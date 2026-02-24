<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AuditTrailRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExportService
{
    public function __construct(
        private readonly AuditTrailRepository $auditTrailRepository,
    ) {
    }

    public function streamUserAuditTrail(User $user): StreamedResponse
    {
        $filename = sprintf(
            'audit_%s_%s.csv',
            $user->getUsername() ?? 'user',
            (new \DateTimeImmutable())->format('Ymd_His')
        );

        $response = new StreamedResponse(function () use ($user): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'action',
                'channel',
                'ip_address',
                'user_agent',
                'created_at',
                'payload',
            ]);

            $records = $this->auditTrailRepository->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
            );

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->getId(),
                    $record->getAction(),
                    $record->getChannel(),
                    $record->getIpAddress(),
                    $record->getUserAgent(),
                    $record->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                    json_encode($record->getPayload() ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
