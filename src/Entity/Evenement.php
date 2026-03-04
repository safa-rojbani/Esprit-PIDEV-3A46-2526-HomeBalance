<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ModuleEvenement\InvitationRsvp;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.'
    )]
    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne doit pas depasser {{ limit }} caracteres.'
    )]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Assert\NotNull(message: 'La date de debut est obligatoire.')]
    #[ORM\Column]
    private ?\DateTimeImmutable $dateDebut = null;

    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[ORM\Column]
    private ?\DateTimeImmutable $dateFin = null;

    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le lieu doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le lieu ne doit pas depasser {{ limit }} caracteres.'
    )]
    #[ORM\Column(length: 255)]
    private ?string $lieu = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateModification = null;

    #[ORM\ManyToOne]
    private ?Family $family = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le type d'evenement est obligatoire.")]
    private ?TypeEvenement $TypeEvenement = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $shareWithFamily = false;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: EvenementImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: InvitationRsvp::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invitations;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateModification(): ?\DateTimeImmutable
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTimeImmutable $dateModification): static
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getTypeEvenement(): ?TypeEvenement
    {
        return $this->TypeEvenement;
    }

    public function setTypeEvenement(?TypeEvenement $TypeEvenement): static
    {
        $this->TypeEvenement = $TypeEvenement;
        return $this;
    }

    public function isShareWithFamily(): bool
    {
        return $this->shareWithFamily;
    }

    public function setShareWithFamily(bool $shareWithFamily): static
    {
        $this->shareWithFamily = $shareWithFamily;
        return $this;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(EvenementImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setEvenement($this);
        }

        return $this;
    }

    public function removeImage(EvenementImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getEvenement() === $this) {
                $image->setEvenement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InvitationRsvp>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(InvitationRsvp $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setEvenement($this);
        }

        return $this;
    }

    public function removeInvitation(InvitationRsvp $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            if ($invitation->getEvenement() === $this) {
                $invitation->setEvenement(null);
            }
        }

        return $this;
    }

    public function getNombreAcceptes(): int
    {
        return $this->countInvitationsByStatus(InvitationRsvp::STATUS_ACCEPTE);
    }

    public function getNombreRefuses(): int
    {
        return $this->countInvitationsByStatus(InvitationRsvp::STATUS_REFUSE);
    }

    public function getNombrePeutEtre(): int
    {
        return $this->countInvitationsByStatus(InvitationRsvp::STATUS_PEUT_ETRE);
    }

    public function getNombreEnAttente(): int
    {
        return $this->countInvitationsByStatus(InvitationRsvp::STATUS_EN_ATTENTE);
    }

    private function countInvitationsByStatus(string $status): int
    {
        $count = 0;
        foreach ($this->invitations as $invitation) {
            if ($invitation->getStatut() === $status) {
                $count++;
            }
        }
        return $count;
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->dateDebut !== null && $this->dateFin !== null && $this->dateFin <= $this->dateDebut) {
            $context->buildViolation('La date de fin doit etre apres la date de debut.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}
