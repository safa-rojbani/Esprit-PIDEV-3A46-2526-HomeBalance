<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\Family;
use App\Entity\SavingGoal;
use App\Entity\User;
use App\Form\ModuleCharge\SavingGoalType;
use App\Repository\SavingGoalRepository;
use App\Service\ActiveFamilyResolver;
use App\ServiceModuleCharge\SavingGoalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/charge/epargne')]
final class SavingGoalController extends AbstractController
{
    #[Route('', name: 'app_saving_goal_index', methods: ['GET'])]
    public function index(Request $request, SavingGoalRepository $repository, SavingGoalService $savingGoalService, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $searchQuery = trim((string) $request->query->get('q', ''));
        $goals = $repository->searchByFamily($family, $searchQuery);

        $plans = [];
        foreach ($goals as $goal) {
            $plans[$goal->getId()] = $savingGoalService->buildPlan($goal, $family);
        }

        return $this->render('module_charge/User/saving_goal/index.html.twig', [
            'saving_goals' => $goals,
            'plans' => $plans,
            'monthlyCapacity' => $savingGoalService->monthlyCapacity($family),
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/new', name: 'app_saving_goal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $savingGoal = new SavingGoal();
        $form = $this->createForm(SavingGoalType::class, $savingGoal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $savingGoal->setFamily($family);
            $savingGoal->setCreatedBy($user);
            $savingGoal->setCreatedAt(new \DateTimeImmutable());
            $savingGoal->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($savingGoal);
            $entityManager->flush();

            return $this->redirectToRoute('app_saving_goal_index');
        }

        return $this->render('module_charge/User/saving_goal/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_saving_goal_show', methods: ['GET'])]
    public function show(SavingGoal $savingGoal, SavingGoalService $savingGoalService, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $savingGoal->getFamily());

        return $this->render('module_charge/User/saving_goal/show.html.twig', [
            'saving_goal' => $savingGoal,
            'plan' => $savingGoalService->buildPlan($savingGoal, $family),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_saving_goal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SavingGoal $savingGoal, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $savingGoal->getFamily());

        $form = $this->createForm(SavingGoalType::class, $savingGoal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $savingGoal->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            return $this->redirectToRoute('app_saving_goal_index');
        }

        return $this->render('module_charge/User/saving_goal/edit.html.twig', [
            'saving_goal' => $savingGoal,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/contribute', name: 'app_saving_goal_contribute', methods: ['POST'])]
    public function contribute(Request $request, SavingGoal $savingGoal, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $savingGoal->getFamily());

        if (!$this->isCsrfTokenValid('contribute_'.$savingGoal->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('app_saving_goal_show', ['id' => $savingGoal->getId()]);
        }

        $amountRaw = str_replace(',', '.', trim((string) $request->request->get('amount', '0')));
        $amount = (float) $amountRaw;
        if ($amount > 0) {
            $current = (float) $savingGoal->getCurrentAmount();
            $savingGoal->setCurrentAmount(number_format($current + $amount, 2, '.', ''));
            $savingGoal->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_saving_goal_show', ['id' => $savingGoal->getId()]);
    }

    #[Route('/{id}', name: 'app_saving_goal_delete', methods: ['POST'])]
    public function delete(Request $request, SavingGoal $savingGoal, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $savingGoal->getFamily());

        if ($this->isCsrfTokenValid('delete_saving_goal_'.$savingGoal->getId(), $request->request->get('_token'))) {
            $entityManager->remove($savingGoal);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_saving_goal_index');
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
