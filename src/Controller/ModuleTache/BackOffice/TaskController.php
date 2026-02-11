<?php

namespace App\Controller\ModuleTache\BackOffice;

use App\Entity\Task;
use App\Entity\User;
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

#[Route('/admin/tasks')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'admin_task_index')]
public function index(
    TaskRepository $taskRepository,
    EntityManagerInterface $em
): Response {
    $admin = $em->getRepository(User::class)->findOneBy([
        'email' => 'admin@test.com'
    ]);

    if (!$admin) {
        throw new \Exception('Admin introuvable');
    }

    $tasks = $taskRepository->findAdminTasks($admin);

    return $this->render('ModuleTache/backoffice/index.html.twig', [
        'tasks' => $tasks,
    ]);
}


   

#[Route('/create', name: 'admin_task_create')]
public function create(Request $request, EntityManagerInterface $em): Response
{
    $task = new Task();

    $form = $this->createForm(TaskType::class, $task);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $admin = $em->getRepository(User::class)->findOneBy([
            'email' => 'admin@test.com'
        ]);

        if (!$admin) {
            throw new \Exception('Admin introuvable');
        }

        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setIsActive(true);
        $task->setCreatedBy($admin);
        $task->setFamily(null);

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
    EntityManagerInterface $em
): Response {
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
    EntityManagerInterface $em
): Response {
    if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->get('_token'))) {
        $em->remove($task);
        $em->flush();
    }

    return $this->redirectToRoute('admin_task_index');
}


}
