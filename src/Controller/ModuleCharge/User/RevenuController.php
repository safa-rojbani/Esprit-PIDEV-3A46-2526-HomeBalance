<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\Revenu;
use App\Form\ModuleCharge\RevenuType;
use App\Repository\RevenuRepository;
use App\ServiceModuleCharge\RevenuService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormError;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/charge/revenus')]
final class RevenuController extends AbstractController
{
    #[Route('', name: 'app_revenu_index', methods: ['GET'])]
    public function index(RevenuRepository $repo, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        return $this->render('module_charge/User/revenu/index.html.twig', [
            'revenus' => $repo->findBy(['family' => $family], ['dateRevenu' => 'DESC']),
        ]);
    }
#calcul budget 
    #[Route('/budget', name: 'app_revenu_budget', methods: ['GET'])]
    public function budget(RevenuService $budgetService, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        return $this->render('module_charge/User/revenu/revenu.html.twig', [
            'totalRevenus' => $budgetService->totalRevenus($family),
            'totalDepenses' => $budgetService->totalDepenses($family),
            'solde' => $budgetService->solde($family),
        ]);
    }

    #[Route('/new', name: 'app_revenu_new', methods: ['GET','POST'])]
    public function new(Request $request, RevenuRepository $repo, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $revenu = new Revenu();

        // types existants (sans family)
        $types = $repo->findDistinctTypesByFamily($family);

        $form = $this->createForm(RevenuType::class, $revenu, ['types' => $types]);
        $form->handleRequest($request);

if ($form->isSubmitted()) {

    $typeLibre = trim((string) $form->get('typeRevenuLibre')->getData());
    if ($typeLibre !== '') {
        $revenu->setTypeRevenu($typeLibre);
    }

    if (!$revenu->getTypeRevenu()) {
        $form->addError(new FormError('Veuillez choisir un type ou saisir un nouveau type.'));
    }

    if ($form->isValid()) {
        $revenu->setCreatedBy($user);
        $revenu->setFamily($family);

        if (!$revenu->getDateRevenu()) {
            $revenu->setDateRevenu(new \DateTimeImmutable());
        }

        $em->persist($revenu);
        $em->flush();

        return $this->redirectToRoute('app_revenu_index');
    }
}

        return $this->render('module_charge/User/revenu/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_revenu_show', methods: ['GET'])]
    public function show(Revenu $revenu, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $revenu->getFamily());

        return $this->render('module_charge/User/revenu/show.html.twig', [
            'revenu' => $revenu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_revenu_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Revenu $revenu, RevenuRepository $repo, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $revenu->getFamily());
        $user = $this->getUser();

        $types = $repo->findDistinctTypesByFamily($family);

        $form = $this->createForm(RevenuType::class, $revenu, ['types' => $types]);
       $form->handleRequest($request);

if ($form->isSubmitted()) {

    $typeLibre = trim((string) $form->get('typeRevenuLibre')->getData());
    if ($typeLibre !== '') {
        $revenu->setTypeRevenu($typeLibre);
    }

    if (!$revenu->getTypeRevenu()) {
        $form->addError(new FormError('Veuillez choisir un type ou saisir un nouveau type.'));
    }

    if ($form->isValid()) {
        $revenu->setCreatedBy($user);
        $revenu->setFamily($family);

        if (!$revenu->getDateRevenu()) {
            $revenu->setDateRevenu(new \DateTimeImmutable());
        }

        $em->persist($revenu);
        $em->flush();

        return $this->redirectToRoute('app_revenu_index');
    }
}

        return $this->render('module_charge/User/revenu/edit.html.twig', [
            'form' => $form->createView(),
            'revenu' => $revenu,
        ]);
    }

    #[Route('/{id}', name: 'app_revenu_delete', methods: ['POST'])]
    public function delete(Request $request, Revenu $revenu, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $revenu->getFamily());

        if ($this->isCsrfTokenValid('delete'.$revenu->getId(), $request->request->get('_token'))) {
            $em->remove($revenu);
            $em->flush();
        }

        return $this->redirectToRoute('app_revenu_index');
    }

    private function resolveFamily(ActiveFamilyResolver $familyResolver): Family
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return $family;
    }

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
