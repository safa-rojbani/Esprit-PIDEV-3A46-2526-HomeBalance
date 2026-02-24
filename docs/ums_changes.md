# UMS Changes Log

## 2026-02-22
- Added KNP paginator integration for admin users, memberships, invitations, and notifications.
- Added Account Health Score service and widget on admin user detail page.
- Added role-change approval workflow:
  - `RoleChangeRequest` entity + repository
  - request/approve/reject routes in `AdminUserController`
  - workflow UI in admin user detail view
  - migration `Version20260222110000`
- Added audit CSV export route and `CsvExportService`.
- Added Brevo-ready mail configuration and setup guide.
- Added Doctrine fixtures bundle and UMS/gamification fixtures.
- Updated UMS demo scenario to include health score, role approval, and audit export.
