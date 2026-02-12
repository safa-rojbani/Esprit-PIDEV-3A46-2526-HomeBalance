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
use App\Service\ActiveFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/portal/tasks/child')]
class ChildTaskController extends AbstractController
{
    #[Route('/', name: 'child_task_index')]
    public function index(EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        [$child, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureChild($child);

        $assignedTasks = $em->getRepository(TaskAssignment::class)->findBy([
            'user' => $child,
            'status' => TaskAssignmentStatus::ASSIGNED->value,
        ]);

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

        return $this->render('ModuleTache/frontoffice/child/index.html.twig', [
            'assignedTasks' => $assignedTasks,
            'freeTasks' => $freeTasks,
            'acceptedByMe' => $acceptedByMe,
            'acceptedByOther' => $acceptedByOther,
            'acceptedByOtherNames' => $acceptedByOtherNames,
        ]);
    }

    #[Route('/assignment/{id}/accept', name: 'child_task_accept')]
    public function accept(
        TaskAssignment $assignment,
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

        return $this->redirectToRoute('child_task_index');
    }

    #[Route('/assignment/{id}/refuse', name: 'child_task_refuse')]
    public function refuse(
        TaskAssignment $assignment,
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

        return $this->redirectToRoute('child_task_index');
    }

    #[Route('/task/{id}/complete', name: 'child_task_complete')]
    public function complete(
        Task $task,
        Request $request,
        EntityManagerInterface $em,
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

            return $this->redirectToRoute('child_task_index');
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

            $em->persist($completion);
            $em->flush();

            return $this->redirectToRoute('child_task_index');
        }

        return $this->render('ModuleTache/frontoffice/child/complete.html.twig', [
            'form' => $form->createView(),
            'task' => $task,
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
}
