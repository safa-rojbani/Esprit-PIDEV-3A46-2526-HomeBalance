# UMS Architecture (Defense)

## Scope
This defense covers only:
- User Management System (authentication, user lifecycle, family context, admin workflows)
- Gamification/Badges integration inside UMS context

Out of scope:
- Other modules (events, documents, charge, tasks business flows not owned by UMS team)

## Core Domain Model
- `User`: identity, status, system role, family role, verification/reset fields, preferences.
- `Family`: household aggregate with join code and creator.
- `FamilyMembership`: current and historical family membership with role and leave timestamp.
- `FamilyInvitation`: invitation lifecycle (`pending`, `accepted`, `expired`).
- `AuditTrail`: immutable activity records for security and traceability.
- `AccountNotification`: queued/sent/failed user-facing notifications.
- `Badge`, `FamilyBadge`, `Score`: gamification layer feeding badge assignment.
- `RoleChangeRequest`: controlled workflow for privileged role transitions.

## Main Flows
1. Registration and verification:
- User registers through portal auth.
- Verification token is generated and queued as account notification.
- Email dispatch happens via Symfony Mailer + Messenger handler.

2. Password reset:
- Reset token generated with TTL.
- Notification queued and delivered asynchronously.
- Reset completion invalidates token and logs audit.

3. Admin user governance:
- Admin list/search/filter/pagination over users.
- Admin detail provides account context, family context, health score, and audit timeline.
- Admin can suspend/reactivate, trigger reset, detach/reinvite family, and export audit CSV.

4. Role change approval:
- Admin submits role change request.
- Request is reviewed through explicit approve/reject endpoints.
- Approval updates system role + security roles and writes audit events.

5. Health score value-add:
- Rule-based scoring service computes 0-100 score with explanation lines.
- Inputs include verification state, membership, profile completeness, login recency, failure history, password recency, notification setup, and suspension penalty.

## Security Model
- Single main firewall with form login and login throttling.
- Access control:
- `/portal/admin/*` restricted to `ROLE_ADMIN`.
- User status checker/subscriber blocks suspended/deleted/unverified access.
- CSRF protection enforced on mutating admin actions.
- Audit trail records security-sensitive actions.

## Integration Choices
1. External API integration:
- Email provider via Symfony Mailer DSN (`MAILER_DSN`) and Messenger handler.
- Brevo-ready setup documented in `docs/email_provider_setup.md`.

2. Bundle integration:
- `knplabs/knp-paginator-bundle` used on admin listing screens for scalable browsing.

## Advanced Features Justification
- Role approval workflow demonstrates controlled privilege escalation logic.
- Health score adds explainable intelligence-like behavior, not just CRUD.
- Audit CSV export provides operational and compliance value.
- Notification delivery states and retries demonstrate robust async architecture.

## Performance and Maintainability
- Pagination caps list payload size and keeps back-office responsive.
- Query-builder repository methods support reuse and extension.
- Separation by service/repository/controller keeps test surface clear.
- Focused tests added for role workflow and CSV export behavior.
