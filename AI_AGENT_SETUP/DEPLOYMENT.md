# Automatic deployment

## What is automated

Every push to `main` starts `.github/workflows/flutter-web.yml`. The workflow:

1. Checks out the exact commit that was pushed.
2. Installs Flutter on a clean Ubuntu runner.
3. Creates a temporary build configuration from GitHub Actions Secrets.
4. Runs `flutter pub get` and `flutter build web --release`.
5. Publishes the web build to GitHub Pages.

This means a change pushed from either Mac or Windows follows the same central build and deployment path. The computer used to push does not need to stay online after the push.

## One-time GitHub setup

1. The workflow attempts to enable GitHub Pages automatically. If GitHub does not allow that from Actions, open **Settings → Pages** and set **Source** to **GitHub Actions** manually.
2. Open **Settings → Secrets and variables → Actions** and add these repository secrets:
   - `DTLIVE_BASE_URL` — deployed Laravel API URL ending in `/api/`
   - `DTLIVE_API_TOKEN` — API token for that environment
   - `DTLIVE_WEB_DOMAIN_URL` — public web/payment callback URL
3. Push to `main` and open the **Actions** tab. Wait for `Flutter web build and deploy` to finish successfully.

The Pages URL will normally be `https://sbayshop3171-ship-it.github.io/movielist/`. The Flutter API must be deployed separately; the Pages workflow only deploys the Flutter web frontend.

For automatic Laravel deployment, add a `DEPLOY_HOOK_URL` Actions secret containing the deploy webhook from the hosting provider. `.github/workflows/server-deploy-hook.yml` will call it after every push to `main`. If the provider has no deploy hook, use its official SSH/deployment action instead.

## Cloudflare R2 media delivery

Use the admin storage settings with the R2 access key, secret, bucket name,
endpoint, and region `auto`. The endpoint is the S3 API endpoint, for example
`https://<account-id>.r2.cloudflarestorage.com`; it is not a public media URL.
Keep the R2 bucket private and leave `DTLIVE_MEDIA_PUBLIC_URL` empty to return
same-origin range-streamed media URLs. Set `DTLIVE_MEDIA_PROXY=false` only when
the bucket/custom domain is configured with browser CORS headers and you want
direct short-lived signed URLs instead. If a Cloudflare custom media domain is
configured, set that value as `DTLIVE_MEDIA_PUBLIC_URL` and configure its CORS
policy for the web app origin.

Bulk movie saves enqueue one background job per staged video. Keep the Laravel
database queue worker running on the `media-video` queue so R2 transfers continue
after the admin page returns. On this host, install the repo's
`scripts/run-media-queue-worker.sh` as a per-user cron watchdog and keep its log
under `storage/logs`. The live host uses a per-minute cron watchdog; the worker
restarts its child processes hourly and after crashes.

The admin bulk movie save endpoint queues each completed staged video for
background R2 storage. Production must run a database queue worker on the
`media-video` queue, for example. The repository worker starts three controlled
workers by default; set `MEDIA_QUEUE_WORKERS=1` when the host needs a lower
parallelism limit.

```bash
php artisan queue:work database --queue=media-video --sleep=1 --timeout=21600 --tries=3
```

The admin page polls each upload receipt while the worker continues after the
Save button returns.

After taking a database backup, audit and migrate existing local media with:

```bash
php artisan media:migrate-r2 --dry-run
php artisan media:migrate-r2
```

The command writes a JSON manifest, verifies object size and SHA-256 after each
upload, updates a video's storage type only when its complete media group was
verified, and keeps the local source files as a rollback copy.

## If the target is not GitHub Pages

Keep the build job and replace only the `deploy-pages` step with the provider's official deployment action. Do not place provider tokens in the repository; store them as GitHub Actions Secrets and require a successful build before deployment.
