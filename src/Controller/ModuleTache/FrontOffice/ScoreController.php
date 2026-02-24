<?php

namespace App\Controller\ModuleTache\FrontOffice;

use App\Entity\Family;
use App\Entity\PointRule;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Repository\PointRuleRepository;
use App\Repository\ScoreHistoryRepository;
use App\Repository\ScoreRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WeeklyAiInsightRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\Ai\WeeklyInsightsOrchestrator;
use App\Service\TaskPointResolver;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/portal/tasks/scores')]
class ScoreController extends AbstractController
{
    #[Route('/', name: 'task_score_index')]
    public function index(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        UserRepository $userRepository,
        ScoreRepository $scoreRepository,
        ScoreHistoryRepository $scoreHistoryRepository,
        WeeklyAiInsightRepository $weeklyAiInsightRepository,
        PaginatorInterface $paginator,
    ): Response {
        [$user, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParentOrChild($user);

        $members = $userRepository->findFamilyMembers($family);
        $membersById = [];
        foreach ($members as $member) {
            $memberId = $member->getId();
            if ($memberId === null) {
                continue;
            }
            $membersById[$memberId] = $member;
        }

        $scoresByUserId = $scoreRepository->findIndexedByUserForFamily($family);
        $leaderboard = [];
        $familyTotal = 0;

        foreach ($members as $member) {
            $memberId = $member->getId();
            if ($memberId === null) {
                continue;
            }

            $score = $scoresByUserId[$memberId] ?? null;
            $points = $score?->getTotalPoints() ?? 0;
            $familyTotal += $points;

            $displayName = trim(((string) $member->getFirstName()).' '.((string) $member->getLastName()));
            if ($displayName === '') {
                $displayName = (string) $member->getEmail();
            }

            $leaderboard[] = [
                'user' => $member,
                'displayName' => $displayName,
                'points' => $points,
                'lastUpdated' => $score?->getLastUpdated(),
                'rank' => 0,
            ];
        }

        usort($leaderboard, static function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            return strcasecmp((string) $a['displayName'], (string) $b['displayName']);
        });

        $position = 0;
        $currentRank = 0;
        $lastPoints = null;
        foreach ($leaderboard as $index => $row) {
            ++$position;
            if ($lastPoints !== $row['points']) {
                $currentRank = $position;
                $lastPoints = $row['points'];
            }
            $leaderboard[$index]['rank'] = $currentRank;
        }

        $currentSearch = trim((string) $request->query->get('q', ''));
        $currentMemberId = (string) $request->query->get('member', 'all');
        $allowedSorts = ['newest', 'oldest', 'points_desc', 'points_asc'];
        $currentSort = (string) $request->query->get('sort', 'newest');
        if (!in_array($currentSort, $allowedSorts, true)) {
            $currentSort = 'newest';
        }
        $allowedSources = ['all', 'ai', 'manual'];
        $currentSource = (string) $request->query->get('source', 'all');
        if (!in_array($currentSource, $allowedSources, true)) {
            $currentSource = 'all';
        }

        $memberFilter = null;
        if ($currentMemberId !== 'all') {
            $memberFilter = $membersById[$currentMemberId] ?? null;
            if (!$memberFilter instanceof User) {
                $currentMemberId = 'all';
            }
        }

        $historyEntries = $scoreHistoryRepository->findForFamilyWithFilters(
            $family,
            $currentSearch,
            $memberFilter,
            $currentSort,
            120,
            $currentSource
        );
        $historyEntries = $paginator->paginate(
            $historyEntries,
            max(1, $request->query->getInt('historyPage', 1)),
            12,
            ['pageParameterName' => 'historyPage']
        );
        $recentAward = $scoreHistoryRepository->findForFamilyWithFilters($family, '', null, 'newest', 1);

        $currentUserRow = null;
        $currentUserId = $user->getId();
        foreach ($leaderboard as $row) {
            $rowUserId = $row['user']->getId();
            if ($rowUserId !== null && $rowUserId === $currentUserId) {
                $currentUserRow = $row;
                break;
            }
        }

        $chartLabels = [];
        $chartData = [];
        foreach (array_slice($leaderboard, 0, 10) as $row) {
            $chartLabels[] = (string) $row['displayName'];
            $chartData[] = (int) $row['points'];
        }

        $currentWeekStart = $this->resolveWeekStart();
        $weeklyInsight = $weeklyAiInsightRepository->findOneForFamilyAndWeek($family, $currentWeekStart);
        if ($weeklyInsight === null) {
            $weeklyInsight = $weeklyAiInsightRepository->findLatestForFamily($family);
        }
        $weeklyPayload = $weeklyInsight?->getPayload();
        if (!is_array($weeklyPayload)) {
            $weeklyPayload = null;
        }

        return $this->render('ModuleTache/frontoffice/score/index.html.twig', [
            'leaderboard' => $leaderboard,
            'currentUserRow' => $currentUserRow,
            'familyTotal' => $familyTotal,
            'historyEntries' => $historyEntries,
            'recentAward' => $recentAward[0] ?? null,
            'leaderboardChartLabels' => $chartLabels,
            'leaderboardChartData' => $chartData,
            'members' => $members,
            'currentSearch' => $currentSearch,
            'currentMemberId' => $currentMemberId,
            'currentSort' => $currentSort,
            'currentSource' => $currentSource,
            'weeklyInsight' => $weeklyInsight,
            'weeklyInsightPayload' => $weeklyPayload,
            'weeklyInsightCurrentWeekStart' => $currentWeekStart,
        ]);
    }

