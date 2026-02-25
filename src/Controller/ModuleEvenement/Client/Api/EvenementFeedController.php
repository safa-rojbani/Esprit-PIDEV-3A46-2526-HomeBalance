<?php

namespace App\Controller\ModuleEvenement\Client\Api;

use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/portal/evenements')]
class EvenementFeedController extends AbstractController
{
    #[Route('/feed', name: 'app_evenements_feed', methods: ['GET'])]
    public function feed(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager,
        ActiveFamilyResolver $familyResolver
    ): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([]);
        }

        $family = $this->resolveFamily($familyResolver);
        $conn = $em->getConnection();

        $where = [];
        $params = [];

        $where[] = '(e.family_id = :familyId OR e.family_id IS NULL)';
        $params['familyId'] = $family->getId();

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
                e.share_with_family,
                e.created_by_id,
                e.family_id,
                te.id AS type_id,
                te.couleur AS type_color,
                u.first_name,
                u.last_name
            FROM evenement e
            LEFT JOIN type_evenement te ON te.id = e.type_evenement_id
            LEFT JOIN user u ON u.id = e.created_by_id
        ';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

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

        $rsvpCounts = [];
        if ($eventIds) {
            $rsvpRows = $conn->executeQuery(
                'SELECT evenement_id, statut, COUNT(*) AS total FROM invitation_rsvp WHERE evenement_id IN (?) GROUP BY evenement_id, statut',
                [$eventIds],
                [ArrayParameterType::INTEGER]
            )->fetchAllAssociative();

            foreach ($rsvpRows as $rsvpRow) {
                $eventId = (int) $rsvpRow['evenement_id'];
                $status = (string) $rsvpRow['statut'];
                $rsvpCounts[$eventId][$status] = (int) $rsvpRow['total'];
            }
        }

        $data = [];
        foreach ($rows as $row) {
            $createdByName = trim(sprintf('%s %s', $row['first_name'] ?? '', $row['last_name'] ?? ''));
            $start = new \DateTimeImmutable($row['date_debut']);
            $end = new \DateTimeImmutable($row['date_fin']);

            $isOwner = $row['created_by_id'] === $user->getId();

            $deleteToken = $isOwner
                ? $csrfTokenManager->getToken('delete' . $row['id'])->getValue()
                : null;

            $eventId = (int) $row['id'];
            $counts = $rsvpCounts[$eventId] ?? [];

            $data[] = [
                'id' => $eventId,
                'title' => $row['titre'],
                'start' => $start->format('Y-m-d\\TH:i:s'),
                'end' => $end->format('Y-m-d\\TH:i:s'),
                'color' => $row['type_color'] ?: '#2F80ED',
                'url' => $this->generateUrl('app_evenement_show', ['id' => $row['id']]),
                'extendedProps' => [
                    'typeId' => $row['type_id'] ? (int) $row['type_id'] : null,
                    'shared' => $row['family_id'] === null || (int) $row['family_id'] === $family->getId(),
                    'createdByName' => $createdByName !== '' ? $createdByName : null,
                    'description' => $row['description'] ?: null,
                    'location' => $row['lieu'] ?: null,
                    'imageUrls' => $imagesByEvent[$eventId] ?? [],
                    'canEdit' => $isOwner,
                    'canDelete' => $isOwner,
                    'deleteUrl' => $isOwner ? $this->generateUrl('app_evenement_delete', ['id' => $row['id']]) : null,
                    'deleteToken' => $deleteToken,
                    'rsvpAcceptes' => $counts['accepte'] ?? 0,
                    'rsvpRefuses' => $counts['refuse'] ?? 0,
                    'rsvpPeutEtre' => $counts['peut_etre'] ?? 0,
                    'rsvpEnAttente' => $counts['en_attente'] ?? 0,
                    'canInvite' => $isOwner && (bool) $row['share_with_family'],
                ],
            ];
        }

        return $this->json($data);
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
