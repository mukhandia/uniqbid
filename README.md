# UniqBid

Laravel 13 + Livewire 4 + Flux UI auction and shop platform.

## Local development

```bash
composer setup                                            # install, .env, key, migrate, npm build
php artisan db:seed                                       # local demo data (test user, catalogue, sample past sales)
composer dev                                              # server + queue + vite
php artisan test --compact                                # run tests
```

## Environments and branches

| Branch    | Deploys to                      | Notes                                              |
|-----------|---------------------------------|----------------------------------------------------|
| `staging` | In-house server (currently Live) | Every push deploys automatically.                  |
| `main`    | Client VPS (future)             | No deploy job yet; add one when moving to the VPS. |

## How deploys work (CI/CD)

Pushing to `staging` runs `.github/workflows/cicd.yml`: tests → build assets → rsync files → `scripts/remote-deploy.sh` on the server.

`scripts/remote-deploy.sh` ships **code and schema only**:

- `composer install --no-dev`
- maintenance mode on
- `php artisan migrate --force`
- `storage:link`, rebuild caches, `queue:restart`
- publish Livewire / Flux assets
- maintenance mode off
- ensure the scheduler cron entry exists

Principles:

- **The pipeline never runs seeders or data-repair commands.** They run on every push and overwrite edits made in the admin (this previously caused auction images, categories and role permissions to "change back" after deploys).
- **Uploads are never touched by deploys.** `storage/app/public`, `storage/app/private` and `public/storage` are listed in `.rsync-exclude`.
- **Deploys are not cancelled mid-run** by a newer push.

## Day-to-day: nothing to run over SSH

Push to `staging` and the pipeline handles everything, including migrations.

### Changing data on the servers

| Need                                                          | How                                                                                   |
|---------------------------------------------------------------|---------------------------------------------------------------------------------------|
| Schema change                                                 | Normal migration — runs automatically on deploy.                                      |
| Data every server needs (new permission, settings field, etc.) | A migration that inserts it once. Also add it to the relevant seeder for new servers. |
| One-off operational fix                                       | Run the artisan command manually over SSH (see below).                                |

## When to SSH into the server

| Situation                                     | Command                                                  |
|-----------------------------------------------|----------------------------------------------------------|
| Setting up a **new** server (e.g. client VPS) | `bash scripts/server-bootstrap.sh /path/to/app` — once, after the first deploy |
| Forgot the admin password                     | `php artisan uniqbid:ensure-admin --reset-password`      |
| Live Auctions shows orphan / broken sales     | `php artisan uniqbid:sync-live-catalogue`                |
| Checking or debugging                         | `php artisan tinker`, `storage/logs/laravel.log`         |

### New server setup

1. Configure the server `.env` (`APP_ENV=production`, database, mail, M-Pesa, and real `ADMIN_EMAIL` / `ADMIN_PASSWORD`).
2. Push to the deploy branch so the pipeline syncs code and runs migrations.
3. Over SSH: `bash scripts/server-bootstrap.sh /path/to/app` (asks for confirmation). It seeds site settings, roles and permissions, CMS content, the shop catalogue, launch auctions, and the admin user.
4. Log in as admin and change the password.

## Seeder safety on servers with real data

| Seeder                             | Behaviour on existing data           | Safe to re-run? |
|------------------------------------|--------------------------------------|-----------------|
| `ShopCatalogueSeeder`              | Insert-only, never modifies rows     | Yes             |
| `SiteSettingSeeder`                | Only creates the row if none exists  | Yes             |
| `ContentSeeder`                    | Skips if content exists, but rewrites one privacy clause | Avoid |
| `LaunchAuctionCatalogueSeeder`     | Overwrites launch auction names, prices, images | **No**  |
| `SyncCatalogueProductImagesSeeder` | Overwrites shop product images       | **No**          |
| `SyncCatalogueTaxonomySeeder`      | Overwrites categories, re-activates them | **No**      |
| `RolePermissionSeeder`             | Resets every role's permissions      | **No**          |
| `UniqBidDemoSeeder` / `db:seed` without `--class` | Adds fake past sales and a test user | **Never on a server** |

Never run `migrate:fresh` on a server with real data: it deletes all users, bids, payments and orders, and uploaded images lose their database references. It is also blocked when `APP_ENV=production`.