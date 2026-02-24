# Demo Script (Timed)

## Pre-Demo Setup
1. Start app and DB.
2. Login as admin user.
3. Keep these tabs ready:
- Admin users list
- Admin user detail
- Invitations
- Notifications
- Gamification badges

## 5-Minute Version
1. Open admin users list and mention pagination/filtering.
2. Open one user detail.
3. Explain Health Score card.
4. Create role change request and approve it.
5. Export audit CSV from same page.

## 8-Minute Version
1. Users list with pagination and filters.
2. User detail:
- health score explanation
- role change request lifecycle
- audit timeline
3. Invitations list:
- pending/accepted/expired
4. Notifications list:
- pending/sent/failed states
5. Export audit CSV

## 10-Minute Version
1. Same as 8-minute flow.
2. Add gamification badge recalculation.
3. Show fixture-based scenario reproducibility and docs.

## Live Narration Prompts
- "This is not direct CRUD role mutation. It is approval workflow with traceability."
- "Every sensitive action leaves audit evidence."
- "Health score is explainable: each line contributes to the final score."
- "CSV export proves operational readiness beyond UI."

## Fallback Plan (If something breaks)
1. If role form fails:
- Show routes and controller methods in `AdminUserController`.
2. If mail not delivered:
- Show notification state transitions in admin notifications.
3. If fixtures fail on shared DB:
- Explain FK purge constraints and show fixture classes + tests.
