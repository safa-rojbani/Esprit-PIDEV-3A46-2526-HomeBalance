<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent/family')]
class FamilyTaskController extends AbstractController
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

    #[Route('/tasks', name: 'family_task_list')]
    public function list(
        TaskRepository $taskRepository,
        EntityManagerInterface $em
    ): Response {
        $parent = $this->getParent($em);
        $family = $parent->getFamily();

        $tasks = $taskRepository->findBy([
            'family' => $family
        ]);

        return $this->render('ModuleTache/frontoffice/family/tasks.html.twig', [
            'tasks' => $tasks,
        ]);
    }
}
