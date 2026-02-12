<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Repository\RappelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/portal/admin/evenements/rappels')]
class RappelAdminController extends AbstractController
{
    #[Route('', name: 'admin_rappel_index', methods: ['GET'])]
    public function index(RappelRepository $rappelRepository): Response
    {
        $admin = $this->requireAdminUser();
        $rappels = $rappelRepository->createQueryBuilder('r')
            ->leftJoin('r.evenement', 'e')
            ->addSelect('e')
            ->andWhere('e.family IS NULL')
            ->andWhere('e.createdBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/rappel/index.html.twig', [
            'rappels' => $rappels,
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
