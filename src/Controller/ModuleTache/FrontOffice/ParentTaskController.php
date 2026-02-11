<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Task;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent/tasks')]
class ParentTaskController extends AbstractController
{
    private function getParent(EntityManagerInterface $em): User
    {
        $parent = $em->getRepository(User::class)->findOneBy([
            'email' => 'parent@test.com'
        ]);

        if (!$parent) {
            throw new \Exception('Parent introuvable');
        }

        return $parent;
    }

    #[Route('/', name: 'parent_task_index')]
    public function index(
        TaskRepository $taskRepository,
        EntityManagerInterface $em
    ): Response {
        $parent = $this->getParent($em);
        $family = $parent->getFamily();

        $adminTasks = $taskRepository->findBy([
            'family' => null,
            'isActive' => true
        ]);

        $familyTasks = $taskRepository->findBy([
            'family' => $family
        ]);

        return $this->render('ModuleTache/frontoffice/parent/index.html.twig', [
            'adminTasks' => $adminTasks,
            'familyTasks' => $familyTasks,
        ]);
    }

    #[Route('/add/{id}', name: 'parent_task_add')]
    public function addAdminTask(
        Task $adminTask,
        EntityManagerInterface $em
    ): Response {
        $parent = $this->getParent($em);

        $task = new Task();
        $task->setTitle($adminTask->getTitle());
        $task->setDescription($adminTask->getDescription());
        $task->setDifficulty($adminTask->getDifficulty());
        $task->setRecurrence($adminTask->getRecurrence());
        $task->setIsActive(true);
        $task->setCreatedAt(new \DateTimeImmutable());
        $task->setCreatedBy($parent);
        $task->setFamily($parent->getFamily());

        $em->persist($task);
        $em->flush();

        return $this->redirectToRoute('parent_task_index');
    }

    #[Route('/new', name: 'parent_task_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $parent = $this->getParent($em);

        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedAt(new \DateTimeImmutable());
            $task->setIsActive(true);
            $task->setCreatedBy($parent);
            $task->setFamily($parent->getFamily());

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
        EntityManagerInterface $em
    ): Response {
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
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
        }

        return $this->redirectToRoute('parent_task_index');
    }
}
