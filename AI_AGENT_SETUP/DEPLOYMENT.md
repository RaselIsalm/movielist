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

1. In the repository, open **Settings → Pages** and set **Source** to **GitHub Actions**.
2. Open **Settings → Secrets and variables → Actions** and add these repository secrets:
   - `DTLIVE_BASE_URL` — deployed Laravel API URL ending in `/api/`
   - `DTLIVE_API_TOKEN` — API token for that environment
   - `DTLIVE_WEB_DOMAIN_URL` — public web/payment callback URL
3. Push to `main` and open the **Actions** tab. Wait for `Flutter web build and deploy` to finish successfully.

The Pages URL will normally be `https://sbayshop3171-ship-it.github.io/movielist/`. The Flutter API must be deployed separately; the workflow only deploys the Flutter web frontend. For the Laravel server, use the hosting provider's deploy hook or SSH deployment separately, then set the resulting API URL in `DTLIVE_BASE_URL`.

## If the target is not GitHub Pages

Keep the build job and replace only the `deploy-pages` step with the provider's official deployment action. Do not place provider tokens in the repository; store them as GitHub Actions Secrets and require a successful build before deployment.
