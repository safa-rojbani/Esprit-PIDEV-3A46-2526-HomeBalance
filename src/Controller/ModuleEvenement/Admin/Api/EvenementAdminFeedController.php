<?php

namespace App\Controller\ModuleEvenement\Admin\Api;

use App\Entity\TypeEvenement;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/portal/admin/evenements')]
class EvenementAdminFeedController extends AbstractController
{
    #[Route('/feed', name: 'admin_evenements_feed', methods: ['GET'])]
    public function feed(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        $admin = $this->requireAdminUser();
        $conn = $em->getConnection();

        $where = [];
        $params = [];

        $where[] = 'e.family_id IS NULL';
        $where[] = 'e.created_by_id = :adminId';
        $params['adminId'] = $admin->getId();

        $typeId = $request->query->get('type');
        if ($typeId !== null && $typeId !== '') {
            $where[] = 'e.type_evenement_id = :typeId';
            $params['typeId'] = (int) $typeId;
        }

        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $where[] = 'e.titre LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql = '
            SELECT
                e.id,
                e.titre,
                e.description,
                e.date_debut,
                e.date_fin,
                e.lieu,
                e.created_by_id,
                te.id AS type_id,
                te.couleur AS type_color
            FROM evenement e
            LEFT JOIN type_evenement te ON te.id = e.type_evenement_id
        ';

        $sql .= ' WHERE ' . implode(' AND ', $where);

        $sql .= ' ORDER BY e.date_debut ASC';

        $rows = $conn->fetchAllAssociative($sql, $params);

        $eventIds = [];
        foreach ($rows as $row) {
            $eventIds[] = (int) $row['id'];
        }

        $imagesByEvent = [];
        if ($eventIds) {
            $imageRows = $conn->executeQuery(
                'SELECT evenement_id, image_name FROM evenement_image WHERE evenement_id IN (?) ORDER BY id ASC',
                [$eventIds],
                [ArrayParameterType::INTEGER]
            )->fetchAllAssociative();

            foreach ($imageRows as $imageRow) {
                if (!empty($imageRow['image_name'])) {
                    $eventId = (int) $imageRow['evenement_id'];
                    $imagesByEvent[$eventId][] = '/uploads/evenements/' . $imageRow['image_name'];
                }
            }
        }

        $data = [];
        foreach ($rows as $row) {
            $start = new \DateTimeImmutable($row['date_debut']);
            $end = new \DateTimeImmutable($row['date_fin']);
            $eventId = (int) $row['id'];

            $deleteToken = $csrfTokenManager->getToken('delete' . $row['id'])->getValue();

            $data[] = [
                'id' => $eventId,
                'title' => $row['titre'],
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'color' => $row['type_color'] ?: '#2F80ED',
                'url' => $this->generateUrl('admin_evenement_show', ['id' => $row['id']]),
                'extendedProps' => [
                    'typeId' => $row['type_id'] ? (int) $row['type_id'] : null,
                    'description' => $row['description'] ?: null,
                    'location' => $row['lieu'] ?: null,
                    'imageUrls' => $imagesByEvent[$eventId] ?? [],
                    'canEdit' => true,
                    'canDelete' => true,
                    'deleteUrl' => $this->generateUrl('admin_evenement_delete', ['id' => $row['id']]),
                    'deleteToken' => $deleteToken,
                ],
            ];
        }

        return $this->json($data);
    }

    private function requireAdminUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
