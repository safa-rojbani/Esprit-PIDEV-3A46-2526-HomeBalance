<?php

namespace App\Controller\Client;

use App\Repository\TypeEvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app')]
class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function calendar(
        Request $request,
        TypeEvenementRepository $typeEvenementRepository
    ): Response
    {
        $user = $this->getUser();

        $typeId = $request->query->get('type');
        $search = trim((string) $request->query->get('q', ''));
        $selectedType = null;

        if ($typeId !== null && $typeId !== '') {
            $selectedType = $typeEvenementRepository->find($typeId);
        }

        $typesQb = $typeEvenementRepository->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC');

        if ($user !== null && $user->getFamily() !== null) {
            $typesQb->andWhere('t.family IS NULL OR t.family = :family')
                ->setParameter('family', $user->getFamily());
        }
        $types = $typesQb->getQuery()->getResult();

        return $this->render('calendar/app_calendar.html.twig', [
            'types' => $types,
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
    }
}
