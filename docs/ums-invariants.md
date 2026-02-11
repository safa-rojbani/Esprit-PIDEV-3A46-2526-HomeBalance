# HomeBalance UMS Lifecycle Invariants

## User ↔ Family invariants
- Each user has **at most one active family** at a time.
- Membership history is preserved: a user can have past memberships with `leftAt` set.
- The `family` field on the user represents the current family context; historical memberships remain in `FamilyMembership`.
- When attaching a user to a family, any prior active membership is closed (`leftAt` set), then a new membership is created.

## Family role and mutation rules
- Only **PARENT** members can mutate family state (invite, refresh join code, manage roles, etc.).
- **CHILD** members can join/participate but cannot mutate family settings.
- **SOLO** indicates no active family membership.

## Invitation and join code rules
- Family join codes expire after a fixed TTL (currently 7 days).
- Email invitations generate a one-time join code with TTL and status tracking.
- Invitations are only valid while status is PENDING and the expiration has not passed.
- If an invitation includes an email, only that email can accept it.
- Invites can be reissued by generating a new join code on the family.

## Status enforcement
- SUSPENDED and DELETED users cannot authenticate.
- SUSPENDED or DELETED users are logged out on their next request.
- Email verification is required before login is allowed.

## Suspension/deletion semantics
- Suspension does not remove family membership; it blocks access only.
- Deletion is a soft-delete status; access is revoked, and user data remains for now.
- Family membership history remains intact even after suspension or deletion.

## Audit and notification expectations
- All lifecycle mutations (status changes, family joins/leaves, resets) must emit audit events.
- Admin-initiated actions must include the acting admin identity in audit payloads.
- Notification delivery must respect user preferences once delivery is implemented.

## Open policy decisions (explicitly pending)
- Define whether a sole PARENT can leave a family without transfer.
- Define hard-delete vs. anonymization policy for DELETED accounts.
- Define invitation revocation semantics (manual revoke vs. expiry only).
