<?php

namespace App\Service\ModuleEvenement;

use App\Entity\Evenement;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class ICalService
{
    public function generateIcs(Evenement $evenement): string
    {
        $calendar = new VCalendar();
        $event = $calendar->add('VEVENT', [
            'SUMMARY' => $evenement->getTitre(),
            'DTSTART' => $this->formatDate($evenement->getDateDebut()),
            'DTEND' => $this->formatDate($evenement->getDateFin()),
            'LOCATION' => $evenement->getLieu() ?? '',
            'DESCRIPTION' => $evenement->getDescription() ?? '',
            'UID' => $this->makeUid($evenement),
            'DTSTAMP' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z'),
        ]);

        return $calendar->serialize();
    }

    /**
     * @param Evenement[] $evenements
     */
    public function generateIcsCollection(array $evenements): string
    {
        $calendar = new VCalendar();
        foreach ($evenements as $evenement) {
            $calendar->add('VEVENT', [
                'SUMMARY' => $evenement->getTitre(),
                'DTSTART' => $this->formatDate($evenement->getDateDebut()),
                'DTEND' => $this->formatDate($evenement->getDateFin()),
                'LOCATION' => $evenement->getLieu() ?? '',
                'DESCRIPTION' => $evenement->getDescription() ?? '',
                'UID' => $this->makeUid($evenement),
                'DTSTAMP' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z'),
            ]);
        }

        return $calendar->serialize();
    }

    /**
     * @return array<int, array{title: string, description: string|null, location: string|null, start: \DateTimeImmutable|null, end: \DateTimeImmutable|null}>
     */
    public function parseIcs(string $content): array
    {
        $vcalendar = Reader::read($content);
        $events = [];

        foreach ($vcalendar->select('VEVENT') as $vevent) {
            $start = isset($vevent->DTSTART) ? $vevent->DTSTART->getDateTime() : null;
            $end = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : null;

            $events[] = [
                'title' => isset($vevent->SUMMARY) ? (string) $vevent->SUMMARY : 'Evenement',
                'description' => isset($vevent->DESCRIPTION) ? (string) $vevent->DESCRIPTION : null,
                'location' => isset($vevent->LOCATION) ? (string) $vevent->LOCATION : null,
                'start' => $this->normalizeDate($start),
                'end' => $this->normalizeDate($end),
            ];
        }

        return $events;
    }

    private function formatDate(?\DateTimeImmutable $date): string
    {
        if ($date === null) {
            return '';
        }
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function makeUid(Evenement $evenement): string
    {
        $id = $evenement->getId() ?? uniqid('evt_', true);
        return sprintf('homebalance-%s@evenements', $id);
    }

    private function normalizeDate(?\DateTimeInterface $date): ?\DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }
        if ($date instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($date);
        }

        return null;
    }
}
