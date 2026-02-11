<?php

namespace App\Controller\ModuleEvenement\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/admin/evenements')]
class CalendarAdminController extends AbstractController
{
    #[Route('/calendar', name: 'admin_calendar')]
    public function calendar(EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
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
            WHERE e.family_id = :familyId
            ORDER BY e.date_debut ASC
        ';

        $rows = $conn->fetchAllAssociative($sql, ['familyId' => $family->getId()]);
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
}
