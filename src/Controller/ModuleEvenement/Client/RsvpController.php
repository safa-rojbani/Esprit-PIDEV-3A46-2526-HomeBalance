<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\Evenement;
use App\Entity\FamilyMembership;
use App\Entity\ModuleEvenement\InvitationRsvp;
use App\Entity\User;
use App\Repository\InvitationRsvpRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\ModuleEvenement\RsvpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/rsvp')]
class RsvpController extends AbstractController
{
    #[Route('/inviter/{id}', name: 'rsvp_inviter', methods: ['GET', 'POST'])]
    public function inviter(
        Request $request,
        Evenement $evenement,
        RsvpService $rsvpService,
        ActiveFamilyResolver $familyResolver,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $evenement->getCreatedBy()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$evenement->isShareWithFamily()) {
            $this->addFlash('error', 'L\u2019\u00e9v\u00e9nement n\u2019est pas partag\u00e9 avec la famille.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        $memberships = $entityManager->getRepository(FamilyMembership::class)
            ->findBy(['family' => $family, 'leftAt' => null]);
        $members = [];
        foreach ($memberships as $membership) {
            $member = $membership->getUser();
            if ($member->getId() !== $user->getId()) {
                $members[] = $member;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rsvp_inviter' . $evenement->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('rsvp_inviter', ['id' => $evenement->getId()]);
            }

            $selected = $request->request->all('invitees');
            $count = 0;
            foreach ($members as $member) {
                if (in_array($member->getId(), $selected, true)) {
                    $rsvpService->inviterMembre($evenement, $member, $user);
                    $count++;
                }
            }

            $this->addFlash('success', sprintf('%d invitation(s) envoy\u00e9e(s).', $count));
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('ModuleEvenement/Client/rsvp/inviter.html.twig', [
            'evenement' => $evenement,
            'members' => $members,
        ]);
    }

    #[Route('/repondre/{token}/{statut}', name: 'rsvp_repondre', methods: ['GET'])]
    public function repondre(
        string $token,
        string $statut,
        InvitationRsvpRepository $invitationRepository,
        RsvpService $rsvpService
    ): Response {
        $invitation = $invitationRepository->findOneBy(['token' => $token]);
        if (!$invitation instanceof InvitationRsvp) {
            throw $this->createNotFoundException();
        }

        $rsvpService->repondre($invitation, $statut);

        return $this->render('ModuleEvenement/Client/rsvp/repondre.html.twig', [
            'invitation' => $invitation,
        ]);
    }

    #[Route('/historique/{id}', name: 'rsvp_historique', methods: ['GET'])]
    public function historique(
        Evenement $evenement,
        RsvpService $rsvpService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $evenement->getCreatedBy()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('ModuleEvenement/Client/rsvp/historique.html.twig', [
            'evenement' => $evenement,
            'invitations' => $rsvpService->getInvitationsByEvenement($evenement),
        ]);
    }
}
