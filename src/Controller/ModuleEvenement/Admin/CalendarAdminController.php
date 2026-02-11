<?php

namespace App\Controller\ModuleEvenement\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class CalendarAdminController extends AbstractController
{
    #[Route('/calendar', name: 'admin_calendar')]
    public function calendar(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();

        $sql = '
            SELECT
                e.id,
                e.titre,
                e.date_debut,
                e.date_fin,
                te.couleur AS type_color
            FROM evenement e
            LEFT JOIN type_evenement te ON te.id = e.type_evenement_id
            ORDER BY e.date_debut ASC
        ';

        $rows = $conn->fetchAllAssociative($sql);
        $data = [];
        foreach ($rows as $row) {
            $start = new \DateTimeImmutable($row['date_debut']);
            $end = new \DateTimeImmutable($row['date_fin']);
            $data[] = [
                'id' => (int) $row['id'],
                'title' => $row['titre'],
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'color' => $row['type_color'] ?: '#2F80ED',
                'url' => $this->generateUrl('admin_evenement_show', ['id' => $row['id']]),
            ];
        }

        return $this->render('admin/calendar/calendar.html.twig', [
            'events' => json_encode($data),
        ]);
    }
}
