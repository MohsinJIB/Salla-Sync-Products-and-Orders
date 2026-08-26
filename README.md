# Salla Demo Store Sync

A Laravel application for connecting a Salla merchant, receiving signed Salla
webhooks, and synchronizing the merchant's products and orders into a local
database.

The project uses Salla OAuth Easy Mode by default. When a merchant installs the
app, Salla sends an `app.store.authorize` event to the application's webhook.
The app verifies the webhook signature, stores the merchant authorization, and
can then queue complete product and order synchronizations through the Salla
Admin API.

## What the project currently does

- Receives Salla events at `POST /webhook`.
- Verifies `X-Salla-Signature` with HMAC-SHA256 and the raw request body.
- Handles Easy Mode `app.store.authorize` events and stores merchant OAuth
  tokens in the database.
- Fetches every page of products and orders from the Salla Admin API.
- Upserts products and orders per merchant, making repeated syncs safe.
- Runs synchronization in unique, retryable Laravel queue jobs.
- Provides commands for syncing all authorized merchants or one local user.

## Technology

- PHP 8.3 or newer
- Laravel 13
- Composer 2
- Node.js and npm
- MySQL by default
- Vite and Tailwind CSS
- Laravel database queues

## How it works

1. A merchant installs the app on a Salla demo store.
2. Salla sends `app.store.authorize` to `POST /webhook`.
3. The app verifies the signature and saves the merchant's access and refresh
   tokens.
4. A sync command adds a unique job to the database queue.
5. A queue worker downloads the merchant's products or orders and upserts them
   into the local database.

## Prerequisites

Install the following before starting:

- PHP 8.3+ with the extensions required by Laravel and your database driver
- Composer 2
- Node.js and npm
- A running MySQL server and an empty database
- A Salla Partners account, a Salla app, and access to a demo store
- A public HTTPS URL for the local or deployed application

Salla cannot deliver webhooks to `localhost`. During local development, expose
the Laravel server through an HTTPS tunnel and use that public URL everywhere
Salla expects the application domain.

## Local installation

Clone the repository and enter its actual Git root:

```powershell
git clone <repository-url>
cd laravel-starter-kit
```

Create the local environment file. In PowerShell:

```powershell
Copy-Item .env.example .env
```

On macOS or Linux, use `cp .env.example .env`.

Create an empty MySQL database, then configure the database connection in
`.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=salla_test_app
DB_USERNAME=root
DB_PASSWORD=
```

Use credentials appropriate for your own local database. After the database is
ready, install the application:

```powershell
composer run setup
```

The setup script installs PHP and JavaScript dependencies, generates a unique
Laravel application key, runs the migrations, and builds the frontend assets.

To perform those steps manually instead:

```powershell
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Salla environment configuration

Every developer should use their own Salla app credentials. Do not copy another
developer's `.env`, OAuth tokens, database, or webhook secret.

Set the real public HTTPS URL and the values from the developer's Salla Partner
app:

```dotenv
APP_URL=https://your-public-domain.example

SALLA_OAUTH_CLIENT_ID=
SALLA_OAUTH_CLIENT_SECRET=
SALLA_WEBHOOK_SECRET=
SALLA_AUTHORIZATION_MODE=easy
```

After changing environment values while Laravel is running, clear cached
configuration:

```powershell
php artisan config:clear
```

`SALLA_OAUTH_CLIENT_REDIRECT_URI` is only used by Custom Mode. This project
defaults to Easy Mode, where the Partner Portal's Salla-owned
`easy_redirect_url` must be used instead of the application's `/oauth/callback`
URL.

## Configure the app in Salla Partners

Create or use a Salla app controlled by the developer who will run the code.
Using a separate app for each developer prevents one developer's webhook URL or
secret changes from breaking another developer's environment.

In the Salla Partner configuration:

1. Keep the authorization mode set to **Easy Mode**.
2. Set the redirect URL to the exact `easy_redirect_url` shown by Salla.
3. Set the webhook URL to `https://your-public-domain.example/webhook`.
4. Explicitly set the webhook security strategy to **Signature**.
5. Copy the current webhook secret into `SALLA_WEBHOOK_SECRET` without
   committing it.
6. Grant the minimum required resource scopes:
   - `products`: read
   - `orders`: read
7. Ensure the authorization includes `offline_access` so Salla issues a refresh
   token.
8. Subscribe only to the additional store events the app actually needs. Salla
   automatically delivers `app.*` lifecycle events, including
   `app.store.authorize`, to the configured webhook URL.

The configured public domain, running deployment or tunnel, webhook URL,
security strategy, and local secret must all agree. A stale secret or tunnel URL
will cause every webhook to fail.

## Run the application

The recommended development command starts Laravel, the queue listener, the log
viewer, and Vite together:

```powershell
composer run dev
```

You can also run the main processes in separate terminals:

```powershell
php artisan serve
php artisan queue:work
npm run dev
```

If using a tunnel, point it at the Laravel server, normally
`http://127.0.0.1:8000`.

## Install on a Salla demo store

Publishing the code does not install or authorize the app automatically. To test
another developer's clone:

1. Start their configured application and public HTTPS tunnel or deployment.
2. Confirm the Salla app's webhook URL points to that running environment.
3. Generate a fresh demo-store `install_url` for that Salla app.
4. Open the generated URL and install the app on the selected demo store.
5. Use the link promptly; demo-store install links are time-limited.
6. Confirm the `app.store.authorize` webhook returns HTTP 200.
7. Confirm the local `users` and `oauth_tokens` tables contain the authorized
   merchant record. Do not print or share the stored token values.

Before publication, use the generated demo-store `install_url`. The public Salla
App Store installation link will not install an unpublished app.

## Synchronize products and orders

