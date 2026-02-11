<?php

namespace App\Controller\ModuleEvenement\Admin;

use App\Repository\RappelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/admin/evenements/rappels')]
class RappelAdminController extends AbstractController
{
    #[Route('', name: 'admin_rappel_index', methods: ['GET'])]
    public function index(RappelRepository $rappelRepository, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        return $this->render('admin/rappel/index.html.twig', [
            'rappels' => $rappelRepository->findBy(['family' => $family], ['id' => 'DESC']),
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
