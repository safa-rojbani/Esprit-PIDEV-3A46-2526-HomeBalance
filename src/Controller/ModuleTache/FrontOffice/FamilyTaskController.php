<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\User;
use App\Entity\Family;
use App\Enum\FamilyRole;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/tasks/family')]
class FamilyTaskController extends AbstractController
{
    #[Route('/tasks', name: 'family_task_list')]
    public function list(
        Request $request,
        TaskRepository $taskRepository,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$user, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParentOrChild($user);

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'newest');
        $allowedSorts = ['newest', 'oldest', 'title_az', 'title_za'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'newest';
        }

        $tasks = $taskRepository->findActiveFamilyTasksFiltered($family, $search, $sort);

        return $this->render('ModuleTache/frontoffice/family/tasks.html.twig', [
            'tasks' => $tasks,
            'currentSearch' => $search,
            'currentSort' => $sort,
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

    private function ensureParentOrChild(User $user): void
    {
        $role = $user->getFamilyRole();
        if ($role !== FamilyRole::PARENT && $role !== FamilyRole::CHILD) {
            throw $this->createAccessDeniedException();
        }
    }
}
