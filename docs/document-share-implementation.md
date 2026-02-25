# Document Share (Secure Link) - Implementation Notes

## Goal
Add secure document sharing in HomeBalance with:
- token-based public URL (`/share/{token}`)
- manual expiration chosen by user (minutes / hours / exact date-time)
- optional email send
- WhatsApp and Messenger sharing shortcuts
- no direct file path exposure for shared access

## Added Components

### 1) Entity: `DocumentShare`
File: `src/Entity/DocumentShare.php`

Stores one generated share link:
- `document` (ManyToOne `Document`)
- `family` (ManyToOne `Family`) for multi-tenant integrity
- `sharedBy` (ManyToOne `User`)
- `tokenHash` (SHA-256 hash, unique)
- `recipientEmail` (optional)
- `createdAt`
- `expiresAt`
- `revokedAt` (nullable, reserved for future manual revoke)

Important: raw token is **not** stored in DB; only hash is persisted.

### 2) Repository: `DocumentShareRepository`
File: `src/Repository/DocumentShareRepository.php`

Main helper:
- `findOneByRawToken(string $rawToken): ?DocumentShare`
  - hashes the token with SHA-256
  - finds row by `tokenHash`

### 3) Controller: `DocumentShareController`
File: `src/Controller/ModuleDocuments/FrontOffice/DocumentShareController.php`

#### Route A (authenticated):
- `POST /portal/documents/{id}/share/{galleryId}`
- name: `app_document_share_create`

Responsibilities:
1. Resolve current user + current family (`ActiveFamilyResolver`).
2. Check document belongs to same family (`assertSameFamily`).
3. Check CSRF token.
4. Read expiration mode from form:
   - `minutes` + numeric value
   - `hours` + numeric value
   - `datetime` + exact date-time
5. Validate expiration:
   - must be in future
   - max window is 7 days
6. Generate strong token (`random_bytes(32)` -> base64url).
7. Persist `DocumentShare` with `tokenHash`.
8. Build public URL with raw token:
   - route `app_document_share_public`
9. If optional email provided:
   - send via Symfony Mailer (`Email`)
10. Render `document/show` with `share_result` (URL + buttons).

#### Route B (public):
- `GET /share/{token}`
- name: `app_document_share_public`

Responsibilities:
1. Resolve share row by token hash.
2. Reject if missing / expired / revoked.
3. Validate tenant consistency:
   - `share.family` must match `share.document.family`
4. Resolve actual file from `Document::getFilePath()`.
5. Stream with `BinaryFileResponse` as attachment.
6. Set safe headers:
   - `Content-Disposition: attachment`
   - `X-Robots-Tag: noindex, nofollow, noarchive`

Invalid/expired links render:
- `templates/ModuleDocuments/Public/share_invalid.html.twig`

### 4) UI: document page
File: `templates/ModuleDocuments/FrontOffice/document/show.html.twig`

Added:
- "Partager" entry in action menu
- new share card with form:
  - expiration mode selector
  - duration input OR datetime input
  - optional email input
- post target: `app_document_share_create`
- result section showing:
  - generated secure URL
  - copy button
  - WhatsApp button
  - Messenger/Facebook share button

JS additions:
- toggle duration/date fields by selected mode
- auto default datetime (`now + 30 min`)
- clipboard copy helper

### 5) Migration
File: `migrations/Version20260223143000.php`

Creates table:
- `document_share`

Constraints:
- FK -> `document` (cascade delete)
- FK -> `family` (cascade delete)
- FK -> `user` (`shared_by_id`, cascade delete)
- unique index on `token_hash`

## Exact Runtime Flow (Function Call Scenario)

### Create share link
1. User opens document page (`DocumentController::show`).
2. User submits share form.
3. Symfony routes request to:
   - `DocumentShareController::create()`
4. Method chain:
   - `resolveFamily()`
   - `assertSameFamily()`
   - `resolveActor()`
   - `isCsrfTokenValid()`
   - `resolveExpirationFromRequest()`
   - `generateShareToken()`
   - persist + flush `DocumentShare`
   - optional mail send (`MailerInterface::send`)
5. Controller renders `document/show.html.twig` with `share_result`.

### Open public share link
1. Client hits `/share/{token}`.
2. Symfony routes to:
   - `DocumentShareController::public()`
3. Method chain:
   - `DocumentShareRepository::findOneByRawToken()`
   - expiration/revocation checks
   - family consistency checks
   - filesystem check
   - `resolveDownloadName()`
   - return `BinaryFileResponse`
4. File downloads without exposing direct storage URL in the shared link.

## Security Notes
- token is cryptographically strong and unguessable
- only token hash is persisted
- expiration is mandatory and server-validated
- cross-family leakage blocked by family consistency checks
- shared URL can still be forwarded by recipient until expiration (normal behavior)

## Usage Notes
- Run migration before testing:
  - `php bin/console doctrine:migrations:migrate`
- For email sending, configure `MAILER_DSN` correctly.
