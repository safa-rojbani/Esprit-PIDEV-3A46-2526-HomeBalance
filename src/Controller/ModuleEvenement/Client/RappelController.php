<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\Evenement;
use App\Entity\Rappel;
use App\Form\RappelType;
use App\Repository\EvenementRepository;
use App\Repository\RappelRepository;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app')]
class RappelController extends AbstractController
{
    #[Route('/rappels', name: 'app_rappel_history', methods: ['GET'])]
    public function history(RappelRepository $rappelRepository): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->render('app/rappel/history.html.twig', [
                'rappels' => [],
            ]);
        }

        $rappelRepository->cleanupOrphanedAndPast();
        $now = new \DateTimeImmutable();
        $rappels = $rappelRepository->createQueryBuilder('r')
            ->innerJoin('r.evenement', 'e')
            ->andWhere('r.user = :user')
            ->andWhere('e.dateFin >= :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('app/rappel/history.html.twig', [
            'rows' => array_map(static function (Rappel $rappel): array {
                $eventId = null;
                $eventTitle = null;
                try {
                    $event = $rappel->getEvenement();
                    if ($event !== null) {
                        $eventId = $event->getId();
                        $eventTitle = $event->getTitre();
                    }
                } catch (EntityNotFoundException $exception) {
                    $eventId = null;
                    $eventTitle = null;
                }

                return [
                    'rappel' => $rappel,
                    'eventId' => $eventId,
                    'eventTitle' => $eventTitle,
                ];
            }, $rappels),
        ]);
    }

    #[Route('/evenement/{id}/rappels', name: 'app_evenement_rappels', methods: ['GET'])]
    public function index(Evenement $evenement, RappelRepository $rappelRepository): Response
    {
        if (!$this->canViewEvent($evenement)) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $rappels = $rappelRepository->findBy(
            ['evenement' => $evenement, 'user' => $user],
            ['offsetMinutes' => 'ASC']
        );

        return $this->render('app/rappel/index.html.twig', [
            'evenement' => $evenement,
            'rappels' => $rappels,
        ]);
    }

    #[Route('/rappel/new', name: 'app_rappel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $entityManager): Response
    {
        $eventId = $request->query->get('evenement');
        if (!$eventId) {
            throw $this->createNotFoundException('Missing event id.');
        }

        $evenement = $evenementRepository->find($eventId);
        if (!$evenement || !$this->canViewEvent($evenement)) {
            throw $this->createAccessDeniedException();
        }

        $rappel = new Rappel();
        $form = $this->createForm(RappelType::class, $rappel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $rappel->setEvenement($evenement);
            $rappel->setUser($user);
            $rappel->setFamily($user?->getFamily());
            if ($rappel->isActif() === null) {
                $rappel->setActif(true);
            }
            if ($rappel->isEstLu() === null) {
                $rappel->setEstLu(false);
            }
            $rappel->setScheduledAt(
                $evenement->getDateDebut()->sub(new \DateInterval('PT' . $rappel->getOffsetMinutes() . 'M'))
            );

            $entityManager->persist($rappel);
            $entityManager->flush();

            return $this->redirectToRoute('app_evenement_rappels', ['id' => $evenement->getId()]);
        }

        return $this->render('app/rappel/new.html.twig', [
            'evenement' => $evenement,
            'rappel' => $rappel,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rappel/{id}/edit', name: 'app_rappel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rappel $rappel, EntityManagerInterface $entityManager): Response
    {
        if ($rappel->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RappelType::class, $rappel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $evenement = $rappel->getEvenement();
            if ($evenement !== null) {
                $rappel->setScheduledAt(
                    $evenement->getDateDebut()->sub(new \DateInterval('PT' . $rappel->getOffsetMinutes() . 'M'))
                );
            }
            $entityManager->flush();

            try {
                $eventId = $rappel->getEvenement()?->getId();
            } catch (EntityNotFoundException $exception) {
                $eventId = null;
            }

            if ($eventId !== null) {
                return $this->redirectToRoute('app_evenement_rappels', [
                    'id' => $eventId,
                ]);
            }

            return $this->redirectToRoute('app_rappel_history');
        }

        return $this->render('app/rappel/edit.html.twig', [
            'evenement' => $rappel->getEvenement(),
            'rappel' => $rappel,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/rappel/{id}', name: 'app_rappel_delete', methods: ['POST'])]
    public function delete(Request $request, Rappel $rappel, EntityManagerInterface $entityManager): Response
    {
        if ($rappel->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$rappel->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rappel);
            $entityManager->flush();
        }

        try {
            $eventId = $rappel->getEvenement()?->getId();
        } catch (EntityNotFoundException $exception) {
            $eventId = null;
        }

        if ($eventId !== null) {
            return $this->redirectToRoute('app_evenement_rappels', [
                'id' => $eventId,
            ]);
        }

        return $this->redirectToRoute('app_rappel_history');
    }

    private function canViewEvent(Evenement $evenement): bool
    {
        $user = $this->getUser();
        if ($user === null) {
            return false;
        }

        if ($evenement->getCreatedBy() === $user) {
            return true;
        }

        if ($evenement->getCreatedBy() === null) {
            return true;
        }

        return $user->getFamily() !== null
            && $evenement->getFamily() === $user->getFamily();
    }
}
