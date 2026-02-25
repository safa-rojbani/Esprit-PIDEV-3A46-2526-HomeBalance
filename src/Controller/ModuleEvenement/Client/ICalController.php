<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\EvenementRepository;
use App\Repository\TypeEvenementRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\ModuleEvenement\ICalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/ical')]
class ICalController extends AbstractController
{
    #[Route('/export/{id}', name: 'portal_ical_export', methods: ['GET'])]
    public function export(Evenement $evenement, ActiveFamilyResolver $familyResolver, ICalService $icalService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $evenement->getCreatedBy()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $content = $icalService->generateIcs($evenement);

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evenement.ics"',
        ]);
    }

    #[Route('/export-all', name: 'portal_ical_export_all', methods: ['GET'])]
    public function exportAll(EvenementRepository $evenementRepository, ICalService $icalService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $events = $evenementRepository->findBy(['createdBy' => $user], ['dateDebut' => 'ASC']);
        $content = $icalService->generateIcsCollection($events);

        return new Response($content, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evenements.ics"',
        ]);
    }

    #[Route('/import', name: 'portal_ical_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        ICalService $icalService,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        TypeEvenementRepository $typeEvenementRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ical_import', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('portal_ical_import');
            }

            $file = $request->files->get('ics_file');
            if ($file === null) {
                $this->addFlash('error', 'Veuillez sélectionner un fichier .ics.');
                return $this->redirectToRoute('portal_ical_import');
            }

            $content = file_get_contents($file->getRealPath() ?: '');
            if ($content === false || trim($content) === '') {
                $this->addFlash('error', 'Fichier .ics vide ou illisible.');
                return $this->redirectToRoute('portal_ical_import');
            }

            $family = $familyResolver->resolveForUser($user);
            if ($family === null) {
                throw $this->createAccessDeniedException();
            }

            $defaultType = $typeEvenementRepository->findOneBy(['family' => $family], ['nom' => 'ASC'])
                ?? $typeEvenementRepository->findOneBy(['family' => null], ['nom' => 'ASC']);

            if ($defaultType === null) {
                $this->addFlash('error', 'Aucun type d evenement disponible. Creez un type avant d importer.');
                return $this->redirectToRoute('portal_ical_import');
            }

            $parsed = $icalService->parseIcs($content);
            $created = 0;
            $now = new \DateTimeImmutable();

            foreach ($parsed as $row) {
                if (empty($row['start']) || empty($row['end'])) {
                    continue;
                }
                $evenement = new Evenement();
                $evenement->setTitre((string) ($row['title'] ?? 'Evenement'));
                $evenement->setDescription($row['description'] ?? null);
                $evenement->setLieu($row['location'] ?? 'Non renseigne');
                $evenement->setDateDebut($row['start']);
                $evenement->setDateFin($row['end']);
                $evenement->setCreatedBy($user);
                $evenement->setFamily($family);
                $evenement->setTypeEvenement($defaultType);
                $evenement->setShareWithFamily(false);
                $evenement->setDateCreation($now);
                $evenement->setDateModification($now);
                $entityManager->persist($evenement);
                $created++;
            }

            $entityManager->flush();

            $this->addFlash('success', sprintf('%d evenement(s) importe(s).', $created));
            return $this->redirectToRoute('app_evenement_index');
        }

        return $this->render('ModuleEvenement/Client/ical/import.html.twig');
    }
}
