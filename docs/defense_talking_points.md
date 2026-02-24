# Defense Talking Points

## 2-Minute Pitch
Our contribution is a complete UMS governance layer with advanced controls, not just CRUD.
We implemented:
- secure admin workflows for user lifecycle and family membership operations
- asynchronous notification delivery through mailer + messenger
- paginated admin operations for scalability
- a controlled role change approval workflow with auditability
- explainable account health scoring
- audit CSV export for evidence and compliance

This is aligned with a production mindset: traceability, controlled privilege change, and operational tooling.

## 5-Minute Deep Dive
1. Problem:
- UMS often stops at user CRUD and login. We extended it to operational governance.

2. Architecture:
- Core entities: `User`, `FamilyMembership`, `FamilyInvitation`, `AuditTrail`, `AccountNotification`, `RoleChangeRequest`.
- Async notification flow through `NotificationService` and message handler.

3. Advanced implementations:
- Role change approval is explicit request/review, not direct mutation.
- Health score combines security and profile signals into explainable output.
- CSV export makes audit records consumable outside the app.

4. Reliability and scale:
- KNP paginator on admin list endpoints.
- tests added for role workflow and export.

5. Outcome:
- Stronger security posture, better admin productivity, and measurable defense evidence.

## Likely Jury Questions and Answers
1. Why not directly edit user role?
- Direct role mutation is risky. The request/approve process creates traceable privilege governance.

2. Is your "AI" really AI?
- It is explainable rule-based intelligence: deterministic scoring with transparent reasons, suitable for governance decisions.

3. How do you ensure email reliability?
- Notifications are queued and tracked with statuses (`PENDING`, `SENT`, `FAILED`, `SKIPPED`) and attempts.

4. How do you prove security maturity?
- Login throttling, CSRF on mutations, admin route isolation, user status enforcement, and audit trail evidence.

5. Why pagination matters in defense?
- It demonstrates readiness for real data volume and avoids memory-heavy admin list rendering.

6. What if fixtures fail on shared DB?
- On a mixed legacy DB with cross-module constraints, purge strategy must be tuned; feature code and fixture definitions are still valid and testable in clean schema/test environments.
