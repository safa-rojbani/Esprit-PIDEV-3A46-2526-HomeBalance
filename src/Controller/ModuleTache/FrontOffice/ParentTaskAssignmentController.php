<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Family;
use App\Entity\TaskAssignment;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\TaskAssignmentStatus;
use App\Form\TaskAssignmentType;
use App\Repository\FamilyMembershipRepository;
use App\Service\ActiveFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/portal/tasks/parent/assignments')]
class ParentTaskAssignmentController extends AbstractController
{
    #[Route('/new', name: 'parent_task_assign')]
    public function assign(
        Request $request,
        EntityManagerInterface $em,
        FamilyMembershipRepository $membershipRepository,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        $assignment = new TaskAssignment();

        $form = $this->createForm(TaskAssignmentType::class, $assignment, [
            'family' => $family,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($assignment->getTask()->getFamily()?->getId() !== $family->getId()) {
                throw new \Exception('Tache invalide pour cette famille');
            }

            if ($membershipRepository->findActiveMembership($family, $assignment->getUser()) === null) {
                throw new \Exception('Cet utilisateur ne fait pas partie de la famille');
            }

            $assignment->setFamily($family);
            $assignment->setAssignedAt(new \DateTimeImmutable());
            $assignment->setStatus(TaskAssignmentStatus::ASSIGNED);

            $em->persist($assignment);
            $em->flush();

            return $this->redirectToRoute('parent_task_index');
        }

        return $this->render('ModuleTache/frontoffice/parent/assign.html.twig', [
            'form' => $form->createView(),
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

    private function ensureParent(User $user): void
    {
        if ($user->getFamilyRole() !== FamilyRole::PARENT) {
            throw $this->createAccessDeniedException();
        }
    }
}
