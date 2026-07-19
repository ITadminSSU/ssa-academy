# SSU Academy — UAT Testing Guide

**Smart Sourcing USA** · June 2026

---

## URLs

| Environment | URL |
|-------------|-----|
| Local development | http://127.0.0.1:8000 |
| UAT / staging | Set `APP_URL` on the server (e.g. `https://academy-uat.smartsourcingusa.com`) |

The **login page is the home page** (`/`). `/login` redirects there.

---

## Test accounts

### Local development (`APP_ENV=local`)

These accounts are created by `php artisan db:seed --class=TestUsersSeeder` or the `ssu:reset-test-passwords` command. The login page shows a helper box with these credentials in local mode only.

| Role | Email | Password |
|------|-------|----------|
| Administrator | admin-test@smartsourcingusa.com | password123 |
| Trainer | trainer-test@smartsourcingusa.com | password123 |
| Internal employee | employee-test@smartsourcingusa.com | password123 |
| External learner | Register with a personal (non-company) email | Your choice |

**Create / reset local test users:**

```bash
php artisan db:seed --class=TestUsersSeeder
# or
php artisan ssu:reset-test-passwords
```

### UAT / staging (`APP_ENV=staging` or `production`)

- Do **not** use `password123` on shared servers.
- Create dedicated UAT accounts in **Admin → Users** with strong passwords.
- The login helper box is **hidden** when `APP_ENV` is not `local`.

---

## Role entry points

| Role | After login |
|------|-------------|
| Administrator | `/dashboard/admin` |
| Trainer | `/dashboard/trainer` |
| Internal employee | `/dashboard/internal` |
| External learner | `/dashboard/external` |

---

## UAT test scenarios

### Administrator

1. Log in and open the admin dashboard.
2. Create or edit a course; add lessons and enable **Practical activity** on a document/text lesson if needed.
3. Enroll a test user via **Enrollments → Create** (admin can enroll any user).
4. Confirm system settings load; **Website Direction (RTL)** should not appear.

### Trainer

1. Log in and open the trainer dashboard.
2. Open a course → **Activity Reviews** tab.
3. Grade a learner practical activity submission; confirm the next module unlocks for that learner.

### Internal employee

1. Log in; accept the legal agreement (T&C + NDA) if prompted.
2. Open an assigned course in the course player (`/play-course/lesson/...`).
3. Complete lessons, submit a practical activity, pass a quiz, and download the certificate.

### External learner

1. Register with a non-`@smartsourcingusa.com` email.
2. Browse and enroll in a course (configure payment gateway first for paid courses).
3. Complete the same learning flow as internal employees.

### Cross-cutting

1. Switch **light mode** — text should be readable on login, dashboard, and course player.
2. Confirm the dashboard sidebar stays on the **left** (no RTL layout flip).
3. Log out; confirm `/` shows the login page.

---

## Payment testing (optional)

**Stripe test card:** `4242 4242 4242 4242` (any future expiry, any CVC)

Requires an active payment gateway in admin settings before external paid enrollment will work.

---

## Subscription billing — local test (Phases 1–6)

Use this **before** UAT/Hostinger. You need: XAMPP MySQL running, the app on `http://localhost:8000`, and the **Stripe CLI** for webhooks.

### 1. Start local stack

```bash
# Terminal 1 — start XAMPP MySQL (or Laragon), then:
cd "path/to/SSU Academy"
php artisan serve

# Terminal 2 — frontend assets (if pages look broken)
npm run dev
```

Confirm migrations ran (all six `2026_07_03_*` rows should show **Ran**):

```bash
php artisan migrate:status
```

Preflight check:

```bash
php artisan subscriptions:preflight
```

### 2. Stripe CLI + webhook secret (required for subscribe/cancel)

Install: https://stripe.com/docs/stripe-cli

```bash
stripe login
stripe listen --forward-to localhost:8000/stripe/webhook
```

Copy the `whsec_...` signing secret into `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
SUBSCRIPTION_GRACE_DAYS=3
SUBSCRIPTION_PORTAL_RETURN_URL=http://localhost:8000/dashboard/external/subscriptions
```

Restart `php artisan serve` after editing `.env`, then run preflight again.

### 3. Configure one pilot course

1. Log in as **trainer-test@smartsourcingusa.com** / `password123`
2. Open a course → **Pricing**
3. **Paid** → **Monthly subscription** → set price (e.g. `29.99`) → **Save**
4. Click **Sync to Stripe** (badge should show **Synced**)

Or create test users if missing:

```bash
php artisan db:seed --class=TestUsersSeeder
```

### 4. Run the happy path

1. Register or log in as an **external** learner (not `@smartsourcingusa.com`)
2. Open the subscription course → **Subscribe now** → pay with `4242 4242 4242 4242`
3. Confirm full access in the course player
4. Complete 1–2 lessons
5. **Dashboard → Subscriptions → Manage billing** → cancel in Stripe portal
6. Confirm: completed lessons read-only, new content locked, **Resubscribe** works

### 5. Automated tests (optional)

```bash
composer install
vendor/bin/pest --filter=Subscription
```

---

## UAT server checklist (for IT / admins)

```text
[ ] APP_ENV=staging (not local)
[ ] APP_DEBUG=false
[ ] APP_URL and FRONTEND_URL set to the UAT domain
[ ] Strong passwords for all UAT accounts
[ ] php artisan migrate --force
[ ] php artisan storage:link
[ ] npm ci && npm run build
[ ] SMTP configured (password reset / verification emails)
[ ] SSU_ACADEMY_INSTALLED=1
[ ] /install/* routes redirect away when installed (installer locked)
```

---

## Support

For issues during UAT, note: role, URL, steps to reproduce, and a screenshot. Report to the project admin or development team.
