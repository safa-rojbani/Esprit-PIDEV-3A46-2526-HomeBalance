<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Entity\TypeEvenement;
use App\Form\TypeEvenementType;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/portal/admin/evenements/types')]
class TypeEvenementController extends AbstractController
{
    #[Route('', name: 'admin_type_evenement_index', methods: ['GET'])]
    public function index(TypeEvenementRepository $repo): Response
    {
        $this->requireAdminUser();

        return $this->render('admin/type_evenement/index.html.twig', [
            'type_evenements' => $repo->findBy(['family' => null]),
        ]);
    }

    #[Route('/new', name: 'admin_type_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->requireAdminUser();

        $typeEvenement = new TypeEvenement();
        $form = $this->createForm(TypeEvenementType::class, $typeEvenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEvenement->setDateCreation(new \DateTimeImmutable());
            $typeEvenement->setFamily(null);

            $em->persist($typeEvenement);
            $em->flush();

            return $this->redirectToRoute('admin_type_evenement_index');
        }

        return $this->render('admin/type_evenement/new.html.twig', [
            'type_evenement' => $typeEvenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_type_evenement_show', methods: ['GET'])]
    public function show(TypeEvenement $typeEvenement): Response
    {
        $this->assertGlobalType($typeEvenement);

        return $this->render('admin/type_evenement/show.html.twig', [
            'type_evenement' => $typeEvenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_type_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeEvenement $typeEvenement, EntityManagerInterface $em): Response
    {
        $this->assertGlobalType($typeEvenement);

        $form = $this->createForm(TypeEvenementType::class, $typeEvenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_type_evenement_index');
        }

        return $this->render('admin/type_evenement/edit.html.twig', [
            'type_evenement' => $typeEvenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_type_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, TypeEvenement $typeEvenement, EntityManagerInterface $em): Response
    {
        $this->assertGlobalType($typeEvenement);

        if ($this->isCsrfTokenValid('delete'.$typeEvenement->getId(), $request->request->get('_token'))) {
            $em->remove($typeEvenement);
            $em->flush();
        }

        return $this->redirectToRoute('admin_type_evenement_index');
    }

    private function requireAdminUser(): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    }

    private function assertGlobalType(TypeEvenement $typeEvenement): void
    {
        $this->requireAdminUser();
        if ($typeEvenement->getFamily() !== null) {
            throw $this->createAccessDeniedException();
        }
    }
}
