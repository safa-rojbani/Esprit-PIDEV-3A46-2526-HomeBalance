# Feature Evidence Matrix (Defense)

## How to Use
For each rubric line, show:
- feature
- concrete code evidence
- live demo evidence

## Category 2: API + Bundle Integration
1. External API (email provider):
- Feature: transactional account emails via mailer DSN.
- Code:
- `config/packages/mailer.yaml`
- `src/MessageHandler/SendAccountNotificationHandler.php`
- `docs/email_provider_setup.md`
- Demo:
- Trigger password reset.
- Show pending/sent notification in admin notifications page.

2. Bundle usage (KNP Paginator):
- Feature: pagination on heavy admin tables.
- Code:
- `config/packages/knp_paginator.yaml`
- `src/Controller/AdminUserController.php`
- `src/Controller/AdminFamilyMembershipController.php`
- `src/Controller/AdminFamilyInvitationController.php`
- `src/Controller/AdminNotificationController.php`
- Demo:
- Open each admin list and navigate to page 2.

## Category 3: Scenario + Data Coverage
1. Reproducible fixture dataset:
- Code:
- `src/DataFixtures/UserFixtures.php`
- `src/DataFixtures/FamilyFixtures.php`
- `src/DataFixtures/InvitationFixtures.php`
- `src/DataFixtures/AuditFixtures.php`
- `src/DataFixtures/NotificationFixtures.php`
- `src/DataFixtures/BadgeFixtures.php`
- `src/DataFixtures/ScoreFixtures.php`
- Demo:
- Run fixtures.
- Show active and suspended users, invitation states, notification states.

2. Demo script:
- `docs/ums-demo-scenario.md`

## Category 4: Mastery and Engineering Depth
1. Role approval workflow:
- Code:
- `src/Entity/RoleChangeRequest.php`
- `src/Controller/AdminUserController.php`
- `templates/ui_portal/admin/users/view.html.twig`
- `migrations/Version20260222110000.php`
- `tests/Controller/AdminRoleWorkflowTest.php`
- Demo:
- Create request, approve request, verify role updated + audit events.

2. Audit export:
- Code:
- `src/Service/CsvExportService.php`
- `src/Controller/AdminUserController.php`
- Demo:
- Click "Exporter audit CSV" and open downloaded CSV.

## Category 5: Value Added / Intelligence
1. Account health score:
- Code:
- `src/Service/AccountHealthScoreService.php`
- `src/Repository/AuditTrailRepository.php`
- `src/Controller/AdminUserController.php`
- `templates/ui_portal/admin/users/view.html.twig`
- Demo:
- Open user detail and explain score + explanation lines.

## Category 6: Team Process / Delivery Evidence
1. Change log:
- `docs/ums_changes.md`
2. Test evidence:
- `tests/Controller/AdminRoleWorkflowTest.php`
- `tests/Controller/AdminUserControllerTest.php`
3. Architecture and setup docs:
- `docs/ums_architecture.md`
- `docs/email_provider_setup.md`
