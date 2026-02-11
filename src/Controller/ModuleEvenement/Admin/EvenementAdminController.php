<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Entity\Evenement;
use App\Form\EvenementAdminType;
use App\Repository\EvenementRepository;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/admin/evenements')]
class EvenementAdminController extends AbstractController
{
    #[Route('', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        TypeEvenementRepository $typeEvenementRepository,
        ActiveFamilyResolver $familyResolver
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->find($typeId);
        }

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenementRepository->findWithFilters($family, $selectedType, $search),
            'types' => $typeEvenementRepository->findBy(['family' => $family], ['nom' => 'ASC']),
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $evenement = new Evenement();

        $form = $this->createForm(EvenementAdminType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);
            $evenement->setFamily($family);
            if ($user instanceof User) {
                $evenement->setCreatedBy($user);
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        $form = $this->createForm(EvenementAdminType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $evenement->getFamily());

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_evenement_index');
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
