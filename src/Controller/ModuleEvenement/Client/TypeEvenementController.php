<?php

namespace App\Controller\ModuleEvenement\Client;

use App\Entity\TypeEvenement;
use App\Form\TypeEvenementClientType;
use App\Repository\TypeEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActiveFamilyResolver;
use App\Entity\User;
use App\Entity\Family;

#[Route('/portal/evenements/types')]
class TypeEvenementController extends AbstractController
{
    #[Route('', name: 'app_type_evenement_index', methods: ['GET'])]
    public function index(TypeEvenementRepository $repo, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $types = $repo->findBy(['family' => $family]);

        return $this->render('app/type_evenement/index.html.twig', [
            'type_evenements' => $types,
        ]);
    }

    #[Route('/new', name: 'app_type_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);

        $typeEvenement = new TypeEvenement();
        $form = $this->createForm(TypeEvenementClientType::class, $typeEvenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEvenement->setDateCreation(new \DateTimeImmutable());
            $typeEvenement->setFamily($family);

            $em->persist($typeEvenement);
            $em->flush();

            return $this->redirectToRoute('app_type_evenement_index');
        }

        return $this->render('app/type_evenement/new.html.twig', [
            'type_evenement' => $typeEvenement,
            'form' => $form->createView(),
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
