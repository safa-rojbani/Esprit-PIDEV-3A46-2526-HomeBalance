<?php

namespace App\ServiceModuleCharge;

use App\Entity\CategorieAchat;
use App\Entity\Family;
use App\Repository\CategorieAchatRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategorieAchatService
{
    private EntityManagerInterface $em;
    private CategorieAchatRepository $repository;

    public function __construct(EntityManagerInterface $em, CategorieAchatRepository $repository)
    {
        $this->em = $em;
        $this->repository = $repository;
    }

    // 1️⃣ Lister toutes les catégories
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findAllByFamily(Family $family): array
    {
        return $this->repository->findBy(['family' => $family]);
    }

    // 2️⃣ Créer une catégorie
    public function create(CategorieAchat $categorie): CategorieAchat
    {
       
        // ... ajouter d'autres champs si besoin

        $this->em->persist($categorie);
        $this->em->flush();

        return $categorie;
    }

    // 3️⃣ Modifier une catégorie
    public function update(CategorieAchat $categorie, array $data): CategorieAchat
    {
        $categorie->setNom($data['nom'] ?? $categorie->getNom());
        // ... autres champs

        $this->em->flush();
        return $categorie;
    }

    // 4️⃣ Supprimer une catégorie
    public function delete(CategorieAchat $categorie): void
    {
        $this->em->remove($categorie);
        $this->em->flush();
    }

    // 5️⃣ (Optionnel) Autres fonctions métier
    public function findByName(string $name): ?CategorieAchat
    {
        return $this->repository->findOneBy(['nom' => $name]);
    }
}
