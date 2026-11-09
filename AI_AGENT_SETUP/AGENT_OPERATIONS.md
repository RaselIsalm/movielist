# Agent operating procedure

## 1. Establish the repository root

The working directory is the folder containing `.git`, `AGENTS.md`, `README.md`, and `AI_AGENT_SETUP/`. If the agent was started inside the Flutter app, run `git rev-parse --show-toplevel` and work from that root for all Git commands.

## 2. Synchronize before editing

Run the platform script:

```bash
# macOS/Linux
bash AI_AGENT_SETUP/scripts/sync.sh
```

```powershell
# Windows PowerShell
.\AI_AGENT_SETUP\scripts\sync.ps1
```

The script fetches `origin/main`, fast-forwards the local branch, and prints the status. If there are uncommitted changes, stop and preserve them before pulling. Do not overwrite them with a reset or force checkout.

## 3. Work in the source tree

Flutter source is under `DTLive v2/.../Mainfiles_DTLive/Appcode/yourappname`. Laravel source is under `DTLive v2/.../Mainfiles_DTLive/ServerCode/admin_panel`. Never edit files under `build`, `.dart_tool`, or `vendor`; those are generated dependencies.

## 4. Build configuration

Copy `config/local.example.json` to `config/local.json` and fill in local values. The app reads these compile-time values:

- `DTLIVE_BASE_URL` — Laravel API URL including the `/api/` suffix
- `DTLIVE_API_TOKEN` — local API token
- `DTLIVE_WEB_DOMAIN_URL` — web/payment callback URL

Use the build script so the same procedure works on both platforms. Do not put credentials in Dart files, commits, issues, or chat messages.

## 5. Review and save the work

After editing:

```bash
git diff --check
git status --short
git diff --stat
```

Keep the commit focused. For an authorized completed task:

```bash
git add -A
git commit -m "Describe the completed change"
git push origin main
```

If the task is unfinished, use a clearly named branch or a `WIP:` commit and tell the user exactly what remains. Never force-push `main`.

## 6. Deployment truth

Only a successful GitHub Actions run is a deployment confirmation. A local build or a successful `git push` alone does not prove that production is updated. See `DEPLOYMENT.md` for the required one-time GitHub Pages and secret configuration.
