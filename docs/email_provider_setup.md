# Email Provider Setup (Brevo)

This project sends account notifications through Symfony Mailer and Messenger.
Use Brevo SMTP for a free and production-like setup.

## 1) Create a Brevo account
- Sign up at `https://www.brevo.com/`.
- Verify your sender identity (domain or single sender email).

## 2) Generate SMTP credentials
- In Brevo, open SMTP/API settings.
- Copy:
1. SMTP login
2. SMTP key (password)

## 3) Configure environment variables
Set the following in `.env.local` (recommended) or environment secrets:

```dotenv
MAILER_DSN="smtp://YOUR_BREVO_SMTP_LOGIN:YOUR_BREVO_SMTP_KEY@smtp-relay.brevo.com:587"
MAIL_FROM="no-reply@your-domain.com"
```

## 4) Ensure Messenger worker is running
Notifications are queued and delivered asynchronously.

```bash
php bin/console messenger:consume async -vv
```

If you use Doctrine transport, ensure migrations are up to date before consuming messages.

## 5) Verify end-to-end
1. Request password reset from `/portal/auth/forgot-password`.
2. Check `account_notification` status in admin notifications page.
3. Confirm email is received from `MAIL_FROM`.

## Notes
- `config/packages/mailer.yaml` already points to `%env(MAILER_DSN)%`.
- `config/services.yaml` uses `MAIL_FROM` via `app.notification_from`.
