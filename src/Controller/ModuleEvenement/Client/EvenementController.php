<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\Evenement;
use App\Entity\Rappel;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/evenement')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        TypeEvenementRepository $typeEvenementRepository
    ): Response
    {
        $user = $this->getUser();

        if ($user === null) {
            return $this->render('app/evenement/index.html.twig', [
                'evenements' => [],
                'types' => [],
                'selectedType' => null,
                'search' => '',
            ]);
        }

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->find($typeId);
        }

        $evenements = $evenementRepository->findForUserWithFilters($user, $selectedType, $search);

        $family = $user->getFamily();
        $typesQb = $typeEvenementRepository->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC');
        if ($family !== null) {
            $typesQb->andWhere('t.family IS NULL OR t.family = :family')
                ->setParameter('family', $family);
        }
        $types = $typesQb->getQuery()->getResult();

        return $this->render('app/evenement/index.html.twig', [
            'evenements' => $evenements,
            'types' => $types,
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $evenement->setCreatedBy($user);
            $evenement->setFamily($user?->getFamily());

            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);

            $entityManager->persist($evenement);
            // Default reminder: 1 day before (1440 minutes) only if event is >= 24h away
            $now = new \DateTimeImmutable();
            $evenement->setDateCreation($now);
            $evenement->setDateModification($now);

            $hoursUntil = ($evenement->getDateDebut()->getTimestamp() - $now->getTimestamp()) / 3600;
            if ($hoursUntil >= 24) {
                $defaultRappel = new Rappel();
                $defaultRappel->setEvenement($evenement);
                $defaultRappel->setUser($user);
                $defaultRappel->setFamily($user?->getFamily());
                $defaultRappel->setOffsetMinutes(1440);
                $defaultRappel->setCanal('popup');
                $defaultRappel->setActif(true);
                $defaultRappel->setEstLu(false);
                $defaultRappel->setScheduledAt($evenement->getDateDebut()->sub(new \DateInterval('PT1440M')));
                $entityManager->persist($defaultRappel);
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('app/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        $user = $this->getUser();
        $isOwner = ($evenement->getCreatedBy() === $user);
        $isGlobal = ($evenement->getCreatedBy() === null);
        $isSameFamily = $user?->getFamily() !== null
            && $evenement->getFamily() === $user->getFamily();

        if (!($isOwner || $isGlobal || $isSameFamily)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('app/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($evenement->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setDateModification(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('app/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($evenement->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $entityManager->remove($evenement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}
