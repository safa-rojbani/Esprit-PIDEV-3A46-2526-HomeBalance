<?php

namespace App\MessageHandler;

use App\Entity\Document;
use App\Enum\EtatDocument;
use App\Message\PurgeTrashMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeTrashMessageHandler
{
    public function __construct(private EntityManagerInterface $em) {}

    public function __invoke(PurgeTrashMessage $message): void
    {
        $limitDate = (new \DateTimeImmutable())->modify('-30 days');

        $docs = $this->em->getRepository(Document::class)->createQueryBuilder('d')
            ->andWhere('d.etat = :etat')
            ->andWhere('d.deletedAt IS NOT NULL')
            ->andWhere('d.deletedAt <= :limit')
            ->setParameter('etat', EtatDocument::CORBEILLE->value)
            ->setParameter('limit', $limitDate)
            ->getQuery()
            ->getResult();

        foreach ($docs as $doc) {
            $doc->setEtat(EtatDocument::DELETED);
        }

        $this->em->flush();
    }
}