The commands dispatch jobs for every local user who has a stored Salla token:

```powershell
php artisan salla:sync-products
php artisan salla:sync-orders
```

To sync one authorized local user:

```powershell
php artisan salla:sync-products --user=1
php artisan salla:sync-orders --user=1
```

These commands enqueue work; they do not perform the API synchronization in the
command process. Keep `composer run dev` or a queue worker running:

```powershell
php artisan queue:work
```

Completed jobs write counts—not OAuth tokens or API payloads—to
`storage/logs/laravel.log`.

## Demo-store verification checklist

- The app installs through a fresh demo-store install URL.
- Salla's webhook activity log shows HTTP 200 for `app.store.authorize`.
- Invalid or unsigned webhook requests return HTTP 401.
- An authorized merchant exists locally without exposing its tokens.
- Product sync populates the `products` table.
- Order sync populates the `orders` table after placing a demo order.
- Repeating either sync updates existing rows instead of duplicating them.
- The queue has no failed synchronization jobs.
- Application logs and screenshots contain no credentials or customer data.

## Useful commands

| Command | Purpose |
| --- | --- |
| `composer run setup` | Install dependencies, generate the app key, migrate, and build |
| `composer run dev` | Run the server, queue listener, logs, and Vite |
| `composer run test` | Run the PHPUnit test suite |
| `npm run build` | Build production frontend assets |
| `php vendor/bin/pint --test` | Check PHP formatting without modifying files |
| `php artisan migrate` | Apply database migrations |
| `php artisan queue:work` | Process queued synchronization jobs |
| `php artisan queue:failed` | List failed queue jobs |
| `php artisan salla:sync-products` | Queue product syncs |
| `php artisan salla:sync-orders` | Queue order syncs |

## Project structure

| Path | Responsibility |
| --- | --- |
| `app/Actions` | Event-specific handlers selected from each Salla event name |
| `app/Http/Controllers/WebhookController.php` | Dispatches verified webhook events |
| `app/Http/Requests/WebhookRequest.php` | Validates payloads and HMAC signatures |
| `app/Services/SallaAuthService.php` | OAuth provider, authenticated API calls, and token refresh |
| `app/Services/SallaProductSyncService.php` | Paginated product synchronization and upserts |
| `app/Services/SallaOrderSyncService.php` | Paginated order synchronization and upserts |
| `app/Jobs` | Unique, retryable background synchronization jobs |
| `app/Console/Commands` | CLI entry points for requesting syncs |
| `database/migrations` | Users, tokens, queues, products, and orders schema |
| `tests` | Unit and feature tests, including webhook signature checks |

## Credential and repository safety

The local `.env` contains credentials and must never be committed. The repository's
`.gitignore` excludes `.env`, local database files, logs, caches, dependencies,
and generated assets.

Before every public push, verify:

```powershell
git status --ignored --short
git ls-files .env
```

The first command should show `!! .env`; the second command should print nothing.
Never run `git add -f .env`, commit database exports, or manually upload `.env`
through GitHub's website.

The `oauth_tokens` table contains access and refresh tokens. Treat the database
and its backups as secrets even though they are not stored in Git.

If a real secret is ever committed, removing it in a later commit is not enough:
rotate the credential immediately and remove it from the repository history.

## Troubleshooting

### `vite` is not recognized

Install the JavaScript dependencies before running a build or development server:

```powershell
npm install
```

### Webhooks return HTTP 401

- Confirm the Partner app uses the **Signature** security strategy.
- Confirm `SALLA_WEBHOOK_SECRET` exactly matches the current Partner Portal
  secret.
- Run `php artisan config:clear` after changing `.env`.
- Do not calculate signatures from parsed or reformatted JSON; the app verifies
  the exact raw request body.

### Webhooks do not arrive

- Confirm `APP_URL` and the Partner webhook URL use the current public HTTPS
  domain.
- Confirm the application and tunnel are running.
- Generate a fresh install URL if the previous one expired.
- Check Salla's webhook activity log for delivery attempts and response codes.
- Reinstall the app after correcting its connection settings.

### Sync reports that no authorized users were found

The `app.store.authorize` event has not created a valid local authorization yet.
Check the demo-store installation and webhook delivery before running a sync.

### Sync command succeeds but no data appears

- Make sure a queue worker is running.
- Run `php artisan queue:failed` and inspect `storage/logs/laravel.log`.
- Confirm the app has read access to the relevant Salla resource.
- Confirm the merchant token is current; reinstall the app if its token chain can
  no longer be refreshed.

### Database connection fails during setup

Create the database first, verify the `DB_*` values in `.env`, and then run:

```powershell
php artisan migrate
```

## Production readiness

This repository is suitable for development and demo-store testing, but the
following hardening is required before production use:

- Encrypt stored OAuth access and refresh tokens at rest.
- Serialize token refreshes with a per-merchant distributed lock. Salla refresh
  tokens are single-use, so concurrent refreshes can invalidate the token chain.
- Add webhook idempotency so duplicate deliveries cannot repeat side effects.
- Keep webhook handlers fast and move slow processing to queues.
- Use durable database and queue infrastructure with backups and monitoring.
- Restrict scopes and trusted server IPs to the minimum required.
- Define data retention and deletion behavior for merchant and customer data.

## Resources

- [Salla Partners Portal](https://portal.salla.partners)
- [Salla webhook documentation](https://docs.salla.dev/421119m0.md)
- [Salla app events](https://docs.salla.dev/421413m0.md)
- [Salla Admin API documentation](https://docs.salla.dev)
- [Laravel documentation](https://laravel.com/docs)

## License

This project is based on the Salla Laravel starter kit and is distributed under
the MIT license declared in `composer.json`.
