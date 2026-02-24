<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\Credit;
use App\Form\ModuleCharge\CreditType;
use App\Repository\CreditRepository;
use App\ServiceModuleCharge\CreditRateApiService;
use App\ServiceModuleCharge\CreditSimulationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/charge/credits')]
final class CreditController extends AbstractController
{
    #[Route('', name: 'app_credit_index', methods: ['GET'])]
    public function index(Request $request, CreditRepository $repository, CreditSimulationService $simulationService): Response
    {
        $searchQuery = trim((string) $request->query->get('q', ''));
        $credits = $repository->search($searchQuery);

        $simulations = [];
        foreach ($credits as $credit) {
            $simulations[$credit->getId()] = $simulationService->simulate($credit);
        }

        return $this->render('module_charge/User/credit/index.html.twig', [
            'credits' => $credits,
            'simulations' => $simulations,
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/new', name: 'app_credit_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CreditRateApiService $creditRateApiService
    ): Response {
        $credit = new Credit();
        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $useApiRate = (bool) $form->get('useApiRate')->getData();
            $countryCode = (string) $form->get('countryCode')->getData();

            if (($credit->getAnnualRate() === null || $credit->getAnnualRate() === '') && $useApiRate) {
                $apiRate = $creditRateApiService->fetchLendingRate($countryCode);
                if ($apiRate !== null) {
                    $credit->setAnnualRate(number_format($apiRate['rate'], 2, '.', ''));
                    $this->addFlash('success', sprintf('Taux API applique: %s%% (annee %d).', $credit->getAnnualRate(), $apiRate['year']));
                }
            }

            if ($credit->getAnnualRate() === null || $credit->getAnnualRate() === '') {
                $this->addFlash('error', 'Le taux annuel est obligatoire (API indisponible ou non selectionnee).');

                return $this->render('module_charge/User/credit/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $credit->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($credit);
            $entityManager->flush();

            return $this->redirectToRoute('app_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('module_charge/User/credit/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_credit_show', methods: ['GET'])]
    public function show(Credit $credit, CreditSimulationService $simulationService): Response
    {
        return $this->render('module_charge/User/credit/show.html.twig', [
            'credit' => $credit,
            'simulation' => $simulationService->simulate($credit),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_credit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CreditType::class, $credit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('module_charge/User/credit/edit.html.twig', [
            'credit' => $credit,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_credit_delete', methods: ['POST'])]
    public function delete(Request $request, Credit $credit, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_credit_'.$credit->getId(), $request->request->get('_token'))) {
            $entityManager->remove($credit);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_credit_index');
    }
}
