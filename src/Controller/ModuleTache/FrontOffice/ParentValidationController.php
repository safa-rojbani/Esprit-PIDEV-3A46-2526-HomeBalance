<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\TaskCompletion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ParentRefusalType;

#[Route('/parent/validations')]
class ParentValidationController extends AbstractController
{
    private function getParent(EntityManagerInterface $em): User
    {
        return $em->getRepository(User::class)->findOneBy([
            'email' => 'parent@test.com'
        ]);
    }

    #[Route('/', name: 'parent_validation_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $parent = $this->getParent($em);
        $family = $parent->getFamily();

        // 🔍 toutes les validations en attente pour la famille
        $pendingCompletions = $em->createQueryBuilder()
            ->select('tc')
            ->from(TaskCompletion::class, 'tc')
            ->join('tc.task', 't')
            ->where('tc.isValidated IS NULL')
            ->andWhere('t.family = :family')
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();

        return $this->render('ModuleTache/frontoffice/parent/validations.html.twig', [
            'completions' => $pendingCompletions,
        ]);
    }

    #[Route('/{id}/accept', name: 'parent_validation_accept')]
public function accept(
    TaskCompletion $completion,
    EntityManagerInterface $em
): Response {
    $parent = $this->getParent($em);

    if ($completion->isValidated() !== null) {
        throw new \Exception('Déjà traité');
    }

    $completion->setIsValidated(true);
    $completion->setValidatedBy($parent);
    $completion->setValidatedAt(new \DateTimeImmutable());

    $em->flush();

    return $this->redirectToRoute('parent_validation_index');
}
#[Route('/{id}/refuse', name: 'parent_validation_refuse')]
public function refuse(
    TaskCompletion $completion,
    Request $request,
    EntityManagerInterface $em
): Response {
    $parent = $this->getParent($em);

    if ($completion->isValidated() !== null) {
        throw new \Exception('Déjà traité');
    }

    $form = $this->createForm(ParentRefusalType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $completion->setIsValidated(false);
        $completion->setValidatedBy($parent);
        $completion->setValidatedAt(new \DateTimeImmutable());
        $completion->setParentComment(
            $form->get('parentComment')->getData()
        );

        $em->flush();

        return $this->redirectToRoute('parent_validation_index');
    }

    return $this->render('ModuleTache/frontoffice/parent/refuse.html.twig', [
        'form' => $form->createView(),
        'completion' => $completion,
    ]);
}


}
