<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AdminBiometricProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuditTrailService;
use App\Service\Security\BiometricEnrollmentService;
use App\Service\Security\BiometricVerificationService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/admin/ums/security', name: 'portal_admin_security_')]
final class AdminSecurityController extends AbstractController
{
    #[Route('/face/enroll', name: 'face_enroll', methods: ['POST'])]
    public function enrollFace(
        Request $request,
        BiometricEnrollmentService $enrollmentService,
        AdminBiometricProfileRepository $profileRepository,
        AuditTrailService $auditTrailService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }

        if (!$this->isCsrfTokenValid('face_enroll', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $existingProfile = $profileRepository->findEnabledForUser($actor);
        if ($existingProfile !== null && $existingProfile->getProvider() === 'luxand') {
            return $this->respond($request, [
                'ok' => true,
                'status' => 'already_enrolled',
            ], 'success');
        }

        $consent = filter_var($request->request->get('consent'), FILTER_VALIDATE_BOOL);
        $selfie = $request->files->get('selfie');
        if (!$consent || !$selfie instanceof UploadedFile) {
            return $this->respond($request, [
                'ok' => false,
                'error' => 'Consent and selfie are required.',
            ], 'error');
        }

        try {
            $enrollmentService->enroll($actor, $selfie);
            $auditTrailService->record($actor, 'admin.biometric.enrolled', [
                'provider' => 'luxand',
            ], $actor->getFamily());

            return $this->respond($request, [
                'ok' => true,
                'status' => 'enrolled',
            ], 'success');
        } catch (\Throwable $exception) {
            $auditTrailService->record($actor, 'admin.biometric.enroll_failed', [
                'error' => $exception->getMessage(),
            ], $actor->getFamily());

            return $this->respond($request, [
                'ok' => false,
                'error' => 'Enrollment failed: ' . $exception->getMessage(),
            ], 'error');
        }
    }

    #[Route('/face/verify', name: 'face_verify', methods: ['POST'])]
    public function verifyFace(
        Request $request,
        UserRepository $userRepository,
        BiometricVerificationService $verificationService,
        AuditTrailService $auditTrailService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }

        if (!$this->isCsrfTokenValid('face_verify', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $actionKey = trim((string) $request->request->get('action_key', ''));
        if ($actionKey === '') {
            return $this->respond($request, [
                'ok' => false,
                'error' => 'action_key is required.',
            ], 'error');
        }

        $target = null;
        $targetId = trim((string) $request->request->get('target_user_id', ''));
        if ($targetId !== '') {
            $targetUser = $userRepository->find($targetId);
            if ($targetUser instanceof User) {
                $target = $targetUser;
            }
        }

        if ($verificationService->isVerifiedForAction($request, $actionKey, $target)) {
            $auditTrailService->record($actor, 'admin.biometric.verify_skipped_already_valid', [
                'actionKey' => $actionKey,
                'targetUserId' => $target?->getId(),
            ], $actor->getFamily());

            return $this->respond($request, [
                'ok' => true,
                'status' => 'already_verified',
                'message' => 'You are already verified for this action.',
            ], 'success');
        }

        $selfie = $request->files->get('selfie');
        if (!$selfie instanceof UploadedFile) {
            return $this->respond($request, [
                'ok' => false,
                'error' => 'selfie is required.',
            ], 'error');
        }

        $result = $verificationService->verify($request, $actor, $actionKey, $selfie, $target);

        $auditTrailService->record($actor, 'admin.biometric.verify', [
            'actionKey' => $actionKey,
            'decision' => $result['decision'] ?? 'ERROR',
            'score' => $result['score'] ?? null,
            'targetUserId' => $target?->getId(),
        ], $actor->getFamily());

        if (($result['decision'] ?? '') === 'PASSED') {
            return $this->respond($request, [
                'ok' => true,
                'result' => $result,
            ], 'success');
        }

        return $this->respond($request, [
            'ok' => false,
            'result' => $result,
        ], 'error');
    }

    #[Route('/manual-fallback', name: 'manual_fallback', methods: ['POST'])]
    public function manualFallback(
        Request $request,
        UserRepository $userRepository,
        BiometricVerificationService $verificationService,
        AuditTrailService $auditTrailService,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException('Invalid actor.');
        }

        if (!$this->isCsrfTokenValid('manual_fallback', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $actionKey = trim((string) $request->request->get('action_key', ''));
        $currentPassword = (string) $request->request->get('current_password', '');
        if ($actionKey === '' || $currentPassword === '') {
            return $this->respond($request, [
                'ok' => false,
                'error' => 'action_key and current_password are required.',
            ], 'error');
        }

        if (!$passwordHasher->isPasswordValid($actor, $currentPassword)) {
            $auditTrailService->record($actor, 'admin.biometric.manual_fallback_failed', [
                'actionKey' => $actionKey,
                'reason' => 'invalid_password',
            ], $actor->getFamily());

            return $this->respond($request, [
                'ok' => false,
                'error' => 'Invalid password for manual fallback.',
            ], 'error');
        }

        $target = null;
        $targetId = trim((string) $request->request->get('target_user_id', ''));
        if ($targetId !== '') {
            $targetUser = $userRepository->find($targetId);
            if ($targetUser instanceof User) {
                $target = $targetUser;
            }
        }

        $verificationService->markVerifiedForAction($request, $actionKey, $target);
        $auditTrailService->record($actor, 'admin.biometric.manual_fallback_approved', [
            'actionKey' => $actionKey,
            'targetUserId' => $target?->getId(),
        ], $actor->getFamily());

        return $this->respond($request, [
            'ok' => true,
            'status' => 'manual_fallback_verified',
        ], 'success');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respond(Request $request, array $payload, string $flashType): Response
    {
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            $status = (($payload['ok'] ?? false) === true) ? 200 : 422;

            return new JsonResponse($payload, $status);
        }

        if (($payload['ok'] ?? false) === true) {
            $this->addFlash($flashType, (string) ($payload['message'] ?? 'Operation completed.'));
        } else {
            $this->addFlash($flashType, (string) ($payload['error'] ?? 'Operation failed.'));
        }

        $returnTo = (string) $request->request->get('return_to', '');
        if ($returnTo !== '') {
            return new RedirectResponse($returnTo);
        }

        return $this->redirectToRoute('portal_admin_console_security_ai');
    }
}
