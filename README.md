# API Bank

API Bank is a Laravel application for connecting customer bank accounts, exposing balance and transaction APIs, handling wallet recharge, and managing API subscription packages.

This repository contains:

- API Bank v2.0 Laravel application at the repository root.
- Legacy APIBank v1.0 PHP endpoints in `public/v1.0`.
- Package policy in `API_PACKAGE_POLICY.md`.

## Runtime

- PHP 8.3 for the Laravel application.
- MySQL or MariaDB.
- Node.js/npm only when rebuilding frontend assets.
- PHP 7.4 compatible runtime for the legacy `public/v1.0` endpoints if the server keeps them isolated.

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan optimize:clear
```

Set the database, Google OAuth, captcha, RSA, and MBBank encrypt service variables in `.env`.

Important variables:

```env
APP_URL=https://apibank.com.vn
DB_DATABASE=apibank
DB_USERNAME=apibank
DB_PASSWORD=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://apibank.com.vn/auth/social/google/callback

VCB_CAPTCHA_API_URL=https://captcha.apibank.com.vn/api/vcb
VCB_CAPTCHA_API_KEY=
MBBANK_CAPTCHA_API_URL=https://captcha.apibank.com.vn/api/mbb
MBBANK_CAPTCHA_API_KEY=
MBBANK_ENCRYPT_URL=http://127.0.0.1:3197/encrypt

VCB_DEFAULT_PUBLIC_KEY=
VCB_CLIENT_PUBLIC_KEY=
VCB_CLIENT_PRIVATE_KEY=
MBBANK_AUTHORIZATION=
```

Do not commit `.env`, private RSA keys, account passwords, database dumps, logs, or aaPanel backups.

## Legacy v1.0

Old public API links are preserved through `public/v1.0`:

```text
/api/historyacb/{token}
/api/historyacbbalance/{token}
/api/historyvietcombank/{token}
/api/historyvietcombankbalance/{token}
/api/historymbbank/{token}
/api/historymbbankbalance/{token}
/api/listbank/{token}
```

Recommended Nginx behavior is to rewrite those old paths to `public/v1.0/api/*.php`, while the Laravel application serves the current product.

The legacy folder has its own `.env.example` because the old PHP code loads environment variables from `public/v1.0/.env`.

## API v2.0

Current API aliases live under `/v2`:

```text
/v2/acb/balance/{token}
/v2/acb/transhistory/{token}
/v2/vcb/balance/{token}
/v2/vcb/transhistory/{token}
/v2/vpbank/balance/{token}
/v2/vpbank/transhistory/{token}
/v2/techcombank/balance/{token}
/v2/techcombank/transhistory/{token}
/v2/mbbank/balance/{token}
/v2/mbbank/transhistory/{token}
```

Existing `/v1/...` routes are still kept by the Laravel app for compatibility with the app.pro.vn implementation.

## Cron

Configure recharge scan jobs on the production server:

```text
ACB recharge scan: every 1 minute
VCB recharge scan: every 3 minutes
VPBank recharge scan: every 3 minutes
Techcombank recharge scan: every 3 minutes
MBBank recharge scan: every 3 minutes
Techcombank keep-alive refresh: every 3 minutes
```

Use `https://apibank.com.vn/cron/...` URLs after the domain migration.

## Package Rules

The API package rules are documented in `API_PACKAGE_POLICY.md`.

Summary:

- Same-package renewal adds time to the current expiry and does not refund the same package.
- Upgrade applies immediately and charges the difference after crediting the remaining value of the old package.
- Downgrade is scheduled for renewal and does not refund mid-cycle.

## Production Notes

The current production migration moved the former `app.pro.vn` Laravel API app to `apibank.com.vn`, copied the old APIBank data into legacy-prefixed tables, and left `app.pro.vn` as a blank site.

Keep old v1.0 links alive unless all legacy users have migrated.
