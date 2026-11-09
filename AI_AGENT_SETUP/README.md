# DTLive AI agent setup

This folder is the canonical operating manual for an AI agent working on DTLive from either macOS or Windows. The agent should open this folder first, then return to the repository root to edit the actual source.

## Start here

1. Read `AGENT_OPERATIONS.md`.
2. Run `scripts/sync.sh` on macOS/Linux or `scripts/sync.ps1` in Windows PowerShell.
3. Read the user's task and inspect the relevant source files.
4. Create or update `config/local.json` from `config/local.example.json` when a local Flutter build needs API values. This file is ignored and must never be committed.
5. Build with `scripts/build_web.sh` or `scripts/build_web.ps1` when the task changes Flutter code.
6. Review `git diff` and `git status`, then commit and push only the changes belonging to the task.

## Repository contract

- `main` is the shared branch.
- Pull before editing and push after a completed, reviewable change.
- Generated directories (`build`, `.dart_tool`, `vendor`, caches) are recreated on each machine and are not synchronized.
- `.env`, API tokens, service-account files, signing keys, and payment secrets stay local or in GitHub Secrets.
- A push to `main` starts the GitHub Actions workflows in `.github/workflows/`. The Flutter workflow builds a release web artifact and deploys it to GitHub Pages after the one-time Pages and secret setup described in `DEPLOYMENT.md`; the optional Laravel hook workflow deploys the backend when `DEPLOY_HOOK_URL` is configured.
