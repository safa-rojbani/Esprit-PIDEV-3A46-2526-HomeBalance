# User Management Overview

## Controllers & Routes
- [src/Controller/UiPortalController.php](../src/Controller/UiPortalController.php) scopes every user-facing action under `#[Route('/portal', name: 'portal_')]`, rendering account/profile templates such as [templates/ui_portal/account-settings-account.html.twig](../templates/ui_portal/account-settings-account.html.twig), [templates/ui_portal/account-settings-notifications.html.twig](../templates/ui_portal/account-settings-notifications.html.twig), and [templates/ui_portal/account-settings-connections.html.twig](../templates/ui_portal/account-settings-connections.html.twig) with `ROLE_USER` guards and CSRF validation.
- The same controller covers auth flows: login ([templates/ui_portal/auth-login-basic.html.twig](../templates/ui_portal/auth-login-basic.html.twig)), register ([templates/ui_portal/auth-register-basic.html.twig](../templates/ui_portal/auth-register-basic.html.twig)), and forgot password ([templates/ui_portal/auth-forgot-password-basic.html.twig](../templates/ui_portal/auth-forgot-password-basic.html.twig)), layering uniqueness checks, reset tokens, and flash feedback via `AuthenticationUtils` and `NotificationService`.
- Account deletion and notification endpoints redirect through `portal_auth_login`, clear tokens via `TokenStorageInterface`, and raise audit events so `AuditTrailService` plus `NotificationService` track every preference change or scheduled deletion.
- [src/Controller/AdminUserController.php](../src/Controller/AdminUserController.php) mounts at `/portal/admin` and renders [templates/ui_portal/admin/users/index.html.twig](../templates/ui_portal/admin/users/index.html.twig) plus [templates/ui_portal/admin/users/view.html.twig](../templates/ui_portal/admin/users/view.html.twig); POST actions flip `UserStatus`, queue reset tokens, and reuse presenter helpers for Twig tables.

## Routing Status
- [config/routes.yaml](../config/routes.yaml) points `/` to `UiPortalController::authLogin`, making the portal login screen the application root while Symfony still auto-loads other routes via attributes.
- The `controllers` import loads every class in `src/Controller` with `type: attribute`, so adding methods with `#[Route]` annotations automatically exposes them without extra YAML entries.
- Controller-level prefixes (`#[Route('/portal', name: 'portal_')]`, `#[Route('/portal/admin', name: 'portal_admin_')]`) keep URL namespaces and route names predictable for Twig helpers, especially inside sidebar links.
- Generated route names such as `portal_account_notifications` or `portal_admin_users_toggle_status` double as template references and redirect targets, so renaming requires synchronized updates across controllers, templates, and tests.

## Security Configuration
- [config/packages/security.yaml](../config/packages/security.yaml) wires a single `app_user_provider` backed by `App\Entity\User`, giving the firewall access to persisted users plus remember-me tokens.
- The `main` firewall relies on form_login with `_username`/`_password` fields, CSRF id `authenticate`, and `default_target_path` of `portal_account`, matching the forms built in `UiPortalController`.
- Logout is handled via `portal_auth_logout` with redirects to `portal_auth_login`, and remember-me cookies last seven days using `%env(APP_SECRET)%` for signing.
- Access control currently hard-stops `/portal/admin` unless the session has `ROLE_ADMIN`, while other sections lean on controller-level `denyAccessUnlessGranted` for fine-grained gating.

## Templates & Layout Partials
- [templates/base.html.twig](../templates/base.html.twig) is the minimal Sneat wrapper that loads core CSS/JS plus `{{ importmap('app') }}` and can host standalone pages if needed.
- Portal pages extend [templates/ui_portal/layouts/base.html.twig](../templates/ui_portal/layouts/base.html.twig), which imports Google Fonts, vendor CSS, flash renderers, and a consistent content wrapper with footer + backdrop overlays.
- [templates/ui_portal/partials/sidebar.html.twig](../templates/ui_portal/partials/sidebar.html.twig) groups links into "User Management" and "Authentication" buckets, applies `active_menu` styling, and links directly to `portal_*` routes.
- [templates/ui_portal/partials/navbar.html.twig](../templates/ui_portal/partials/navbar.html.twig) supplies the responsive toggle, search input, GitHub CTA, and dropdown scaffold for future profile/setting links, currently populated with demo avatars, while the new [templates/ui_portal/dashboard.html.twig](../templates/ui_portal/dashboard.html.twig) card grid taps `UserMetricsFormatter` output for badge/family stats.
- Step 5 introduced a dedicated animated auth wrapper ([templates/ui_portal/layouts/auth.html.twig](../templates/ui_portal/layouts/auth.html.twig)) consumed by both [login](../templates/ui_portal/auth-login-basic.html.twig) and [registration](../templates/ui_portal/auth-register-basic.html.twig) views. The layout pairs copy-driven hero content with a frosted-glass panel, while the refreshed dashboard template layers orbit hero cards, the notification heatmap, and a focus queue for next actions.

