<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Family;
use App\Entity\TaskCompletion;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Form\ParentRefusalType;
use App\Service\ActiveFamilyResolver;
use App\Service\TaskScoringService;
use App\Message\TaskCompleted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/portal/tasks/parent/validations')]
class ParentValidationController extends AbstractController
{
    #[Route('/', name: 'parent_validation_index')]
    public function index(EntityManagerInterface $em, ActiveFamilyResolver $familyResolver): Response
    {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);

        $completions = $em->createQueryBuilder()
            ->select('tc')
            ->from(TaskCompletion::class, 'tc')
            ->join('tc.task', 't')
            ->leftJoin('tc.aiEvaluation', 'aie')
            ->addSelect('aie')
            ->where('tc.isValidated IS NULL')
            ->andWhere('t.family = :family')
            ->setParameter('family', $family)
            ->orderBy('tc.completedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('ModuleTache/frontoffice/parent/validations.html.twig', [
            'completions' => $completions,
        ]);
    }

    #[Route('/{id}/accept', name: 'parent_validation_accept')]
    public function accept(
        TaskCompletion $completion,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver,
        TaskScoringService $taskScoringService,
        MessageBusInterface $messageBus
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);
        if ($completion->getTask()?->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($completion->isValidated() !== null) {
            throw new \Exception('Deja traite');
        }

        $completion->setIsValidated(true);
        $completion->setValidatedBy($parent);
        $completion->setValidatedAt(new \DateTimeImmutable());

        $awardedPoints = $taskScoringService->awardPointsForValidatedCompletion($completion);
        $em->flush();

        if ($awardedPoints > 0) {
            $messageBus->dispatch(new TaskCompleted([
                'familyId' => $family->getId(),
            ]));
            $this->addFlash('success', sprintf(
                '%s a gagne %d points.',
                $completion->getUser()?->getFirstName() ?? 'Le membre',
                $awardedPoints
            ));
        }

        return $this->redirectToRoute('parent_validation_index');
    }

    #[Route('/{id}/refuse', name: 'parent_validation_refuse')]
    public function refuse(
        TaskCompletion $completion,
        Request $request,
        EntityManagerInterface $em,
        ActiveFamilyResolver $familyResolver
    ): Response {
        [$parent, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($parent);
        if ($completion->getTask()?->getFamily()?->getId() !== $family->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($completion->isValidated() !== null) {
            throw new \Exception('Deja traite');
        }

        $form = $this->createForm(ParentRefusalType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $completion->setIsValidated(false);
            $completion->setValidatedBy($parent);
            $completion->setValidatedAt(new \DateTimeImmutable());
            $completion->setParentComment($form->get('parentComment')->getData());

            $em->flush();

            return $this->redirectToRoute('parent_validation_index');
        }

        return $this->render('ModuleTache/frontoffice/parent/refuse.html.twig', [
            'form' => $form->createView(),
            'completion' => $completion,
        ]);
    }

    /**
     * @return array{0: User, 1: Family}
     */
    private function resolveUserAndFamily(ActiveFamilyResolver $familyResolver): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $family = $familyResolver->resolveForUser($user);
        if ($family === null) {
            throw $this->createAccessDeniedException();
        }

        return [$user, $family];
    }

    private function ensureParent(User $user): void
    {
        if ($user->getFamilyRole() !== FamilyRole::PARENT) {
            throw $this->createAccessDeniedException();
        }
    }
}
