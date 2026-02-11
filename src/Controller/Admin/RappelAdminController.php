<?php

namespace App\Controller\Admin;

use App\Repository\RappelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rappel')]
class RappelAdminController extends AbstractController
{
    #[Route('', name: 'admin_rappel_index', methods: ['GET'])]
    public function index(RappelRepository $rappelRepository): Response
    {
        return $this->render('admin/rappel/index.html.twig', [
            'rappels' => $rappelRepository->findBy([], ['id' => 'DESC']),
        ]);
    }
}
