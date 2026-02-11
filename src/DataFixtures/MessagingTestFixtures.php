<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Family;
use App\Enum\UserStatus;
use App\Enum\SystemRole;
use App\Enum\FamilyRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MessagingTestFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create a test family (without createdBy initially)
        $family = new Family();
        $family->setName('Test Family');
        $family->setCreatedAt(new \DateTimeImmutable());

        // Create Parent User
        $parent = new User();
        $parent->setEmail('parent@test.com');
        $parent->setFirstName('John');
        $parent->setLastName('Doe');
        $parent->setBirthDate(new \DateTime('1980-01-01'));
        $parent->setLocale('en');
        $parent->setStatus(UserStatus::ACTIVE);
        $parent->setSystemRole(SystemRole::CUSTOMER);
        $parent->setFamilyRole(FamilyRole::PARENT);
        $parent->setFamily($family);
        $parent->setPassword($this->passwordHasher->hashPassword($parent, 'password'));

        // Now set the family creator
        $family->setCreatedBy($parent);

        $manager->persist($family);
        $manager->persist($parent);

        // Create Child User 1
        $child1 = new User();
        $child1->setEmail('child1@test.com');
        $child1->setFirstName('Alice');
        $child1->setLastName('Doe');
        $child1->setBirthDate(new \DateTime('2010-05-15'));
        $child1->setLocale('en');
        $child1->setStatus(UserStatus::ACTIVE);
        $child1->setSystemRole(SystemRole::CUSTOMER);
        $child1->setFamilyRole(FamilyRole::CHILD);
        $child1->setFamily($family);
        $child1->setPassword($this->passwordHasher->hashPassword($child1, 'password'));
        $manager->persist($child1);

        // Create Child User 2
        $child2 = new User();
        $child2->setEmail('child2@test.com');
        $child2->setFirstName('Bob');
        $child2->setLastName('Doe');
        $child2->setBirthDate(new \DateTime('2012-08-20'));
        $child2->setLocale('en');
        $child2->setStatus(UserStatus::ACTIVE);
        $child2->setSystemRole(SystemRole::CUSTOMER);
        $child2->setFamilyRole(FamilyRole::CHILD);
        $child2->setFamily($family);
        $child2->setPassword($this->passwordHasher->hashPassword($child2, 'password'));
        $manager->persist($child2);

        // Create another Parent
        $parent2 = new User();
        $parent2->setEmail('parent2@test.com');
        $parent2->setFirstName('Jane');
        $parent2->setLastName('Doe');
        $parent2->setBirthDate(new \DateTime('1982-03-10'));
        $parent2->setLocale('en');
        $parent2->setStatus(UserStatus::ACTIVE);
        $parent2->setSystemRole(SystemRole::CUSTOMER);
        $parent2->setFamilyRole(FamilyRole::PARENT);
        $parent2->setFamily($family);
        $parent2->setPassword($this->passwordHasher->hashPassword($parent2, 'password'));
        $manager->persist($parent2);

        $manager->flush();
    }
}
