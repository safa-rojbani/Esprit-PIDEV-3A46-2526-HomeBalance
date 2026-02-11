<?php

namespace App\Controller\Client;

use App\Entity\TypeEvenement;
use App\Form\TypeEvenementClientType;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/type-evenement')]
class TypeEvenementController extends AbstractController
{
    #[Route('', name: 'app_type_evenement_index', methods: ['GET'])]
    public function index(TypeEvenementRepository $repo): Response
    {
        $user = $this->getUser();
        $family = $user?->getFamily();

        $types = $family
            ? $repo->findBy(['family' => $family])
            : $repo->findBy(['family' => null]);

        return $this->render('app/type_evenement/index.html.twig', [
            'type_evenements' => $types,
        ]);
    }

    #[Route('/new', name: 'app_type_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $typeEvenement = new TypeEvenement();
        $form = $this->createForm(TypeEvenementClientType::class, $typeEvenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEvenement->setDateCreation(new \DateTimeImmutable());
            $typeEvenement->setFamily($this->getUser()?->getFamily());

            $em->persist($typeEvenement);
            $em->flush();

            return $this->redirectToRoute('app_type_evenement_index');
        }

        return $this->render('app/type_evenement/new.html.twig', [
            'type_evenement' => $typeEvenement,
            'form' => $form->createView(),
        ]);
    }
}
