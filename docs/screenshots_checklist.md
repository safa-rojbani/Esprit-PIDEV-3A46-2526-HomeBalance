# Screenshots Checklist

## Capture Rules
- Resolution: at least 1366x768.
- Keep browser zoom at 100%.
- Use a clean dataset (fixtures).
- Save all files in `docs/screenshots/`.

## Required Shots (Order + Filename)
1. `01_admin_users_paginated.png`
- Route: `/portal/admin/users`
- Must show:
- filters
- pager component
- total count line

2. `02_user_detail_health_score.png`
- Route: `/portal/admin/users/{id}`
- Must show:
- health score value
- label
- explanation lines

3. `03_role_change_request_form.png`
- Route: `/portal/admin/users/{id}`
- Must show:
- role change form
- request table

4. `04_role_change_pending.png`
- Route: `/portal/admin/users/{id}`
- Must show:
- pending row
- approve/reject buttons

5. `05_role_change_approved.png`
- Route: `/portal/admin/users/{id}`
- Must show:
- approved status
- updated user role badge

6. `06_audit_export_button.png`
- Route: `/portal/admin/users/{id}`
- Must show export button.

7. `07_audit_csv_opened.png`
- Local file opened in spreadsheet/text editor
- Must show:
- CSV headers
- at least 3 audit rows

8. `08_invitations_states.png`
- Route: `/portal/admin/ums/invitations`
- Must show mixed statuses (`pending`, `accepted`, `expired`).

9. `09_notifications_states.png`
- Route: `/portal/admin/notifications`
- Must show mixed statuses (`PENDING`, `SENT`, `FAILED`).

10. `10_badges_overview.png`
- Route: `/portal/admin/gamification/badges`
- Must show badge holder counts.

## Optional Evidence Shots
1. `11_security_throttling_config.png`
- File view: `config/packages/security.yaml`
2. `12_knp_paginator_config.png`
- File view: `config/packages/knp_paginator.yaml`
3. `13_email_provider_setup_doc.png`
- File view: `docs/email_provider_setup.md`
