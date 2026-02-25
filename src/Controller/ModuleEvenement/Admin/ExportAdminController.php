<?php

namespace App\Controller\ModuleEvenement\Admin;

use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Entity\User;

#[Route('/admin/export')]
class ExportAdminController extends AbstractController
{
    #[Route('/csv', name: 'admin_export_csv', methods: ['GET'])]
    public function exportCsv(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $conn = $entityManager->getConnection();
        $rows = $conn->fetchAllAssociative('
            SELECT
                e.id,
                e.titre,
                t.nom AS type_nom,
                e.date_debut,
                e.date_fin,
                e.lieu,
                f.name AS family_name,
                u.email AS created_by,
                e.date_creation
            FROM evenement e
            LEFT JOIN type_evenement t ON t.id = e.type_evenement_id
            LEFT JOIN family f ON f.id = e.family_id
            LEFT JOIN user u ON u.id = e.created_by_id
            ORDER BY e.date_debut ASC
        ');

        $csv = Writer::createFromString('');
        $csv->insertOne(['ID', 'Titre', 'Type', 'Date Debut', 'Date Fin', 'Lieu', 'Famille', 'Cree par', 'Date creation']);
        foreach ($rows as $row) {
            $csv->insertOne([
                $row['id'],
                $row['titre'],
                $row['type_nom'],
                $row['date_debut'],
                $row['date_fin'],
                $row['lieu'],
                $row['family_name'],
                $row['created_by'],
                $row['date_creation'],
            ]);
        }

        return new Response($csv->toString(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="evenements.csv"',
        ]);
    }
}
