# Audit Retention Policy

## Purpose
Audit trails are operational logs that can grow indefinitely without retention controls. This policy defines the retention window and pruning approach for HomeBalance.

## Retention Window
- Default retention: **180 days**
- Configured via parameter `app.audit_retention_days`

## Pruning Strategy
- A scheduled command deletes audit records older than the retention window.
- Deletion is permanent (no archival store yet).

## Open Follow-ups
- Decide if archival storage is required before deletion.
- Decide if any audit classes require longer retention (e.g., admin actions).
