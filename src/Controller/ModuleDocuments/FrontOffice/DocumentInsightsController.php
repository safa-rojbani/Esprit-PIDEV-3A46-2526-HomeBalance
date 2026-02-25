<?php

namespace App\Controller\ModuleDocuments\FrontOffice;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/documents')]
final class DocumentInsightsController extends AbstractController
{
    #[Route('/insights', name: 'app_document_insights', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('ModuleDocuments/FrontOffice/insights/index.html.twig');
    }
}

