# UMS Demo Scenario

## Goal
Demonstrate an end-to-end UMS flow with advanced features:
- role approval workflow
- account health score
- audit export
- invitation lifecycle
- notifications and badges context

## Seed Data
Load fixtures:

```bash
php bin/console doctrine:fixtures:load
```

Main users:
- `admin_demo@example.com` / `password123`
- `parent_demo@example.com` / `password123`
- `child_demo@example.com` / `password123`
- `suspended_demo@example.com` / `password123`

## Walkthrough (7 Steps)
1. Login as admin and open `Admin > Users > parent_demo`.
2. Review the **Health score** widget and explain each explanation line.
3. In **Role change approval**, create a request from `CUSTOMER` to `ADMIN`.
4. Approve the request and confirm:
   - status changes to `Approved`
   - user system role updates
   - audit timeline gets new entries.
5. Click **Exporter audit CSV** and show downloaded audit evidence.
6. Open `Admin > Invitations` and show pending/accepted/expired invitation records.
7. Open `Admin > Notifications` and show pending/sent/failed deliveries.

## Optional Gamification Step
Open `Admin > Gamification > Badges` and run badge recalculation to show holder updates with seeded score data.
