<?php

namespace App\Service\ModuleEvenement;

use App\Entity\Evenement;
use App\Entity\User;
use App\Entity\ModuleEvenement\InvitationRsvp;
use App\Repository\InvitationRsvpRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class RsvpService
{
    private const VALID_STATUSES = [
        InvitationRsvp::STATUS_EN_ATTENTE,
        InvitationRsvp::STATUS_ACCEPTE,
        InvitationRsvp::STATUS_REFUSE,
        InvitationRsvp::STATUS_PEUT_ETRE,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvitationRsvpRepository $invitationRepository,
        private MailerInterface $mailer
    ) {}

    public function inviterMembre(Evenement $evenement, User $invitee, User $invitedBy): InvitationRsvp
    {
        $existing = $this->invitationRepository->findOneBy([
            'evenement' => $evenement,
            'invitee' => $invitee,
        ]);
        if ($existing instanceof InvitationRsvp) {
            return $existing;
        }

        $invitation = new InvitationRsvp();
        $invitation->setEvenement($evenement);
        $invitation->setInvitee($invitee);
        $invitation->setInvitedBy($invitedBy);
        $invitation->setStatut(InvitationRsvp::STATUS_EN_ATTENTE);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    public function repondre(InvitationRsvp $invitation, string $statut): void
    {
        if (!in_array($statut, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Statut RSVP invalide.');
        }

        $invitation->setStatut($statut);
        $invitation->setReponduAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * @return InvitationRsvp[]
     */
    public function getInvitationsByEvenement(Evenement $evenement): array
    {
        return $this->invitationRepository->findBy(['evenement' => $evenement], ['createdAt' => 'DESC']);
    }

    private function sendInvitationEmail(InvitationRsvp $invitation): void
    {
        $invitee = $invitation->getInvitee();
        if ($invitee === null || $invitee->getEmail() === null) {
            return;
        }

        $organizer = $invitation->getInvitedBy();
        $from = $organizer && $organizer->getEmail()
            ? new Address($organizer->getEmail(), trim(($organizer->getFirstName() ?? '') . ' ' . ($organizer->getLastName() ?? '')))
            : new Address('no-reply@homebalance.local', 'HomeBalance');

        $email = (new TemplatedEmail())
            ->from($from)
            ->to($invitee->getEmail())
            ->subject('Invitation a un evenement familial')
            ->htmlTemplate('emails/rsvp_invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'evenement' => $invitation->getEvenement(),
                'organisateur' => $organizer,
            ]);

        $this->mailer->send($email);
    }
}
