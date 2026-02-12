<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Entity\Family;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ParentRefusalType;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/tasks/parent/validations')]
class ParentValidationController extends AbstractController
{
    #[Route('/', name: 'parent_validation_index')]
    public function index(EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        [, $family] = $this->resolveUserAndFamily($familyResolver);

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
    EntityManagerInterface $em,
    ActiveFamilyResolver $familyResolver
): Response {
    [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
    if ($completion->getTask()?->getFamily()?->getId() !== $family->getId()) {
        throw $this->createAccessDeniedException();
    }

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
    EntityManagerInterface $em,
    ActiveFamilyResolver $familyResolver
): Response {
    [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
    if ($completion->getTask()?->getFamily()?->getId() !== $family->getId()) {
        throw $this->createAccessDeniedException();
    }

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
