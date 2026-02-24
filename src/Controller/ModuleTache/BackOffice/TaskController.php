<?php

namespace App\Controller\ModuleTache\BackOffice;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\TaskPointResolver;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\TaskType;

#[Route('/portal/admin/tasks')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'admin_task_index')]
    public function index(
        Request $request,
        TaskRepository $taskRepository,
        TaskPointResolver $taskPointResolver,
        PaginatorInterface $paginator
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $tasks = $taskRepository->findGlobalAdminTasks();
        $taskPoints = $taskPointResolver->resolvePointsForTasks($tasks);
        $tasks = $paginator->paginate(
            $tasks,
            max(1, $request->query->getInt('page', 1)),
            10,
            ['pageParameterName' => 'page']
        );

        return $this->render('ModuleTache/backoffice/index.html.twig', [
            'tasks' => $tasks,
            'taskPoints' => $taskPoints,
        ]);
    }


   

#[Route('/create', name: 'admin_task_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $admin = $this->getUser();
        if (!$admin instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $task = new Task();

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($task->getFamily() !== null) {
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
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($task->getFamily() !== null) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), (string) $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
        }

        return $this->redirectToRoute('admin_task_index');
    }
}
