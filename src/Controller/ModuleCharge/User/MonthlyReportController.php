<?php

namespace App\Controller\ModuleCharge\User;

use App\Entity\Family;
use App\Entity\User;
use App\Service\ActiveFamilyResolver;
use App\ServiceModuleCharge\MonthlyReportAiService;
use App\ServiceModuleCharge\MonthlyReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/charge/rapport-mensuel')]
final class MonthlyReportController extends AbstractController
{
    #[Route('', name: 'app_monthly_report_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ActiveFamilyResolver $familyResolver,
        MonthlyReportService $monthlyReportService,
        MonthlyReportAiService $monthlyReportAiService
    ): Response {
        $family = $this->resolveFamily($familyResolver);

        $monthInput = trim((string) $request->get('month', ''));
        $monthStart = $this->parseMonth($monthInput);
        $report = $monthlyReportService->build($family, $monthStart);

        $aiSummary = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('generate_ai_monthly_report', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide. Recharge la page puis réessaie.');
            } else {
                $aiSummary = $monthlyReportAiService->generate($report);
                $this->addFlash('info', sprintf('Résumé généré via: %s', (string) ($aiSummary['provider'] ?? 'unknown')));
            }
        }

        return $this->render('module_charge/User/monthly_report/index.html.twig', [
            'monthValue' => $monthStart->format('Y-m'),
            'report' => $report,
            'aiSummary' => $aiSummary,
        ]);
    }

    private function parseMonth(string $monthInput): \DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $monthInput) === 1) {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthInput.'-01 00:00:00');
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        return new \DateTimeImmutable('first day of this month');
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
