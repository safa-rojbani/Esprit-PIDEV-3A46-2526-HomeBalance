<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\User;
use App\Entity\TaskAssignment;
use App\Enum\TaskAssignmentStatus;
use App\Form\TaskAssignmentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/parent/assignments')]
class ParentTaskAssignmentController extends AbstractController
{
    #[Route('/new', name: 'parent_task_assign')]
    public function assign(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $parent = $em->getRepository(User::class)->findOneBy([
            'email' => 'parent@test.com'
        ]);

        $family = $parent->getFamily();

        $assignment = new TaskAssignment();

        $form = $this->createForm(TaskAssignmentType::class, $assignment, [
            'family' => $family
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 🔐 Sécurité finale (bonne pratique)
            if ($assignment->getTask()->getFamily() !== $family) {
                throw new \Exception('Tâche invalide pour cette famille');
            }

            if ($assignment->getUser()->getFamily() !== $family) {
                throw new \Exception('Cet enfant ne fait pas partie de la famille');
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
}
