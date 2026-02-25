<?php

namespace App\Repository;

use App\Entity\DocumentActivityLog;
use App\Entity\Family;
use App\Enum\DocumentActivityEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentActivityLog>
 */
class DocumentActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentActivityLog::class);
    }

    /**
     * @return array{
     *   totalEvents:int,
     *   uploads:int,
     *   views:int,
     *   downloads:int,
     *   sharesTotal:int,
     *   sharesEmail:int,
     *   sharesWhatsapp:int,
     *   shareBlocks:int,
     *   uniqueActors:int
     * }
     */
    public function getOverview(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('log')
            ->select('COUNT(log.id) AS totalEvents')
            ->addSelect('SUM(CASE WHEN log.eventType = :uploaded THEN 1 ELSE 0 END) AS uploads')
            ->addSelect('SUM(CASE WHEN log.eventType = :viewed THEN 1 ELSE 0 END) AS views')
            ->addSelect('SUM(CASE WHEN log.eventType = :downloaded THEN 1 ELSE 0 END) AS downloads')
            ->addSelect('SUM(CASE WHEN log.eventType = :shared THEN 1 ELSE 0 END) AS sharesTotal')
            ->addSelect('SUM(CASE WHEN log.eventType = :shared AND log.channel = :email THEN 1 ELSE 0 END) AS sharesEmail')
            ->addSelect('SUM(CASE WHEN log.eventType = :shared AND log.channel = :whatsapp THEN 1 ELSE 0 END) AS sharesWhatsapp')
            ->addSelect('SUM(CASE WHEN log.eventType = :shareBlocked THEN 1 ELSE 0 END) AS shareBlocks')
            ->addSelect('COUNT(DISTINCT log.user) AS uniqueActors')
            ->andWhere('log.family = :family')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('uploaded', DocumentActivityEvent::DOCUMENT_UPLOADED->value)
            ->setParameter('viewed', DocumentActivityEvent::DOCUMENT_VIEWED->value)
            ->setParameter('downloaded', DocumentActivityEvent::DOCUMENT_DOWNLOADED->value)
            ->setParameter('shared', DocumentActivityEvent::DOCUMENT_SHARED->value)
            ->setParameter('shareBlocked', DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED->value)
            ->setParameter('email', 'email')
            ->setParameter('whatsapp', 'whatsapp')
            ->getQuery()
            ->getOneOrNullResult();

        if (!is_array($row)) {
            return [
                'totalEvents' => 0,
                'uploads' => 0,
                'views' => 0,
                'downloads' => 0,
                'sharesTotal' => 0,
                'sharesEmail' => 0,
                'sharesWhatsapp' => 0,
                'shareBlocks' => 0,
                'uniqueActors' => 0,
            ];
        }

        return [
            'totalEvents' => (int) ($row['totalEvents'] ?? 0),
            'uploads' => (int) ($row['uploads'] ?? 0),
            'views' => (int) ($row['views'] ?? 0),
            'downloads' => (int) ($row['downloads'] ?? 0),
            'sharesTotal' => (int) ($row['sharesTotal'] ?? 0),
            'sharesEmail' => (int) ($row['sharesEmail'] ?? 0),
            'sharesWhatsapp' => (int) ($row['sharesWhatsapp'] ?? 0),
            'shareBlocks' => (int) ($row['shareBlocks'] ?? 0),
            'uniqueActors' => (int) ($row['uniqueActors'] ?? 0),
        ];
    }

    /**
     * @return list<array{documentId:int, documentName:string, shareCount:int}>
     */
    public function getTopSharedDocuments(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select('IDENTITY(log.document) AS documentId')
            ->addSelect('MAX(doc.fileName) AS documentName')
            ->addSelect('COUNT(log.id) AS shareCount')
            ->join('log.document', 'doc')
            ->andWhere('log.family = :family')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->andWhere('log.eventType = :shared')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('shared', DocumentActivityEvent::DOCUMENT_SHARED->value)
            ->groupBy('log.document')
            ->orderBy('shareCount', 'DESC')
            ->addOrderBy('documentId', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $documentId = (int) ($row['documentId'] ?? 0);
            if ($documentId <= 0) {
                continue;
            }

            $result[] = [
                'documentId' => $documentId,
                'documentName' => (string) ($row['documentName'] ?? 'Document #' . $documentId),
                'shareCount' => (int) ($row['shareCount'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    public function getHourlyActivity(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select('log.createdAt AS createdAt')
            ->andWhere('log.family = :family')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = 0;
        }

        foreach ($rows as $row) {
            $createdAt = $row['createdAt'] ?? null;
            if (!$createdAt instanceof \DateTimeInterface) {
                continue;
            }

            $hour = (int) $createdAt->format('G');
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        }

        return $hours;
    }

    public function countEvent(
        Family $family,
        DocumentActivityEvent $event,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?string $channel = null
    ): int {
        $qb = $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->andWhere('log.family = :family')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->andWhere('log.eventType = :event')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('event', $event->value);

        if ($channel !== null && trim($channel) !== '') {
            $qb->andWhere('log.channel = :channel')
                ->setParameter('channel', strtolower(trim($channel)));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<array{day:string, shares:int}>
     */
    public function getDailyShares(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select('log.createdAt AS createdAt')
            ->andWhere('log.family = :family')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->andWhere('log.eventType = :shared')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('shared', DocumentActivityEvent::DOCUMENT_SHARED->value)
            ->getQuery()
            ->getArrayResult();

        $days = [];
        foreach ($rows as $row) {
            $createdAt = $row['createdAt'] ?? null;
            if (!$createdAt instanceof \DateTimeInterface) {
                continue;
            }

            $key = $createdAt->format('Y-m-d');
            $days[$key] = ($days[$key] ?? 0) + 1;
        }

        ksort($days);

        $result = [];
        foreach ($days as $day => $count) {
            $result[] = [
                'day' => $day,
                'shares' => $count,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{
     *   familyId:int,
     *   totalEvents:int,
     *   uploads:int,
     *   views:int,
     *   downloads:int,
     *   sharesEmail:int,
     *   sharesWhatsapp:int,
     *   shareBlocks:int
     * }>
     */
    public function getDailyFamilyAggregates(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('log')
            ->select('IDENTITY(log.family) AS familyId')
            ->addSelect('COUNT(log.id) AS totalEvents')
            ->addSelect('SUM(CASE WHEN log.eventType = :uploaded THEN 1 ELSE 0 END) AS uploads')
            ->addSelect('SUM(CASE WHEN log.eventType = :viewed THEN 1 ELSE 0 END) AS views')
            ->addSelect('SUM(CASE WHEN log.eventType = :downloaded THEN 1 ELSE 0 END) AS downloads')
            ->addSelect('SUM(CASE WHEN log.eventType = :shared AND log.channel = :email THEN 1 ELSE 0 END) AS sharesEmail')
            ->addSelect('SUM(CASE WHEN log.eventType = :shared AND log.channel = :whatsapp THEN 1 ELSE 0 END) AS sharesWhatsapp')
            ->addSelect('SUM(CASE WHEN log.eventType = :shareBlocked THEN 1 ELSE 0 END) AS shareBlocks')
            ->andWhere('log.createdAt >= :from')
            ->andWhere('log.createdAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('uploaded', DocumentActivityEvent::DOCUMENT_UPLOADED->value)
            ->setParameter('viewed', DocumentActivityEvent::DOCUMENT_VIEWED->value)
            ->setParameter('downloaded', DocumentActivityEvent::DOCUMENT_DOWNLOADED->value)
            ->setParameter('shared', DocumentActivityEvent::DOCUMENT_SHARED->value)
            ->setParameter('shareBlocked', DocumentActivityEvent::DOCUMENT_SHARE_BLOCKED->value)
            ->setParameter('email', 'email')
            ->setParameter('whatsapp', 'whatsapp')
            ->groupBy('log.family')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $familyId = (int) ($row['familyId'] ?? 0);
            if ($familyId <= 0) {
                continue;
            }

            $result[] = [
                'familyId' => $familyId,
                'totalEvents' => (int) ($row['totalEvents'] ?? 0),
                'uploads' => (int) ($row['uploads'] ?? 0),
                'views' => (int) ($row['views'] ?? 0),
                'downloads' => (int) ($row['downloads'] ?? 0),
                'sharesEmail' => (int) ($row['sharesEmail'] ?? 0),
                'sharesWhatsapp' => (int) ($row['sharesWhatsapp'] ?? 0),
                'shareBlocks' => (int) ($row['shareBlocks'] ?? 0),
            ];
        }

        return $result;
    }
}

