# UMS Demo Scenario (Front + Back Office)

## Goal
Provide a consistent, logical demo run for UMS validation: authentication, family onboarding, invitations, admin CRUD, and audit visibility.

## Test Data (Suggested)
- Admin: `admin_demo@example.com` / username `admin_demo`
- Parent: `parent_demo@example.com` / username `parent_demo`
- Child: `child_demo@example.com` / username `child_demo`
- Family name: `Demo Household`
- Invitation email: `invitee_demo@example.com`

## Scenario Steps
1. **Admin setup (Back Office)**
   - Login as admin.
   - Create a new user `parent_demo` with status Active, system role Customer, family role Parent.
   - Create a family `Demo Household` with `parent_demo` as creator.
   - Create a membership linking `parent_demo` to `Demo Household` with role Parent.

2. **Parent onboarding (Front Office)**
   - Login as `parent_demo`.
   - Open Account → Family.
   - Refresh join code if needed.
   - Send invitation to `invitee_demo@example.com`.

3. **Admin review (Back Office)**
   - Open Invitations list; verify new invitation exists.
   - Open Users list; verify `parent_demo` family role and status.
   - Open Audit Trails list; verify entries for family creation/invite actions.

4. **Badge + notification records (Back Office)**
   - Create a badge (e.g., `WELCOME` scope `user`, points `10`).
   - Create a family badge award for `Demo Household` using that badge.
   - Create a notification record for `parent_demo` with key `family_created`, status `PENDING`.

5. **Cleanup**
   - Delete the demo records using the admin CRUD pages.

## Notes
- No HTML or JS validation is required. All checks are done server‑side.
- Use the admin CRUD pages under the Admin menu for full entity coverage.