## Frontend Assets & Hooks
- [assets/css/demo.css](../assets/css/demo.css) defines HomeBalance-branded CSS variables, typography, card styling, and layout polish that sit on top of Sneat defaults.
- [assets/js/main.js](../assets/js/main.js) bootstraps password-visibility toggles and the layout-menu collapse behaviour via lightweight DOM utilities, keeping behaviour deterministic without extra frameworks.
- [templates/ui_portal/layouts/base.html.twig](../templates/ui_portal/layouts/base.html.twig) loads vendor helpers such as `vendor/libs/perfect-scrollbar/perfect-scrollbar.js`, `vendor/js/menu.js`, and `vendor/js/bootstrap.js`, which power sidebar animations and scroll inertia.
- Additional entrypoints live under `public/assets` and `assets/vendor`, so chart/heatmap libraries can be registered through `importmap('app')` alongside existing Sneat bundles when data visualizations land.
- Stimulus now drives the “wow factor” micro-interactions: controllers like [assets/controllers/auth_shell_controller.js](../assets/controllers/auth_shell_controller.js), [notification_heatmap_controller.js](../assets/controllers/notification_heatmap_controller.js), and [pulse_card_controller.js](../assets/controllers/pulse_card_controller.js) handle parallax gradients, interactive heatmap toggles, auto-suggested usernames, and live engagement meters. Each controller is registered in [assets/bootstrap.js](../assets/bootstrap.js), keeping declarative `data-controller="…"` hooks in Twig tidy.

## Data Layer & Migrations
- [src/Entity/User.php](../src/Entity/User.php) now also exposes a `badges` many-to-many collection so gamification metrics can be surfaced on dashboards.
- [src/Entity/Badge.php](../src/Entity/Badge.php) captures reward metadata (name, icon, required points) for gamified progress, while [src/Entity/SupportTicket.php](../src/Entity/SupportTicket.php) links tickets to `Conversation` threads with enum statuses.
- [src/Entity/Family.php](../src/Entity/Family.php) stores household names, join codes, expiry windows, and creator references, enabling future sharing/invitation flows surfaced in the portal.
- [src/Entity/FamilyMembership.php](../src/Entity/FamilyMembership.php) + [src/Repository/FamilyMembershipRepository.php](../src/Repository/FamilyMembershipRepository.php) track who belongs to each family, their `FamilyRole`, and when they joined/left, unlocking richer onboarding and admin insights.
- [src/Entity/AuditTrail.php](../src/Entity/AuditTrail.php) records channel + IP metadata alongside payloads so recent-activity timelines can surface provenance info.
- [src/Service/UserMetricsFormatter.php](../src/Service/UserMetricsFormatter.php) aggregates badge counts, family membership totals, and role metadata for use in dashboards and admin summaries.
- [migrations/Version20260206133314.php](../migrations/Version20260206133314.php) provisions the full domain schema (users, families, tasks, audit trails, etc.), and [migrations/Version20260207141430.php](../migrations/Version20260207141430.php) later enforces the unique username column, so the Doctrine migration status should be up-to-date before QA.

## Tests & Fixtures
- [tests/Controller/PortalAccessTest.php](../tests/Controller/PortalAccessTest.php) verifies public auth pages load and that protected `/portal/*` routes redirect to `/portal/auth/login` when anonymous.
- [tests/Controller/ForgotPasswordTest.php](../tests/Controller/ForgotPasswordTest.php) covers invalid vs valid email submissions, mocking `UserRepository` to focus on flash messaging and redirect behaviour.
- [tests/Fixtures/PortalRouteFixtures.php](../tests/Fixtures/PortalRouteFixtures.php) centralizes the list of public paths, expected selectors, and redirect targets used by the controller smoke tests.
- [tests/Fixtures/ForgotPasswordFixtures.php](../tests/Fixtures/ForgotPasswordFixtures.php) supplies canonical invalid/valid email payloads, highlighting where richer auth fixtures will belong once persistence-based tests are added.

## Gaps & Next Steps
- Seed real users/admins (fixtures or migrations) so manual logins and WebTestCase scenarios stop depending on mocked repositories and can exercise password hashing.
- Replace the static avatar/name/dropdown text in [templates/ui_portal/partials/navbar.html.twig](../templates/ui_portal/partials/navbar.html.twig) with live `User` data and consolidate overlapping account links between the navbar dropdown and sidebar.
- Introduce dashboard/heatmap visualizations by adding chart libraries to the import map, surfacing target containers in `ui_portal` templates, and returning summarized metrics from controllers/services.
- Wire notification/account audit trails into the UI (e.g., recent activity list, delivery previews) so the existing `AuditTrailService` and `NotificationService` calls result in visible feedback for admins and end users.
