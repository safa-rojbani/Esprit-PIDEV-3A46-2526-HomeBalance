<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\Service\FamilyOnboardingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/onboarding', name: 'portal_onboarding_')]
final class OnboardingController extends AbstractController
{
    #[Route('/family', name: 'family', methods: ['GET'])]
    public function family(ActiveFamilyResolver $familyResolver): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->redirectToRoute('portal_auth_login');
        }

        if ($familyResolver->hasActiveFamily($user)) {
            return $this->redirectToRoute('portal_dashboard');
        }

        return $this->render('ui_portal/onboarding/family.html.twig', [
            'active_menu' => 'onboarding',
        ]);
    }

    #[Route('/family/create', name: 'family_create', methods: ['POST'])]
    public function createFamily(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        FamilyOnboardingService $onboardingService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('portal_auth_login');
        }

        if ($familyResolver->hasActiveFamily($user)) {
            return $this->redirectToRoute('portal_dashboard');
        }

        if (!$this->isCsrfTokenValid('family_onboarding_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $onboardingService->createFamily($user, (string) $request->request->get('familyName'));
        if (!$result->success) {
            foreach ($result->errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('portal_onboarding_family');
        }

        if ($result->message) {
            $this->addFlash('success', $result->message);
        }

        return $this->redirectToRoute('portal_dashboard');
    }

    #[Route('/family/join', name: 'family_join', methods: ['POST'])]
    public function joinFamily(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        FamilyOnboardingService $onboardingService,
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('portal_auth_login');
        }

        if ($familyResolver->hasActiveFamily($user)) {
            return $this->redirectToRoute('portal_dashboard');
        }

        if (!$this->isCsrfTokenValid('family_onboarding_join', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $onboardingService->joinFamily($user, (string) $request->request->get('joinCode'));
        if (!$result->success) {
            foreach ($result->errors as $error) {
                $this->addFlash('error', $error);
            }

            return $this->redirectToRoute('portal_onboarding_family');
        }

        if ($result->message) {
            $this->addFlash('success', $result->message);
        }

        return $this->redirectToRoute('portal_dashboard');
    }
}
