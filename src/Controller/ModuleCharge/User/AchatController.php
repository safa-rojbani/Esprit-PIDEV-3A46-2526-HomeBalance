<?php

namespace  App\Controller\ModuleCharge\User;

use App\Entity\Achat;
use App\Form\ModuleCharge\AchatType;
use App\Repository\AchatRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\HistoriqueAchatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\HistoriqueAchat;
use App\Form\ModuleCharge\HistoriqueAchatConfirmType;
use App\Repository\UserRepository;

#[Route('/achat')]
final class AchatController extends AbstractController
{
 #[Route('/achat', name: 'app_achat_index', methods: ['GET','POST'])]
public function index(Request $request, AchatRepository $achatRepository, EntityManagerInterface $entityManager): Response
{
    $achat = new Achat();
    $formNew = $this->createForm(AchatType::class, $achat);
    $formNew->handleRequest($request);

    if ($formNew->isSubmitted() && $formNew->isValid()) {
        $achat->setCreatedAt(new \DateTimeImmutable());
        $achat->setCreatedBy($this->getUser());
        $achat->setEstAchete(false);

        $entityManager->persist($achat);
        $entityManager->flush();

        return $this->redirectToRoute('app_achat_index');
    }

    return $this->render('module_charge/User/achat/index.html.twig', [
        'achats' => $achatRepository->findBy([], ['createdAt' => 'DESC']),
        'formNew' => $formNew->createView(),
        'openOffcanvas' => $formNew->isSubmitted() && !$formNew->isValid(), // pour rouvrir si erreur
    ]);
}

#----------------------------new
    #[Route('/new', name: 'app_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $achat = new Achat();
        $form = $this->createForm(AchatType::class, $achat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $achat->setCreatedAt(new \DateTimeImmutable());
$achat->setCreatedBy($this->getUser());
$achat->setEstAchete(false);
            $entityManager->persist($achat);
            $entityManager->flush();

            return $this->redirectToRoute('app_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module_charge/User/achat/new.html.twig', [
            'achat' => $achat,
            'form' => $form,
           

        ]);
    }
#----------------------show
    #[Route('/{id}', name: 'app_achat_show', methods: ['GET'])]
    public function show(Achat $achat): Response
    {
        return $this->render('module_charge/User/achat/show.html.twig', [
            'achat' => $achat,
        ]);
    }
#--------------------edit
    #[Route('/{id}/edit', name: 'app_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Achat $achat, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AchatType::class, $achat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_achat_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module_charge/User/achat/edit.html.twig', [
            'achat' => $achat,
             'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_achat_delete', methods: ['POST'])]
    public function delete(Request $request, Achat $achat, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$achat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($achat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_achat_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/toggle', name: 'app_achat_toggle', methods: ['POST'])]
public function toggle(Request $request, Achat $achat, EntityManagerInterface $entityManager): Response
{
    if ($this->isCsrfTokenValid('toggle'.$achat->getId(), $request->request->get('_token'))) {
        // inverse la valeur (false -> true, true -> false)
        $achat->setEstAchete(!$achat->isEstAchete());
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_achat_index', [], Response::HTTP_SEE_OTHER);
}
#----------------
#[Route('/{id}/confirmer', name: 'app_achat_confirmer', methods: ['GET', 'POST'])]

public function confirmer(
    Achat $achat,
    Request $request,
    EntityManagerInterface $entityManager,
    UserRepository $userRepository
): Response {
    // éviter duplication si déjà acheté
    if ($achat->isEstAchete()) {
        return $this->redirectToRoute('app_achat_index');
    }

    $historique = new HistoriqueAchat();
    $form = $this->createForm(HistoriqueAchatConfirmType::class, $historique);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        // 1) marquer l'achat comme acheté
        $achat->setEstAchete(true);

        // 2) compléter historique avec les champs automatiques
        $historique->setAchat($achat);
        $historique->setDateAchat(new \DateTimeImmutable());

        // family depuis achat (si tu veux l'utiliser)
        $historique->setFamily($achat->getFamily());

        // paidBy : user connecté sinon user test (TEMP)
        $user = $this->getUser();
        if (!$user) {
            $user = $userRepository->findOneBy([]); // premier user en DB
        }
        $historique->setPaidBy($user);

        $entityManager->persist($historique);
        $entityManager->flush();

        return $this->redirectToRoute('app_achat_index');
    }

    return $this->render('module_charge/User/achat/confirmer.html.twig', [
        'achat' => $achat,
        'form' => $form,
    ]);
}

#----------
#[Route('/{id}/annuler', name: 'app_achat_annuler', methods: ['POST'])]
public function annuler(
    Request $request,
    Achat $achat,
    EntityManagerInterface $entityManager,
    HistoriqueAchatRepository $historiqueAchatRepository
): Response {
    if (!$this->isCsrfTokenValid('annuler'.$achat->getId(), $request->request->get('_token'))) {
        return $this->redirectToRoute('app_achat_index');
    }

    $achat->setEstAchete(false);

    $historique = $historiqueAchatRepository->findOneBy(['achat' => $achat]);
    if ($historique) {
        $entityManager->remove($historique);
    }

    $entityManager->flush();

    return $this->redirectToRoute('app_achat_index');
}

}
