<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\Revenu;
use App\Form\ModuleCharge\RevenuType;
use App\Repository\RevenuRepository;
use App\ServiceModuleCharge\RevenuService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/revenu')]
final class RevenuController extends AbstractController
{
    #[Route('', name: 'app_revenu_index', methods: ['GET'])]
    public function index(RevenuRepository $repo): Response
    {
        return $this->render('module_charge/User/revenu/index.html.twig', [
            'revenus' => $repo->findBy([], ['dateRevenu' => 'DESC']),
        ]);
    }

    #[Route('/budget', name: 'app_revenu_budget', methods: ['GET'])]
    public function budget(RevenuService $budgetService): Response
    {
        return $this->render('module_charge/User/revenu/revenu.html.twig', [
            'totalRevenus' => $budgetService->totalRevenusAll(),
            'totalDepenses' => $budgetService->totalDepensesAll(),
            'solde' => $budgetService->soldeAll(),
        ]);
    }

    #[Route('/new', name: 'app_revenu_new', methods: ['GET','POST'])]
    public function new(Request $request, RevenuRepository $repo, EntityManagerInterface $em): Response
    {
        $revenu = new Revenu();

        // types existants (sans family)
        $types = $repo->findDistinctTypesAll();

        $form = $this->createForm(RevenuType::class, $revenu, ['types' => $types]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $typeLibre = trim((string) $form->get('typeRevenuLibre')->getData());
            if ($typeLibre !== '') {
                $revenu->setTypeRevenu($typeLibre);
            }

            if (!$revenu->getTypeRevenu()) {
                $this->addFlash('danger', 'Veuillez choisir un type ou saisir un nouveau type.');
                return $this->redirectToRoute('app_revenu_new');
            }

            // TEMP sans auth
            $revenu->setCreatedBy($this->getUser()); // peut être null
            $revenu->setFamily(null);

            if (!$revenu->getDateRevenu()) {
                $revenu->setDateRevenu(new \DateTimeImmutable());
            }

            $em->persist($revenu);
            $em->flush();

            return $this->redirectToRoute('app_revenu_index');
        }

        return $this->render('module_charge/User/revenu/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_revenu_show', methods: ['GET'])]
    public function show(Revenu $revenu): Response
    {
        return $this->render('module_charge/User/revenu/show.html.twig', [
            'revenu' => $revenu,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_revenu_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Revenu $revenu, RevenuRepository $repo, EntityManagerInterface $em): Response
    {
        $types = $repo->findDistinctTypesAll();

        $form = $this->createForm(RevenuType::class, $revenu, ['types' => $types]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $typeLibre = trim((string) $form->get('typeRevenuLibre')->getData());
            if ($typeLibre !== '') {
                $revenu->setTypeRevenu($typeLibre);
            }

            if (!$revenu->getTypeRevenu()) {
                $this->addFlash('danger', 'Veuillez choisir un type ou saisir un nouveau type.');
                return $this->redirectToRoute('app_revenu_edit', ['id' => $revenu->getId()]);
            }

            $em->flush();
            return $this->redirectToRoute('app_revenu_index');
        }

        return $this->render('module_charge/User/revenu/edit.html.twig', [
            'form' => $form->createView(),
            'revenu' => $revenu,
        ]);
    }

    #[Route('/{id}', name: 'app_revenu_delete', methods: ['POST'])]
    public function delete(Request $request, Revenu $revenu, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$revenu->getId(), $request->request->get('_token'))) {
            $em->remove($revenu);
            $em->flush();
        }

        return $this->redirectToRoute('app_revenu_index');
    }
}
