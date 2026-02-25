<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\DocumentActivityEvent;
use App\Repository\DocumentShareRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\AbstractEmailValidationClient;
use App\Service\DocumentActivityTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DocumentShareController extends AbstractController
{
    private const SHARE_LIMIT_PER_HOUR = 10;

    #[Route('/portal/documents/{id}/share/{galleryId}', name: 'app_document_share_create', methods: ['POST'], requirements: ['id' => '\d+', 'galleryId' => '\d+'], defaults: ['galleryId' => null])]
    public function create(
        Request $request,
        Document $document,
        ?int $galleryId,
        EntityManagerInterface $entityManager,
        DocumentShareRepository $documentShareRepository,
        ActiveFamilyResolver $familyResolver,
        AbstractEmailValidationClient $emailValidationClient,
        DocumentActivityTracker $documentActivityTracker,
        UrlGeneratorInterface $urlGenerator,
        MailerInterface $mailer,
        ParameterBagInterface $parameterBag,
    ): Response {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $document->getFamily());
        $actor = $this->resolveActor();

        if (!$this->isCsrfTokenValid('share_document' . $document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $expiresAt = $this->resolveExpirationFromRequest($request);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        $shareChannel = strtolower(trim((string) $request->request->get('share_channel', 'whatsapp')));
        if (!\in_array($shareChannel, ['email', 'whatsapp'], true)) {
            $this->addFlash('danger', 'Canal de partage invalide.');

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        $emailTarget = trim((string) $request->request->get('share_email', ''));
        if ($shareChannel === 'email' && $emailTarget === '') {
            $this->addFlash('danger', 'Veuillez renseigner une adresse email.');

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        if ($emailTarget !== '' && !filter_var($emailTarget, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse email invalide.');

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        $now = new \DateTimeImmutable();
        $windowStart = $now->modify('-1 hour');
        $shareCountLastHour = $documentShareRepository->countSharesByUserSince($actor, $windowStart);
        if ($shareCountLastHour >= self::SHARE_LIMIT_PER_HOUR) {
            $oldestShare = $documentShareRepository->findOldestShareByUserSince($actor, $windowStart);
            $retryAt = $oldestShare?->getCreatedAt()?->modify('+1 hour');
            $retryLabel = $retryAt?->format('d/m/Y H:i') ?? 'dans quelques minutes';

            $documentActivityTracker->track(
                $family,
                $actor,
                $document,
                DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED,
                $shareChannel,
                [
                    'reason' => 'rate_limit',
                    'shareCountLastHour' => $shareCountLastHour,
                ]
            );
            $entityManager->flush();

            $this->addFlash(
                'danger',
                sprintf(
                    'Limite atteinte: maximum %d partages (email + WhatsApp) par heure. Reessayez apres %s.',
                    self::SHARE_LIMIT_PER_HOUR,
                    $retryLabel
                )
            );

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        if ($shareChannel === 'email' && $emailTarget !== '') {
            if (!$emailValidationClient->isEnabled()) {
                $documentActivityTracker->track(
                    $family,
                    $actor,
                    $document,
                    DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED,
                    $shareChannel,
                    [
                        'reason' => 'email_validation_not_configured',
                    ]
                );
                $entityManager->flush();

                $this->addFlash('danger', 'Validation email externe non configuree. Envoi bloque.');

                return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
            }

            try {
                $validation = $emailValidationClient->validate($emailTarget);
            } catch (\Throwable) {
                $documentActivityTracker->track(
                    $family,
                    $actor,
                    $document,
                    DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED,
                    $shareChannel,
                    [
                        'reason' => 'email_validation_unavailable',
                    ]
                );
                $entityManager->flush();

                $this->addFlash('danger', 'Validation email externe indisponible. Envoi bloque.');

                return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
            }

            if (($validation['is_valid'] ?? false) !== true) {
                $reason = (string) ($validation['reason'] ?? 'invalid');
                $message = match ($reason) {
                    'invalid_format' => 'Adresse email refusee: format invalide.',
                    'disposable_email' => 'Adresse email refusee: email jetable non autorise.',
                    'undeliverable' => 'Adresse email refusee: boite non distribuable.',
                    'mx_smtp_failed' => 'Adresse email refusee: domaine non joignable.',
                    default => 'Adresse email refusee par la validation externe.',
                };
                $suggestion = trim((string) ($validation['suggestion'] ?? ''));
                if ($suggestion !== '') {
                    $message .= ' Suggestion: ' . $suggestion;
                }

                $documentActivityTracker->track(
                    $family,
                    $actor,
                    $document,
                    DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED,
                    $shareChannel,
                    [
                        'reason' => $reason,
                    ]
                );
                $entityManager->flush();

                $this->addFlash('danger', $message);

                return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
            }
        }

        $token = $this->generateShareToken();
        $tokenHash = hash('sha256', $token);

        $share = (new DocumentShare())
            ->setDocument($document)
            ->setFamily($family)
            ->setSharedBy($actor)
            ->setTokenHash($tokenHash)
            ->setRecipientEmail($shareChannel === 'email' && $emailTarget !== '' ? $emailTarget : null)
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt);

        $entityManager->persist($share);
        $entityManager->flush();
        $documentActivityTracker->track(
            $family,
            $actor,
            $document,
            DocumentActivityEvent::DOCUMENT_SHARED,
            $shareChannel,
            [
                'shareId' => $share->getId(),
                'expiresAt' => $expiresAt->format(\DATE_ATOM),
            ]
        );
        $entityManager->flush();

        $publicPath = $urlGenerator->generate('app_document_share_public', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_PATH);
        $publicBaseUrl = rtrim((string) $parameterBag->get('app.public_base_url'), '/');
        $publicUrl = $publicBaseUrl . $publicPath;

        $emailSent = false;
        if ($shareChannel === 'email' && $emailTarget !== '') {
            $from = (string) $parameterBag->get('app.notification_from');
            $expiresLabel = $expiresAt->format('d/m/Y H:i');

            $email = (new Email())
                ->from(new Address($from, 'HomeBalance'))
                ->to($emailTarget)
                ->subject('Lien partage document HomeBalance')
                ->text(
                    "Un document a ete partage avec vous.\n\n" .
                    'Document: ' . ((string) $document->getFileName()) . "\n" .
                    'Lien securise: ' . $publicUrl . "\n" .
                    'Expiration: ' . $expiresLabel . "\n"
                );

            try {
                $mailer->send($email);
                $emailSent = true;
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Lien genere, mais envoi email impossible pour le moment.');
            }
        }

        $shareText = sprintf(
            'Document partage HomeBalance: %s (expire le %s)',
            $publicUrl,
            $expiresAt->format('d/m/Y H:i')
        );
        if ($shareChannel === 'email') {
            if ($emailSent) {
                $this->addFlash('success', 'Le document a ete partage par email.');
            }

            return $this->redirectToRoute('app_document_show', $this->buildShowRouteParams($document->getId(), $galleryId));
        }

        $this->addFlash('success', 'Le document a ete partage par WhatsApp.');
        return $this->redirect('https://wa.me/?text=' . rawurlencode($shareText));
    }

    #[Route('/share/{token}', name: 'app_document_share_public', methods: ['GET'])]
    public function public(
        string $token,
        DocumentShareRepository $documentShareRepository,
        DocumentActivityTracker $documentActivityTracker,
        EntityManagerInterface $entityManager
    ): Response
    {
        $share = $documentShareRepository->findOneByRawToken($token);
        if (!$share instanceof DocumentShare) {
            return $this->renderInvalidShare(Response::HTTP_NOT_FOUND);
        }

        $now = new \DateTimeImmutable();
        if ($share->getRevokedAt() !== null || $share->getExpiresAt() === null || $share->getExpiresAt() <= $now) {
            return $this->renderInvalidShare(Response::HTTP_GONE);
        }

        $document = $share->getDocument();
        if (!$document instanceof Document) {
            return $this->renderInvalidShare(Response::HTTP_NOT_FOUND);
        }

        $shareFamilyId = $share->getFamily()?->getId();
        $documentFamilyId = $document->getFamily()?->getId();
        if ($shareFamilyId === null || $documentFamilyId === null || $shareFamilyId !== $documentFamilyId) {
            return $this->renderInvalidShare(Response::HTTP_NOT_FOUND);
        }

        $relativePath = $document->getFilePath();
        if ($relativePath === null || $relativePath === '') {
            return $this->renderInvalidShare(Response::HTTP_NOT_FOUND);
        }

        $absolutePath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');
        if (!is_file($absolutePath)) {
            return $this->renderInvalidShare(Response::HTTP_NOT_FOUND);
        }

        $downloadName = $this->resolveDownloadName($document, $absolutePath);

        $documentActivityTracker->track(
            $share->getFamily(),
            null,
            $document,
            DocumentActivityEvent::DOCUMENT_DOWNLOADED,
            null,
            [
                'source' => 'public_share_link',
                'shareId' => $share->getId(),
            ]
        );
        $entityManager->flush();

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
        $response->headers->set('Content-Type', $document->getFileType() ?: 'application/octet-stream');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->setPrivate();
        $response->setMaxAge(0);

        return $response;
    }

    private function resolveExpirationFromRequest(Request $request): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        $max = $now->modify('+7 days');
        $raw = trim((string) $request->request->get('share_expires_at', ''));
        if ($raw === '') {
            throw new \InvalidArgumentException('Veuillez choisir une date/heure d expiration.');
        }

        $expiresAt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
        if (!$expiresAt) {
            throw new \InvalidArgumentException('Date/heure d expiration invalide.');
        }

        if ($expiresAt <= $now) {
            throw new \InvalidArgumentException('La date d expiration doit etre dans le futur.');
        }

        if ($expiresAt > $max) {
            throw new \InvalidArgumentException('La duree maximale de partage est de 7 jours.');
        }

        return $expiresAt;
    }

    /**
     * @return array{id: int, galleryId?: int}
     */
    private function buildShowRouteParams(?int $documentId, ?int $galleryId): array
    {
        if ($documentId === null) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $params = ['id' => $documentId];
        if ($galleryId !== null) {
            $params['galleryId'] = $galleryId;
        }

        return $params;
    }

    private function generateShareToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function resolveDownloadName(Document $document, string $absolutePath): string
    {
        $name = trim((string) $document->getFileName());
        if ($name === '') {
            $name = 'document';
        }

        $safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
        $safeName = $safe !== null && $safe !== '' ? $safe : 'document';

        $extension = strtolower((string) pathinfo($absolutePath, \PATHINFO_EXTENSION));
        if ($extension !== '' && !str_ends_with(strtolower($safeName), '.' . $extension)) {
            $safeName .= '.' . $extension;
        }

        return $safeName;
    }

    private function isLocalhostUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        return $host === 'localhost' || $host === '127.0.0.1';
    }

    private function renderInvalidShare(int $status): Response
    {
        return $this->render('ModuleDocuments/Public/share_invalid.html.twig', [], new Response('', $status));
    }

    private function resolveFamily(ActiveFamilyResolver $familyResolver): Family
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return $family;
    }

    private function resolveActor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
