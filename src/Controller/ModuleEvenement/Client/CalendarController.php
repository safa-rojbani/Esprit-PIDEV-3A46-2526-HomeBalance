<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Repository\TypeEvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/evenements')]
class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function calendar(
        Request $request,
        TypeEvenementRepository $typeEvenementRepository,
        ActiveFamilyResolver $familyResolver
    ): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->find($typeId);
        }

        $typesQb = $typeEvenementRepository->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC');

        $typesQb->andWhere('t.family = :family')
            ->setParameter('family', $family);
        $types = $typesQb->getQuery()->getResult();

        return $this->render('calendar/app_calendar.html.twig', [
            'types' => $types,
            'selectedType' => $selectedType,
            'search' => $search,
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
