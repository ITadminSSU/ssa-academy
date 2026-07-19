# Smart Sourcing Academy

Smart Sourcing Academy is an enterprise learning platform built with Laravel 12 and React (Inertia), designed for internal employee training and external professional development.

## Features

- Linear course progression (video → assignment → quiz → certification)
- Role-based dashboards for learners, trainers, and administrators
- Payment gateway support (Stripe, PayPal, bank/wire transfer, and more)
- Protected video streaming and legal access gates (T&C + NDA)
- Exam leaderboards, trainer metrics, and candidate pipeline tracking

## Local Development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

## Branding

Platform identity is centralized in `config/branding.php`. Update `APP_NAME` and optional `BRAND_*` environment variables to customize the site name, author, and tagline shown across the UI.