    #[Route('/ai/weekly-refresh', name: 'task_score_ai_refresh', methods: ['POST'])]
    public function refreshWeeklyInsight(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        WeeklyInsightsOrchestrator $weeklyInsightsOrchestrator
    ): Response {
        [$user, $family] = $this->resolveUserAndFamily($familyResolver);
        $this->ensureParent($user);

        if (!$this->isCsrfTokenValid('task_score_ai_refresh', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $insight = $weeklyInsightsOrchestrator->generateForFamily($family, null, true);
        $status = (string) $insight->getStatus();
        if ($status === 'SUCCESS') {
            $this->addFlash('success', 'Resume IA hebdo regenere avec succes.');
        } elseif ($status === 'FALLBACK') {
            $this->addFlash('warning', 'Resume hebdo regenere en mode fallback (sans reponse IA valide).');
        } else {
            $this->addFlash('warning', 'Resume hebdo regenere avec statut '.$status.'.');
        }

        return $this->redirectToRoute('task_score_index');
    }

    #[Route('/rules', name: 'task_score_rules')]
    public function rules(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        TaskRepository $taskRepository,
        PointRuleRepository $pointRuleRepository,
        TaskPointResolver $taskPointResolver
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $isAdminContext = $this->isGranted('ROLE_ADMIN');
        $family = null;

        if ($isAdminContext) {
            $tasks = $taskRepository->findGlobalAdminTasks();
        } else {
            $this->ensureParent($user);
            $family = $familyResolver->resolveForUser($user);
            if (!$family instanceof Family) {
                throw $this->createAccessDeniedException();
            }
            $tasks = $taskRepository->findFamilyTasks($family);
        }

        $currentSearch = trim((string) $request->query->get('q', ''));
        $currentSort = (string) $request->query->get('sort', 'newest');
        $allowedSorts = ['newest', 'oldest', 'title_az', 'title_za'];
        if (!in_array($currentSort, $allowedSorts, true)) {
            $currentSort = 'newest';
        }

        if ($currentSearch !== '') {
            $tasks = array_values(array_filter(
                $tasks,
                static function (Task $task) use ($currentSearch): bool {
                    $haystack = mb_strtolower(((string) $task->getTitle()).' '.((string) $task->getDescription()));
                    return str_contains($haystack, mb_strtolower($currentSearch));
                }
            ));
        }

        $this->sortTasks($tasks, $currentSort);

        $rulesByTaskId = $pointRuleRepository->findActiveForTasks($tasks, new \DateTimeImmutable());
        $resolvedPoints = $taskPointResolver->resolvePointsForTasks($tasks);

        return $this->render('ModuleTache/frontoffice/score/rules.html.twig', [
            'tasks' => $tasks,
            'rulesByTaskId' => $rulesByTaskId,
            'resolvedPoints' => $resolvedPoints,
            'isAdminContext' => $isAdminContext,
            'currentSearch' => $currentSearch,
            'currentSort' => $currentSort,
        ]);
    }

    #[Route('/rules/task/{id}', name: 'task_score_rule_save', methods: ['POST'])]
    public function saveRule(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        ActiveFamilyResolver $familyResolver,
        PointRuleRepository $pointRuleRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('task_point_rule_'.$task->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($task->getFamily() !== null) {
                throw $this->createAccessDeniedException();
            }
        } else {
            $this->ensureParent($user);
            $family = $familyResolver->resolveForUser($user);
            if (!$family instanceof Family || $task->getFamily()?->getId() !== $family->getId()) {
                throw $this->createAccessDeniedException();
            }
        }

        $points = (int) $request->request->get('points', 0);
        if ($points <= 0) {
            $this->addFlash('warning', 'Les points doivent etre superieurs a 0.');

            return $this->redirectToRoute('task_score_rules', $this->buildRulesRedirectParams($request));
        }

        $validFrom = $this->parseLocalDateTime((string) $request->request->get('validFrom', ''));
        $validTo = $this->parseLocalDateTime((string) $request->request->get('validTo', ''));
        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            $this->addFlash('warning', 'La date de fin doit etre apres la date de debut.');

            return $this->redirectToRoute('task_score_rules', $this->buildRulesRedirectParams($request));
        }

        $rule = $pointRuleRepository->findLatestForTask($task);
        if ($rule === null) {
            $rule = new PointRule();
            $rule->setTask($task);
        }

        $rule->setPoints($points);
        $rule->setValidFrom($validFrom);
        $rule->setValidTo($validTo);

        $entityManager->persist($rule);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Regle de points enregistree pour "%s".', (string) $task->getTitle()));

        return $this->redirectToRoute('task_score_rules', $this->buildRulesRedirectParams($request));
    }

    /**
     * @param array<int, Task> $tasks
     */
    private function sortTasks(array &$tasks, string $sort): void
    {
        if ($sort === 'oldest') {
            usort($tasks, static function (Task $a, Task $b): int {
                return ($a->getCreatedAt()?->getTimestamp() ?? 0) <=> ($b->getCreatedAt()?->getTimestamp() ?? 0);
            });

            return;
        }

        if ($sort === 'title_az') {
            usort($tasks, static function (Task $a, Task $b): int {
                return strcasecmp((string) $a->getTitle(), (string) $b->getTitle());
            });

            return;
        }

        if ($sort === 'title_za') {
            usort($tasks, static function (Task $a, Task $b): int {
                return strcasecmp((string) $b->getTitle(), (string) $a->getTitle());
            });

            return;
        }

        usort($tasks, static function (Task $a, Task $b): int {
            return ($b->getCreatedAt()?->getTimestamp() ?? 0) <=> ($a->getCreatedAt()?->getTimestamp() ?? 0);
        });
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
        if (!$family instanceof Family) {
            throw $this->createAccessDeniedException();
        }

        return [$user, $family];
    }

    private function ensureParentOrChild(User $user): void
    {
        $role = $user->getFamilyRole();
        if ($role !== FamilyRole::PARENT && $role !== FamilyRole::CHILD) {
            throw $this->createAccessDeniedException();
        }
    }

    private function ensureParent(User $user): void
    {
        if ($user->getFamilyRole() !== FamilyRole::PARENT) {
            throw $this->createAccessDeniedException();
        }
    }

    private function parseLocalDateTime(string $value): ?\DateTimeImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $trimmed);
        if ($date instanceof \DateTimeImmutable) {
            return $date;
        }

        try {
            return new \DateTimeImmutable($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildRulesRedirectParams(Request $request): array
    {
        $params = [];
        $search = trim((string) $request->request->get('q', ''));
        $sort = trim((string) $request->request->get('sort', ''));

        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        return $params;
    }

    private function resolveWeekStart(?\DateTimeImmutable $at = null): \DateTimeImmutable
    {
        $base = $at ?? new \DateTimeImmutable();

        return $base->modify('monday this week')->setTime(0, 0, 0);
    }
}
