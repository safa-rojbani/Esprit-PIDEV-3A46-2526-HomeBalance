<?php

namespace App\Controller\ModuleEvenement\Client\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

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

        $where[] = 'e.family_id = :familyId';
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

        $data = [];
        foreach ($rows as $row) {
            $createdByName = trim(sprintf('%s %s', $row['first_name'] ?? '', $row['last_name'] ?? ''));
            $start = new \DateTimeImmutable($row['date_debut']);
            $end = new \DateTimeImmutable($row['date_fin']);

            $isOwner = $user !== null && $row['created_by_id'] === $user->getId();
            $deleteToken = $isOwner
                ? $csrfTokenManager->getToken('delete' . $row['id'])->getValue()
                : null;

            $data[] = [
                'id' => (int) $row['id'],
                'title' => $row['titre'],
                'start' => $start->format('c'),
                'end' => $end->format('c'),
                'color' => $row['type_color'] ?: '#2F80ED',
                'url' => $this->generateUrl('app_evenement_show', ['id' => $row['id']]),
                'extendedProps' => [
                    'typeId' => $row['type_id'] ? (int) $row['type_id'] : null,
                    'shared' => $row['created_by_id'] === null || $row['family_id'] !== null,
                    'createdByName' => $createdByName !== '' ? $createdByName : null,
                    'description' => $row['description'] ?: null,
                    'location' => $row['lieu'] ?: null,
                    'canEdit' => $isOwner,
                    'canDelete' => $isOwner,
                    'deleteUrl' => $isOwner ? $this->generateUrl('app_evenement_delete', ['id' => $row['id']]) : null,
                    'deleteToken' => $deleteToken,
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
