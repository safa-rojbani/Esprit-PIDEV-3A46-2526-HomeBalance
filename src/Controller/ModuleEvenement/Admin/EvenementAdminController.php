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
use App\Entity\User;

#[Route('/portal/admin/evenements')]
class EvenementAdminController extends AbstractController
{
    #[Route('', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        TypeEvenementRepository $typeEvenementRepository
    ): Response
    {
        $admin = $this->requireAdminUser();

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->findOneBy([
                'id' => (int) $typeId,
                'family' => null,
            ]);
        }

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAdminDefaultsWithFilters($admin, $selectedType, $search),
            'types' => $typeEvenementRepository->findBy(['family' => null], ['nom' => 'ASC']),
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $admin = $this->requireAdminUser();

        $evenement = new Evenement();

        $form = $this->createForm(EvenementAdminType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);
            $evenement->setFamily(null);
            $evenement->setCreatedBy($admin);
            $evenement->setShareWithFamily(true);

            $entityManager->persist($evenement);
            $entityManager->flush();

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Evenement $evenement): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

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

    #[Route('/{id}', name: 'admin_evenement_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        $this->assertDefaultOwnedByAdmin($this->requireAdminUser(), $evenement);

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_evenement_index');
    }

    private function requireAdminUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertDefaultOwnedByAdmin(User $admin, Evenement $evenement): void
    {
        $owner = $evenement->getCreatedBy();
        if ($evenement->getFamily() !== null || !$owner instanceof User || $owner->getId() !== $admin->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
