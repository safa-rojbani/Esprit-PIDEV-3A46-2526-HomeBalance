<?php

namespace App\Controller\ModuleCharge\Admin;

use App\Entity\Family;
use App\Entity\TypeRevenu;
use App\Entity\User;
use App\Form\ModuleCharge\TypeRevenuType;
use App\Repository\TypeRevenuRepository;
use App\Service\ActiveFamilyResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/charge/types-revenu')]
final class TypeRevenuController extends AbstractController
{
    #[Route('', name: 'app_type_revenu_index', methods: ['GET'])]
    public function index(TypeRevenuRepository $repository, ActiveFamilyResolver $familyResolver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $family = $this->resolveFamily($familyResolver);

        return $this->render('module_charge/Admin/type_revenu/index.html.twig', [
            'types_revenu' => $repository->findBy(['family' => $family], ['nomType' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_type_revenu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $family = $this->resolveFamily($familyResolver);

        $typeRevenu = new TypeRevenu();
        $form = $this->createForm(TypeRevenuType::class, $typeRevenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeRevenu->setFamily($family);
            $entityManager->persist($typeRevenu);
            $entityManager->flush();

            return $this->redirectToRoute('app_type_revenu_index');
        }

        return $this->render('module_charge/Admin/type_revenu/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_revenu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeRevenu $typeRevenu, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $typeRevenu->getFamily());

        $form = $this->createForm(TypeRevenuType::class, $typeRevenu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_type_revenu_index');
        }

        return $this->render('module_charge/Admin/type_revenu/edit.html.twig', [
            'form' => $form->createView(),
            'type_revenu' => $typeRevenu,
        ]);
    }

    #[Route('/{id}', name: 'app_type_revenu_delete', methods: ['POST'])]
    public function delete(Request $request, TypeRevenu $typeRevenu, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $typeRevenu->getFamily());

        if ($this->isCsrfTokenValid('delete_type_revenu_'.$typeRevenu->getId(), $request->request->get('_token'))) {
            $entityManager->remove($typeRevenu);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_type_revenu_index');
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
