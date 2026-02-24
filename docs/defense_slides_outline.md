# Defense Slides Outline

## 3-Slide Version
1. Slide 1: Problem and Scope
- Title: "UMS Governance Beyond CRUD"
- Content:
- Scope boundary (UMS + badges only)
- Key challenge: secure user operations at admin scale

2. Slide 2: Architecture and Advanced Features
- Content:
- domain blocks (`User`, `FamilyMembership`, `AuditTrail`, `RoleChangeRequest`, `Notification`)
- feature highlights:
- role approval workflow
- health score
- audit CSV export
- pagination and async notifications

3. Slide 3: Evidence and Outcome
- Content:
- rubric-to-feature mapping
- test evidence
- demo proof screenshots
- closing: security, traceability, operational readiness

## 5-Slide Version
1. Context and scope
2. Architecture and data model
3. Security and governance workflows
4. Value-added intelligence and admin tooling
5. Evidence matrix + test results + conclusion

## Speaker Notes
- Keep each slide to one core claim.
- For each claim, cite one code file and one screenshot.
- Avoid module drift. Re-state out-of-scope modules if questioned.

## Recommended Visuals Per Slide
1. Scope diagram
2. Entity relationship snapshot
3. Role approval flow (request -> approve/reject)
4. Health score widget + CSV preview
5. Table mapping rubric criteria to implemented artifacts
