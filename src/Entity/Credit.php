<?php

namespace App\Entity;

use App\Repository\CreditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'credit')]
class Credit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre du credit est obligatoire.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit etre superieur a 0.')]
    private ?string $principal = null;

    #[ORM\Column(name: 'annual_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le taux annuel est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le taux annuel doit etre positif ou nul.')]
    private ?string $annualRate = null;

    #[ORM\Column(name: 'term_months')]
    #[Assert\NotNull(message: 'La duree est obligatoire.')]
    #[Assert\Positive(message: 'La duree doit etre superieure a 0.')]
    private ?int $termMonths = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPrincipal(): ?string
    {
        return $this->principal;
    }

    public function setPrincipal(?string $principal): static
    {
        $this->principal = $principal;

        return $this;
    }

    public function getAnnualRate(): ?string
    {
        return $this->annualRate;
    }

    public function setAnnualRate(?string $annualRate): static
    {
        $this->annualRate = $annualRate;

        return $this;
    }

    public function getTermMonths(): ?int
    {
        return $this->termMonths;
    }

    public function setTermMonths(?int $termMonths): static
    {
        $this->termMonths = $termMonths;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
