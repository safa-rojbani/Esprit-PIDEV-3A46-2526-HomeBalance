<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Family;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateTestDataCommand extends Command
{
    protected static $defaultName = 'app:create-test-data';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* =========================
           1️⃣ Création du Parent
        ========================= */

       $parent = new User();
$parent->setEmail('parent@test.com');
$parent->setFirstName('Parent');
$parent->setLastName('Test');
$parent->setLocale('fr');
$parent->setStatus(UserStatus::ACTIVE);
$parent->setSystemRole(SystemRole::CUSTOMER);
$parent->setFamilyRole(FamilyRole::PARENT);
$parent->setBirthDate(new \DateTime('1985-01-01'));
$parent->setCreatedAt(new \DateTimeImmutable());

$parent->setPassword(
    $this->passwordHasher->hashPassword($parent, 'password123')
);

$this->em->persist($parent);


        /* =========================
           2️⃣ Création de la Famille
        ========================= */

        $family = new Family();
        $family->setName('Famille Test');
        $family->setCreatedAt(new \DateTimeImmutable());
        $family->setCreatedBy($parent);

        $this->em->persist($family);

        // 🔑 Lier le parent à la famille
        $parent->setFamily($family);

        /* =========================
           3️⃣ Création de l’Enfant
        ========================= */

       $child = new User();
$child->setEmail('child@test.com');
$child->setFirstName('Child');
$child->setLastName('Test');
$child->setLocale('fr');
$child->setStatus(UserStatus::ACTIVE);
$child->setSystemRole(SystemRole::CUSTOMER);
$child->setFamilyRole(FamilyRole::CHILD);
$child->setFamily($family);
$child->setBirthDate(new \DateTime('2015-01-01'));
$child->setCreatedAt(new \DateTimeImmutable());

$child->setPassword(
    $this->passwordHasher->hashPassword($child, 'password123')
);

$this->em->persist($child);

        /* =========================
           4️⃣ Création Admin plateforme (optionnel)
        ========================= */

       $admin = new User();
$admin->setEmail('admin@test.com');
$admin->setFirstName('Admin');
$admin->setLastName('Platform');
$admin->setLocale('fr');
$admin->setStatus(UserStatus::ACTIVE);
$admin->setSystemRole(SystemRole::ADMIN);
$admin->setFamilyRole(FamilyRole::SOLO);
$admin->setBirthDate(new \DateTime('1990-01-01'));
$admin->setCreatedAt(new \DateTimeImmutable());

$admin->setPassword(
    $this->passwordHasher->hashPassword($admin, 'admin123')
);

$this->em->persist($admin);


        /* =========================
           5️⃣ Sauvegarde
        ========================= */

        $this->em->flush();

        $output->writeln('✅ Données de test créées avec succès');

        return Command::SUCCESS;
    }
}
