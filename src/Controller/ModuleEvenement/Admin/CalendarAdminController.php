<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Entity\TypeEvenement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/portal/admin/evenements')]
class CalendarAdminController extends AbstractController
{
    #[Route('/calendar', name: 'admin_calendar')]
    public function calendar(Request $request, EntityManagerInterface $em): Response
    {
        $this->requireAdminUser();
        $typeRepo = $em->getRepository(TypeEvenement::class);
        $types = $typeRepo->findBy(['family' => null]);

        $selectedType = null;
        $typeId = $request->query->get('type');
        if ($typeId !== null && $typeId !== '') {
            $candidate = $typeRepo->find((int) $typeId);
            if ($candidate instanceof TypeEvenement && $candidate->getFamily() === null) {
                $selectedType = $candidate;
            }
        }

        $search = trim((string) $request->query->get('q', ''));

        return $this->render('admin/calendar/calendar.html.twig', [
            'types' => $types,
            'selectedType' => $selectedType,
            'search' => $search,
        ]);
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
