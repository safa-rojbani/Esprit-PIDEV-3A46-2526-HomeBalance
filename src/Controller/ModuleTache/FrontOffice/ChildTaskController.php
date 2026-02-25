<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Family;
use App\Entity\Task;
use App\Entity\TaskAssignment;
use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\TaskAssignmentStatus;
use App\Form\TaskCompletionType;
use App\Message\AnalyzeTaskProofImageMessage;
use App\Message\TaskCompleted;
use App\Service\ActiveFamilyResolver;
use App\Service\TaskPenaltyService;
use App\Service\TaskPointResolver;
use App\Repository\TaskAssignmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/portal/tasks/child')]
class ChildTaskController extends AbstractController
{
    #[Route('/', name: 'child_task_index')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        TaskPointResolver $taskPointResolver,
        PaginatorInterface $paginator,
        ActiveFamilyResolver $familyResolver
    ): Response
    {
        [$child, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureChild($child);
        [$currentFilter, $currentSort] = $this->resolveTaskViewOptions($request);

        $assignedTasks = $em->getRepository(TaskAssignment::class)->findBy([
            'user' => $child,
            'status' => TaskAssignmentStatus::ASSIGNED->value,
        ], ['id' => 'DESC']);

        $qb = $em->createQueryBuilder();
        $freeTasks = $qb
            ->select('t')
            ->from(Task::class, 't')
            ->where('t.family = :family')
            ->andWhere('t.isActive = true')
            ->andWhere(
                $qb->expr()->notIn(
                    't.id',
                    $em->createQueryBuilder()
                        ->select('IDENTITY(a.task)')
                        ->from(TaskAssignment::class, 'a')
                        ->where('a.status IN (:blockedStatuses)')
                        ->getDQL()
                )
            )
            ->setParameter('family', $family)
            ->setParameter('blockedStatuses', [
                TaskAssignmentStatus::ASSIGNED->value,
            ])
            ->getQuery()
            ->getResult();

        $acceptedByMe = [];
        $acceptedByOther = [];
        $acceptedByOtherNames = [];

        foreach ($freeTasks as $task) {
            $assignment = $em->getRepository(TaskAssignment::class)->findOneBy([
                'task' => $task,
                'status' => TaskAssignmentStatus::ACCEPTED->value,
            ]);

            if ($assignment === null) {
                continue;
            }

            if ($assignment->getUser() === $child) {
                $acceptedByMe[$task->getId()] = true;
                continue;
            }

            $acceptedByOther[$task->getId()] = true;
            $user = $assignment->getUser();
            $name = trim(((string) $user->getFirstName()).' '.((string) $user->getLastName()));
            $acceptedByOtherNames[$task->getId()] = $name !== '' ? $name : (string) $user->getEmail();
        }

        $taskStates = [];
        $stateCounts = [
            'all' => \count($freeTasks),
            'available' => 0,
            'assigned_to_me' => 0,
            'pending_validation' => 0,
            'validated' => 0,
            'refused' => 0,
            'reserved' => 0,
        ];

        foreach ($freeTasks as $task) {
            if ($task->getId() === null) {
                continue;
            }

            $taskId = $task->getId();
            $completion = $task->getTaskCompletions()->first();
            if (!$completion instanceof TaskCompletion) {
                $completion = null;
            }

            $state = 'available';
            if ($completion instanceof TaskCompletion) {
                if ($completion->isValidated() === true) {
                    $state = 'validated';
                } elseif ($completion->isValidated() === false) {
                    $state = 'refused';
                } else {
                    $state = 'pending_validation';
                }
            } elseif (isset($acceptedByOther[$taskId])) {
                $state = 'reserved';
            } elseif (isset($acceptedByMe[$taskId])) {
                $state = 'assigned_to_me';
            }

            $taskStates[$taskId] = $state;
            ++$stateCounts[$state];
        }

        if ($currentFilter !== 'all') {
            $freeTasks = \array_values(\array_filter(
                $freeTasks,
                static function (Task $task) use ($taskStates, $currentFilter): bool {
                    if ($task->getId() === null) {
                        return false;
                    }

                    return ($taskStates[$task->getId()] ?? 'available') === $currentFilter;
                }
            ));
        }

        $this->sortTasks($freeTasks, $currentSort);
        $tasksForPoints = $freeTasks;
        foreach ($assignedTasks as $assignedTask) {
            $taskEntity = $assignedTask->getTask();
            if (!$taskEntity instanceof Task) {
                continue;
            }
            $taskId = $taskEntity->getId();
            if ($taskId === null) {
                continue;
            }

            $alreadyPresent = false;
            foreach ($tasksForPoints as $existingTask) {
                if ($existingTask->getId() === $taskId) {
                    $alreadyPresent = true;
                    break;
                }
            }

            if (!$alreadyPresent) {
                $tasksForPoints[] = $taskEntity;
            }
        }
        $taskPoints = $taskPointResolver->resolvePointsForTasks($tasksForPoints);
        $assignedTasks = $paginator->paginate(
            $assignedTasks,
            max(1, $request->query->getInt('assignedPage', 1)),
            6,
            ['pageParameterName' => 'assignedPage']
        );
        $freeTasks = $paginator->paginate(
            $freeTasks,
            max(1, $request->query->getInt('freePage', 1)),
            8,
            ['pageParameterName' => 'freePage']
        );

        return $this->render('ModuleTache/frontoffice/child/index.html.twig', [
            'assignedTasks' => $assignedTasks,
            'freeTasks' => $freeTasks,
            'taskPoints' => $taskPoints,
            'acceptedByMe' => $acceptedByMe,
            'acceptedByOther' => $acceptedByOther,
            'acceptedByOtherNames' => $acceptedByOtherNames,
            'taskStates' => $taskStates,
            'stateCounts' => $stateCounts,
            'currentFilter' => $currentFilter,
            'currentSort' => $currentSort,
        ]);
    }

    #[Route('/assignment/{id}/accept', name: 'child_task_accept')]
    public function accept(
        TaskAssignment $assignment,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$child, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureChild($child);
        if ($assignment->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($assignment->getUser() !== $child) {
            throw new \Exception('Action non autorisee');
        }

        if ($assignment->getStatus()?->value !== TaskAssignmentStatus::ASSIGNED->value) {
            throw new \Exception('Tache non acceptable');
        }

        $assignment->setStatus(TaskAssignmentStatus::ACCEPTED);
        $em->flush();

        return $this->redirectToRoute('child_task_index', $this->buildTaskRedirectParams($request));
    }

    #[Route('/assignment/{id}/refuse', name: 'child_task_refuse')]
    public function refuse(
        TaskAssignment $assignment,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$child, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureChild($child);
        if ($assignment->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($assignment->getUser() !== $child) {
            throw new \Exception('Action non autorisee');
        }

        $assignment->setStatus(TaskAssignmentStatus::CANCELLED);
        $assignment->setRefusedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->redirectToRoute('child_task_index', $this->buildTaskRedirectParams($request));
    }

    #[Route('/task/{id}/complete', name: 'child_task_complete')]
    public function complete(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $messageBus,
        TaskAssignmentRepository $taskAssignmentRepository,
        TaskPenaltyService $taskPenaltyService,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$child, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureChild($child);
        if ($task->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        $assignment = $em->getRepository(TaskAssignment::class)->findOneBy([
            'task' => $task,
            'status' => TaskAssignmentStatus::ACCEPTED->value,
        ]);

        if ($assignment !== null && $assignment->getUser() !== $child) {
            $reservedUser = $assignment->getUser();
            $reservedName = trim(((string) $reservedUser->getFirstName()).' '.((string) $reservedUser->getLastName()));
            if ($reservedName === '') {
                $reservedName = (string) $reservedUser->getEmail();
            }
            $this->addFlash('warning', 'Cette tache est reservee pour '.$reservedName.'.');

            return $this->redirectToRoute('child_task_index', $this->buildTaskRedirectParams($request));
        }

        $completion = $em->getRepository(TaskCompletion::class)->findOneBy([
            'task' => $task,
            'user' => $child,
        ]);

        if (!$completion) {
            $completion = new TaskCompletion();
            $completion->setTask($task);
            $completion->setUser($child);
        }

        $form = $this->createForm(TaskCompletionType::class, $completion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('proof')->getData();
            $filename = uniqid().'.'.$file->guessExtension();

            $file->move(
                $this->getParameter('kernel.project_dir').'/public/uploads/proofs',
                $filename
            );

            $completion->setProof($filename);
            $completion->setCompletedAt(new \DateTimeImmutable());
            $completion->setIsValidated(null);
            $completion->setValidatedAt(null);
            $completion->setValidatedBy(null);
            $completion->setParentComment(null);

            $memberAssignment = $taskAssignmentRepository->findLatestForTaskAndUser($task, $child);
            if ($memberAssignment instanceof TaskAssignment) {
                $memberAssignment->setStatus(TaskAssignmentStatus::COMPLETED);
            }

            $latePenalty = 0;
            if ($memberAssignment instanceof TaskAssignment) {
                $latePenalty = $taskPenaltyService->applyLatePenalty($memberAssignment, $completion);
            }

            $em->persist($completion);
            $em->flush();
            if ($completion->getId() !== null) {
                $messageBus->dispatch(new AnalyzeTaskProofImageMessage($completion->getId()));
            }
            if ($latePenalty < 0) {
                $familyId = $family->getId();
                if ($familyId !== null) {
                    $messageBus->dispatch(new TaskCompleted([
                        'familyId' => $familyId,
                    ]));
                }
                $this->addFlash('warning', sprintf('Penalite retard appliquee: %d pts.', $latePenalty));
            }

            $this->addFlash('info', 'Analyse IA en cours. Le parent sera notifie pour validation si necessaire.');

            return $this->redirectToRoute('child_task_index', $this->buildTaskRedirectParams($request));
        }

        [$currentFilter, $currentSort] = $this->resolveTaskViewOptions($request);

        return $this->render('ModuleTache/frontoffice/child/complete.html.twig', [
            'form' => $form->createView(),
            'task' => $task,
            'currentFilter' => $currentFilter,
            'currentSort' => $currentSort,
        ]);
    }

    /**
     * @return array{0: User, 1: Family}
     */
    private function resolveUserAndFamily(ActiveFamilyResolver $familyResolver): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return [$user, $family];
    }

    private function ensureChild(User $user): void
    {
        if ($user->getFamilyRole() !== FamilyRole::CHILD) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTaskViewOptions(Request $request): array
    {
        $allowedFilters = [
            'all',
            'available',
            'assigned_to_me',
            'pending_validation',
            'validated',
            'refused',
            'reserved',
        ];
        $allowedSorts = ['newest', 'oldest', 'title_az', 'title_za'];

        $filter = (string) $request->query->get('filter', 'all');
        $sort = (string) $request->query->get('sort', 'newest');

        if (!\in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        return [$filter, $sort];
    }

    /**
     * @return array<string, string>
     */
    private function buildTaskRedirectParams(Request $request): array
    {
        [$filter, $sort] = $this->resolveTaskViewOptions($request);
        $params = [];

        if ($filter !== 'all') {
            $params['filter'] = $filter;
        }
        if ($sort !== 'newest') {
            $params['sort'] = $sort;
        }

        return $params;
    }

    /**
     * @param array<int, Task> $tasks
     */
    private function sortTasks(array &$tasks, string $sort): void
    {
        if ($sort === 'oldest') {
            \usort($tasks, static function (Task $a, Task $b): int {
                return ($a->getCreatedAt()?->getTimestamp() ?? 0) <=> ($b->getCreatedAt()?->getTimestamp() ?? 0);
            });

            return;
        }

        if ($sort === 'title_az') {
            \usort($tasks, static function (Task $a, Task $b): int {
                return \strcasecmp((string) $a->getTitle(), (string) $b->getTitle());
            });

            return;
        }

        if ($sort === 'title_za') {
            \usort($tasks, static function (Task $a, Task $b): int {
                return \strcasecmp((string) $b->getTitle(), (string) $a->getTitle());
            });

            return;
        }

        \usort($tasks, static function (Task $a, Task $b): int {
            return ($b->getCreatedAt()?->getTimestamp() ?? 0) <=> ($a->getCreatedAt()?->getTimestamp() ?? 0);
        });
    }
}
