<?php

namespace App\Controller\ModuleDocuments\FrontOffice\API;

use App\Service\AbstractEmailValidationClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents/api/email-validation')]
final class EmailValidationController extends AbstractController
{
    #[Route('/check', name: 'app_email_validation_check', methods: ['POST'])]
    public function check(
        Request $request,
        AbstractEmailValidationClient $emailValidationClient
    ): JsonResponse {
        $email = $this->readEmail($request);
        if ($email === '') {
            return $this->json([
                'ok' => false,
                'error' => 'email is required.',
            ], 400);
        }

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'ok' => false,
                'error' => 'Email format is invalid.',
            ], 400);
        }

        if (!$emailValidationClient->isEnabled()) {
            return $this->json([
                'ok' => false,
                'error' => 'ABSTRACT_EMAIL_VALIDATION_API_KEY is not configured.',
            ], 503);
        }

        try {
            $validation = $emailValidationClient->validate($email);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => 'Email validation provider is unavailable.',
                'details' => $e->getMessage(),
            ], 502);
        }

        $isValid = ($validation['is_valid'] ?? false) === true;

        return $this->json([
            'ok' => $isValid,
            'email' => $email,
            'reason' => (string) ($validation['reason'] ?? 'unknown'),
            'suggestion' => $validation['suggestion'] ?? null,
            'details' => $validation['details'] ?? [],
        ], $isValid ? 200 : 422);
    }

    private function readEmail(Request $request): string
    {
        $email = trim((string) $request->request->get('email', ''));
        if ($email !== '') {
            return $email;
        }

        $raw = (string) $request->getContent();
        if ($raw === '') {
            return '';
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['email']) || !is_string($json['email'])) {
            return '';
        }

        return trim($json['email']);
    }
}

