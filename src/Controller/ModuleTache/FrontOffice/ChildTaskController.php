<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\User;
use App\Entity\Task;
use App\Entity\TaskAssignment;
use App\Entity\TaskCompletion;
use App\Enum\TaskAssignmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\TaskCompletionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/child/tasks')]
class ChildTaskController extends AbstractController
{
    private function getChild(EntityManagerInterface $em): User
    {
        return $em->getRepository(User::class)->findOneBy([
            'email' => 'child@test.com'
        ]);
    }

    #[Route('/', name: 'child_task_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $child = $this->getChild($em);
        $family = $child->getFamily();

        // 1️⃣ Tâches assignées à l’enfant (ASSIGNED uniquement)
        $assignedTasks = $em->getRepository(TaskAssignment::class)
            ->findBy([
                'user' => $child,
                'status' => TaskAssignmentStatus::ASSIGNED->value
            ]);

        // 2️⃣ Tâches familiales libres
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

foreach ($freeTasks as $task) {
    $assignment = $em->getRepository(TaskAssignment::class)->findOneBy([
        'task' => $task,
        'status' => TaskAssignmentStatus::ACCEPTED->value
    ]);

    if ($assignment) {
        if ($assignment->getUser() === $child) {
            $acceptedByMe[$task->getId()] = true;
        } else {
            $acceptedByOther[$task->getId()] = true;
        }
    }
}
return $this->render('ModuleTache/frontoffice/child/index.html.twig', [
    'assignedTasks' => $assignedTasks,
    'freeTasks' => $freeTasks,
    'acceptedByMe' => $acceptedByMe,
    'acceptedByOther' => $acceptedByOther,
]);

    }

    #[Route('/assignment/{id}/accept', name: 'child_task_accept')]
    public function accept(TaskAssignment $assignment, EntityManagerInterface $em): Response
    {
        $child = $this->getChild($em);

        // 🔐 sécurité
        if ($assignment->getUser() !== $child) {
            throw new \Exception('Action non autorisée');
        }

        // ✅ COMPARAISON SAFE
        if ($assignment->getStatus()?->value !== TaskAssignmentStatus::ASSIGNED->value) {
            throw new \Exception('Tâche non acceptable');
        }

        $assignment->setStatus(TaskAssignmentStatus::ACCEPTED);
        $em->flush();

        return $this->redirectToRoute('child_task_index');
    }

    #[Route('/assignment/{id}/refuse', name: 'child_task_refuse')]
    public function refuse(TaskAssignment $assignment, EntityManagerInterface $em): Response
    {
        $child = $this->getChild($em);

        if ($assignment->getUser() !== $child) {
            throw new \Exception('Action non autorisée');
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
    EntityManagerInterface $em
): Response {
    $child = $this->getChild($em);

    // 1️⃣ Vérifier s’il existe une assignation ACCEPTED
    $assignment = $em->getRepository(TaskAssignment::class)->findOneBy([
        'task' => $task,
        'status' => TaskAssignmentStatus::ACCEPTED->value
    ]);

    if ($assignment && $assignment->getUser() !== $child) {
        throw new \Exception('Tâche réservée à un autre enfant');
    }

    // 2️⃣ Chercher UNE complétion existante (refusée ou validée)
    $completion = $em->getRepository(TaskCompletion::class)->findOneBy([
        'task' => $task,
        'user' => $child,
    ]);

    if (!$completion) {
        $completion = new TaskCompletion();
        $completion->setTask($task);
        $completion->setUser($child);
    }

    // 3️⃣ Formulaire
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

        // 🔁 RESET COMPLET POUR NOUVELLE VALIDATION
        $completion->setProof($filename);
        $completion->setCompletedAt(new \DateTimeImmutable());
        $completion->setIsValidated(null);      // ⏳ en attente
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

}
