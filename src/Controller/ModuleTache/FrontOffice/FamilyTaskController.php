<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\User;
use App\Entity\Family;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/tasks/family')]
class FamilyTaskController extends AbstractController
{
    #[Route('/tasks', name: 'family_task_list')]
    public function list(
        TaskRepository $taskRepository,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [, $family] = $this->resolveUserAndFamily($familyResolver);

        $tasks = $taskRepository->findBy([
            'family' => $family
        ]);

        return $this->render('ModuleTache/frontoffice/family/tasks.html.twig', [
            'tasks' => $tasks,
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
}
