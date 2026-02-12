<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Task;
use App\Entity\TaskAssignment;
use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Entity\Family;
use App\Enum\FamilyRole;
use App\Enum\TaskAssignmentStatus;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/tasks/parent')]
class ParentTaskController extends AbstractController
{
    #[Route('/', name: 'parent_task_index')]
    public function index(
        TaskRepository $taskRepository,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        $adminTasks = $taskRepository->findActiveGlobalAdminTasks();
        $familyTasks = $taskRepository->findFamilyTasks($family);

        return $this->render('ModuleTache/frontoffice/parent/index.html.twig', [
            'adminTasks' => $adminTasks,
            'familyTasks' => $familyTasks,
        ]);
    }

    #[Route('/add/{id}', name: 'parent_task_add', methods: ['POST'])]
    public function addAdminTask(
        Task $adminTask,
        Request $request,
        TaskRepository $taskRepository,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        if ($adminTask->getFamily() !== null || !$adminTask->isActive()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('add_admin_task_'.$adminTask->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $existingFamilyTask = $taskRepository->findFamilyDuplicateFromTemplate($adminTask, $family);
        if ($existingFamilyTask !== null) {
            $this->addFlash('info', 'Cette tâche existe déjà dans la liste familiale.');

            return $this->redirectToRoute('parent_task_index');
        }

        $task = new Task();
        $task->setTitle($adminTask->getTitle());
        $task->setDescription($adminTask->getDescription());
        $task->setDifficulty($adminTask->getDifficulty());
        $task->setRecurrence($adminTask->getRecurrence());
        $task->setIsActive(true);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setCreatedBy($parent);
        $task->setFamily($family);

        $em->persist($task);
        $em->flush();

        return $this->redirectToRoute('parent_task_index');
    }

    #[Route('/new', name: 'parent_task_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedAt(new \DateTimeImmutable());
            $task->setIsActive(true);
            $task->setCreatedBy($parent);
            $task->setFamily($family);

            $em->persist($task);
            $em->flush();

            return $this->redirectToRoute('parent_task_index');
        }

        return $this->render('ModuleTache/frontoffice/parent/form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter une tâche personnalisée'
        ]);
    }

    #[Route('/{id}/edit', name: 'parent_task_edit')]
    public function edit(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);
        if ($task->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('parent_task_index');
        }

        return $this->render('ModuleTache/frontoffice/parent/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'parent_task_delete', methods: ['POST'])]
    public function delete(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);
        if ($task->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
        }

        return $this->redirectToRoute('parent_task_index');
    }

    #[Route('/self', name: 'parent_self_task_index')]
    public function selfTasks(
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);
        [$currentFilter, $currentSort] = $this->resolveSelfTaskViewOptions($request);

        $assignedTasks = $em->getRepository(TaskAssignment::class)->findBy([
            'user' => $parent,
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
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $acceptedByOther = [];
        $acceptedByMe = [];
        $acceptedAssignments = $freeTasks === [] ? [] : $em->getRepository(TaskAssignment::class)->findBy([
            'task' => $freeTasks,
            'status' => TaskAssignmentStatus::ACCEPTED->value,
        ]);

        foreach ($acceptedAssignments as $assignment) {
            $assignedTask = $assignment->getTask();
            if ($assignedTask === null || $assignedTask->getId() === null) {
                continue;
            }

            $taskId = $assignedTask->getId();
            if ($assignment->getUser() === $parent) {
                $acceptedByMe[$taskId] = true;
                continue;
            }

            $user = $assignment->getUser();
            $label = trim(((string) $user->getFirstName()).' '.((string) $user->getLastName()));
            $acceptedByOther[$taskId] = $label !== '' ? $label : (string) $user->getEmail();
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

        $this->sortSelfTasks($freeTasks, $currentSort);

        return $this->render('ModuleTache/frontoffice/parent/self_tasks.html.twig', [
            'assignedTasks' => $assignedTasks,
            'freeTasks' => $freeTasks,
            'acceptedByOther' => $acceptedByOther,
            'taskStates' => $taskStates,
            'stateCounts' => $stateCounts,
            'currentFilter' => $currentFilter,
            'currentSort' => $currentSort,
        ]);
    }

    #[Route('/self/{id}/complete', name: 'parent_self_task_complete', methods: ['POST'])]
    public function completeSelfTask(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        if (!$this->isCsrfTokenValid('parent_self_complete_'.$task->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($task->getFamily()?->getId() !== $family->getId() || !$task->isActive()) {
            throw $this->createAccessDeniedException();
        }

        $assignment = $em->getRepository(TaskAssignment::class)->findOneBy([
            'task' => $task,
            'status' => TaskAssignmentStatus::ACCEPTED->value,
        ]);
        if ($assignment !== null && $assignment->getUser() !== $parent) {
            $reservedUser = $assignment->getUser();
            $reservedName = trim(((string) $reservedUser->getFirstName()).' '.((string) $reservedUser->getLastName()));
            if ($reservedName === '') {
                $reservedName = (string) $reservedUser->getEmail();
            }
            $this->addFlash('warning', 'Cette tache est reservee pour '.$reservedName.'.');

            return $this->redirectToRoute('parent_self_task_index', $this->buildSelfTaskRedirectParams($request));
        }

        $completion = $em->getRepository(TaskCompletion::class)->findOneBy([
            'task' => $task,
            'user' => $parent,
        ]);

        if (!$completion) {
            $completion = new TaskCompletion();
            $completion->setTask($task);
            $completion->setUser($parent);
            $em->persist($completion);
        }

        // Parent completes directly: no proof upload and no pending review step.
        $completion->setProof('parent-no-proof');
        $completion->setCompletedAt(new \DateTimeImmutable());
        $completion->setIsValidated(true);
        $completion->setValidatedBy($parent);
        $completion->setValidatedAt(new \DateTimeImmutable());
        $completion->setParentComment(null);

        $em->flush();

        return $this->redirectToRoute('parent_self_task_index', $this->buildSelfTaskRedirectParams($request));
    }

    #[Route('/self/assignment/{id}/accept', name: 'parent_self_task_accept')]
    public function acceptSelfAssignment(
        TaskAssignment $assignment,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        if ($assignment->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($assignment->getUser() !== $parent) {
            throw new \Exception('Action non autorisee');
        }

        if ($assignment->getStatus()?->value !== TaskAssignmentStatus::ASSIGNED->value) {
            throw new \Exception('Tache non acceptable');
        }

        $assignment->setStatus(TaskAssignmentStatus::ACCEPTED);
        $em->flush();

        return $this->redirectToRoute('parent_self_task_index', $this->buildSelfTaskRedirectParams($request));
    }

    #[Route('/self/assignment/{id}/refuse', name: 'parent_self_task_refuse')]
    public function refuseSelfAssignment(
        TaskAssignment $assignment,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        if ($assignment->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($assignment->getUser() !== $parent) {
            throw new \Exception('Action non autorisee');
        }

        $assignment->setStatus(TaskAssignmentStatus::CANCELLED);
        $assignment->setRefusedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->redirectToRoute('parent_self_task_index', $this->buildSelfTaskRedirectParams($request));
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

    private function ensureParent(User $user): void
    {
        if ($user->getFamilyRole() !== FamilyRole::PARENT) {
            throw $this->createAccessDeniedException();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSelfTaskViewOptions(Request $request): array
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
    private function buildSelfTaskRedirectParams(Request $request): array
    {
        [$filter, $sort] = $this->resolveSelfTaskViewOptions($request);
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
    private function sortSelfTasks(array &$tasks, string $sort): void
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
