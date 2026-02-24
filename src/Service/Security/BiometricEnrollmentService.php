<?php

namespace App\Service\Security;

use App\Entity\AdminBiometricProfile;
use App\Entity\User;
use App\Repository\AdminBiometricProfileRepository;
use App\Service\External\FacePlusPlusClient;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BiometricEnrollmentService
{
    public function __construct(
        private readonly FacePlusPlusClient $facePlusPlusClient,
        private readonly TokenCipherService $tokenCipherService,
        private readonly AdminBiometricProfileRepository $profileRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function enroll(User $actor, UploadedFile $selfie): AdminBiometricProfile
    {
        $faceToken = $this->facePlusPlusClient->enrollReference($selfie);
        $encrypted = $this->tokenCipherService->encrypt($faceToken);

        $profile = $this->profileRepository->findOneBy(['user' => $actor]);
        if (!$profile instanceof AdminBiometricProfile) {
            $profile = (new AdminBiometricProfile())->setUser($actor);
            $this->entityManager->persist($profile);
        }

        $profile
            ->setProvider('luxand')
            ->setReferenceFaceTokenEncrypted($encrypted)
            ->setEnabled(true)
            ->setConsentAt(new DateTimeImmutable())
            ->touch();

        $this->entityManager->flush();

        return $profile;
    }
}
