<?php

namespace App\Entity;

use App\Repository\DefaultGalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DefaultGalleryRepository::class)]
#[UniqueEntity(
    fields: ['name'],
    message: 'Un template par defaut avec ce nom existe deja.'
)]
class DefaultGallery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du template est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom du template doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le nom du template ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'La description du template est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'defaultGallery', targetEntity: Gallery::class)]
private Collection $galleries;

public function __construct()
{
    $this->galleries = new ArrayCollection();
}

public function getGalleries(): Collection
{
    return $this->galleries;
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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
}
