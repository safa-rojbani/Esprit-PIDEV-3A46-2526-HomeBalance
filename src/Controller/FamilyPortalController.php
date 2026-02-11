<?php

namespace App\Controller;

use App\Enum\FamilyRole;
use App\Repository\FamilyInvitationRepository;
use App\Repository\FamilyMembershipRepository;
use App\Service\FamilyManager;
use App\Service\UserMetricsFormatter;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/account/family', name: 'portal_family_')]
final class FamilyPortalController extends AbstractController
{
    public function __construct(
        private readonly FamilyManager $familyManager,
        private readonly FamilyMembershipRepository $membershipRepository,
        private readonly FamilyInvitationRepository $invitationRepository,
        private readonly UserMetricsFormatter $metricsFormatter,
    ) {
    }

    #[Route('', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user !== null);
        $family = $user->getFamily();

        $members = $family ? $this->membershipRepository->findActiveMemberships($family) : [];
        $invitations = $family ? $this->invitationRepository->findRecentByFamily($family) : [];

        return $this->render('ui_portal/account-settings-family.html.twig', [
            'active_menu' => 'family',
            'members' => $members,
            'invitations' => $invitations,
            'family' => $family,
            'can_manage' => $user->getFamilyRole() === FamilyRole::PARENT,
            'metrics' => $this->metricsFormatter->summarize($user),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user !== null);

        if (!$this->isCsrfTokenValid('family_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = trim((string) $request->request->get('familyName'));
        if ($name === '') {
            $this->addFlash('error', 'Please provide a family name.');
            return $this->redirectToRoute('portal_family_settings');
        }

        try {
            $this->familyManager->createFamily($user, $name);
            $this->addFlash('success', 'Family created. Share your code to invite others.');
        } catch (LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('portal_family_settings');
    }

    #[Route('/join', name: 'join', methods: ['POST'])]
    public function join(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user !== null);

        if (!$this->isCsrfTokenValid('family_join', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $code = strtoupper(trim((string) $request->request->get('joinCode')));
        if ($code === '') {
            $this->addFlash('error', 'A join code is required.');
            return $this->redirectToRoute('portal_family_settings');
        }

        try {
            $this->familyManager->joinFamilyByCode($user, $code);
            $this->addFlash('success', 'Welcome aboard! Your household was updated.');
        } catch (LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('portal_family_settings');
    }

    #[Route('/code', name: 'refresh_code', methods: ['POST'])]
    public function refreshCode(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user !== null);
        $family = $user->getFamily();

        if (!$this->isCsrfTokenValid('family_code', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$family || $user->getFamilyRole() !== FamilyRole::PARENT) {
            $this->addFlash('error', 'Only family admins can refresh the join code.');
            return $this->redirectToRoute('portal_family_settings');
        }

        $this->familyManager->generateJoinCode($family);
        $this->addFlash('success', 'Join code refreshed.');

        return $this->redirectToRoute('portal_family_settings');
    }

    #[Route('/invite', name: 'invite', methods: ['POST'])]
    public function invite(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        \assert($user !== null);
        $family = $user->getFamily();

        if (!$this->isCsrfTokenValid('family_invite', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$family || $user->getFamilyRole() !== FamilyRole::PARENT) {
            $this->addFlash('error', 'Only family admins can send invitations.');
            return $this->redirectToRoute('portal_family_settings');
        }

        $email = trim((string) $request->request->get('inviteEmail'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Please supply a valid email address.');
            return $this->redirectToRoute('portal_family_settings');
        }

        try {
            $invitation = $this->familyManager->inviteByEmail($family, $user, $email);
            $this->addFlash('success', sprintf('Invitation code %s created for %s.', $invitation->getJoinCode(), $email));
        } catch (LogicException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('portal_family_settings');
    }
}
