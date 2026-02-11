<?php

namespace App\Controller\ModuleTache\BackOffice;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Family;
use App\Repository\UserRepository;
use App\Enum\TaskDifficulty;
use App\Enum\TaskRecurrence;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\TaskType;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/admin/tasks')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'admin_task_index')]
public function index(
    TaskRepository $taskRepository,
    EntityManagerInterface $em,
    ActiveFamilyResolver $familyResolver
): Response {
    [$admin, $family] = $this->resolveUserAndFamily($familyResolver);

    $tasks = $taskRepository->findAdminTasks($admin, $family);

    return $this->render('ModuleTache/backoffice/index.html.twig', [
        'tasks' => $tasks,
    ]);
}


   

#[Route('/create', name: 'admin_task_create')]
public function create(Request $request, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
{
    [$admin, $family] = $this->resolveUserAndFamily($familyResolver);
    $task = new Task();

    $form = $this->createForm(TaskType::class, $task);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setIsActive(true);
        $task->setCreatedBy($admin);
        $task->setFamily($family);

        $em->persist($task);
        $em->flush();

        return $this->redirectToRoute('admin_task_index');
    }

    return $this->render('ModuleTache/backoffice/create.html.twig', [
        'form' => $form->createView(),
    ]);
}


#[Route('/{id}/edit', name: 'admin_task_edit')]
public function edit(
    Task $task,
    Request $request,
    EntityManagerInterface $em,
    ActiveFamilyResolver $familyResolver
): Response {
    [, $family] = $this->resolveUserAndFamily($familyResolver);
    if ($task->getFamily()?->getId() !== $family->getId()) {
        throw $this->createAccessDeniedException();
    }

    $form = $this->createForm(TaskType::class, $task);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        return $this->redirectToRoute('admin_task_index');
    }

    return $this->render('ModuleTache/backoffice/edit.html.twig', [
        'form' => $form->createView(),
        'task' => $task,
    ]);
}


#[Route('/{id}/delete', name: 'admin_task_delete', methods: ['POST'])]
public function delete(
    Request $request,
    Task $task,
    EntityManagerInterface $em,
    ActiveFamilyResolver $familyResolver
): Response {
    [, $family] = $this->resolveUserAndFamily($familyResolver);
    if ($task->getFamily()?->getId() !== $family->getId()) {
        throw $this->createAccessDeniedException();
    }

    if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->get('_token'))) {
        $em->remove($task);
        $em->flush();
    }

    return $this->redirectToRoute('admin_task_index');
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

}
