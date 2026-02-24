<?php

namespace  App\Controller\ModuleCharge\User;

use App\Entity\Achat;
use App\Entity\Family;
use App\Entity\User;
use App\Form\ModuleCharge\AchatType;
use App\Repository\AchatRepository;
use App\Repository\CategorieAchatRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\HistoriqueAchatRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\HistoriqueAchat;
use App\Form\ModuleCharge\HistoriqueAchatConfirmType;
use App\Repository\UserRepository;
use App\Service\ActiveFamilyResolver;

#[Route('/portal/charge/achats')]
final class AchatController extends AbstractController
{
 #[Route('', name: 'app_achat_index', methods: ['GET','POST'])]
public function index(
    Request $request,
    AchatRepository $achatRepository,
    CategorieAchatRepository $categorieAchatRepository,
    EntityManagerInterface $entityManager,
    ActiveFamilyResolver $familyResolver,
    PaginatorInterface $paginator
): Response
{
    $family = $this->resolveFamily($familyResolver);
    $user = $this->getUser();
    $searchQuery = trim((string) $request->query->get('q', ''));
    $month = trim((string) $request->query->get('month', ''));
    $categoryId = $request->query->getInt('category', 0);
    $page = max(1, $request->query->getInt('page', 1));
    $limit = $request->query->getInt('limit', 10);
    if (!\in_array($limit, [10, 20], true)) {
        $limit = 10;
    }

    $selectedCategory = null;
    if ($categoryId > 0) {
        $candidate = $categorieAchatRepository->find($categoryId);
        if ($candidate !== null && $candidate->getFamily()?->getId() === $family->getId()) {
            $selectedCategory = $candidate;
        }
    }

    $achat = new Achat();
    $formNew = $this->createForm(AchatType::class, $achat);
    $formNew->handleRequest($request);

    if ($formNew->isSubmitted() && $formNew->isValid()) {
        $achat->setCreatedAt(new \DateTimeImmutable());
        $achat->setCreatedBy($user);
        $achat->setEstAchete(false);
        $achat->setFamily($family);

        $entityManager->persist($achat);
        $entityManager->flush();

        return $this->redirectToRoute('app_achat_index');
    }

    $queryBuilder = $achatRepository->createFilteredByFamilyQueryBuilder(
        $family,
        $searchQuery,
        $selectedCategory,
        $month !== '' ? $month : null
    );
    $pagination = $paginator->paginate($queryBuilder, $page, $limit);

    return $this->render('module_charge/User/achat/index.html.twig', [
        'achats' => $pagination,
        'formNew' => $formNew->createView(),
        'openOffcanvas' => $formNew->isSubmitted() && !$formNew->isValid(), // pour rouvrir si erreur
        'searchQuery' => $searchQuery,
        'categories' => $categorieAchatRepository->findBy(['family' => $family], ['nomCategorie' => 'ASC']),
        'selectedCategoryId' => $selectedCategory?->getId(),
        'selectedMonth' => $month,
        'selectedLimit' => $limit,
    ]);
}

#----------------------------new
    #[Route('/new', name: 'app_achat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $user = $this->getUser();

        $achat = new Achat();
        $form = $this->createForm(AchatType::class, $achat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $achat->setCreatedAt(new \DateTimeImmutable());
$achat->setCreatedBy($user);
$achat->setEstAchete(false);
            $achat->setFamily($family);
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
    public function show(Achat $achat, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $achat->getFamily());

        return $this->render('module_charge/User/achat/show.html.twig', [
            'achat' => $achat,
        ]);
    }
#--------------------edit
    #[Route('/{id}/edit', name: 'app_achat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Achat $achat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $achat->getFamily());

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
    public function delete(Request $request, Achat $achat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
    {
        $family = $this->resolveFamily($familyResolver);
        $this->assertSameFamily($family, $achat->getFamily());

        if ($this->isCsrfTokenValid('delete'.$achat->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($achat);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_achat_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/toggle', name: 'app_achat_toggle', methods: ['POST'])]
public function toggle(Request $request, Achat $achat, EntityManagerInterface $entityManager, ActiveFamilyResolver $familyResolver): Response
{
    $family = $this->resolveFamily($familyResolver);
    $this->assertSameFamily($family, $achat->getFamily());

    if ($this->isCsrfTokenValid('toggle'.$achat->getId(), $request->request->get('_token'))) {
        // Keep state consistent with HistoriqueAchat flow.
        // Confirmed purchase must go through /confirmer to capture amount.
        if (!$achat->isEstAchete()) {
            return $this->redirectToRoute('app_achat_confirmer', ['id' => $achat->getId()]);
        }

        $achat->setEstAchete(false);
        $historiques = $entityManager->getRepository(HistoriqueAchat::class)->findBy(['achat' => $achat]);
        foreach ($historiques as $historique) {
            $entityManager->remove($historique);
        }
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
    UserRepository $userRepository,
    ActiveFamilyResolver $familyResolver
): Response {
    $family = $this->resolveFamily($familyResolver);
    $this->assertSameFamily($family, $achat->getFamily());

    // éviter duplication si déjà acheté
    if ($achat->isEstAchete()) {
        return $this->redirectToRoute('app_achat_index');
    }

    // Defensive check: if history already exists, keep flags aligned.
    $existingHistorique = $entityManager->getRepository(HistoriqueAchat::class)->findOneBy(['achat' => $achat]);
    if ($existingHistorique !== null) {
        $achat->setEstAchete(true);
        $entityManager->flush();

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

        // paidBy : user connecté
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
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
    HistoriqueAchatRepository $historiqueAchatRepository,
    ActiveFamilyResolver $familyResolver
): Response {
    $family = $this->resolveFamily($familyResolver);
    $this->assertSameFamily($family, $achat->getFamily());

    if (!$this->isCsrfTokenValid('annuler'.$achat->getId(), $request->request->get('_token'))) {
        return $this->redirectToRoute('app_achat_index');
    }

    $achat->setEstAchete(false);

    $historiques = $historiqueAchatRepository->findBy(['achat' => $achat]);
    foreach ($historiques as $historique) {
        $entityManager->remove($historique);
    }

    $entityManager->flush();

    return $this->redirectToRoute('app_achat_index');
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

    private function assertSameFamily(Family $family, ?Family $targetFamily): void
    {
        if ($targetFamily === null || $targetFamily->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

}
