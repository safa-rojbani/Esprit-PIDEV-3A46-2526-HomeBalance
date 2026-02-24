# Gamification Scoring Contract (Tasks-Only)

## Scope
This contract defines the **only** score producer for v1: **Task completion**.

## Producer Event
**Event name:** `task.completed`

**Required payload fields**
- `taskId` (int or string)
- `userId` (string)
- `familyId` (int)
- `points` (int)
- `completedAt` (ISO 8601 string)

**Optional payload fields**
- `difficulty` (string)
- `recurrence` (string)
- `validatedBy` (string)

## Idempotency
- Score updates must be idempotent by `(taskId, userId)`.
- If a completion is reprocessed, it must not double-count points.

## Score update rules
- On `task.completed`, increment `Score.totalPoints` by `points`.
- Create `ScoreHistory` entry with `points` and `createdAt`.

## Badge recalculation rules
- After a score update, trigger badge awarding incrementally.
- Avoid full recalculation if a single family is affected.

## Out of scope
- Purchases, chores, budgets, or non-task events.
- Retroactive scoring based on old task history.

## Ownership
- Task event production is owned by the Tasks/Scoring team.
- Gamification consumes the event and awards badges.
