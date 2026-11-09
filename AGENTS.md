# DTLive agent entrypoint

Every coding agent must start at the repository root and read:

1. `AI_AGENT_SETUP/README.md`
2. `AI_AGENT_SETUP/AGENT_OPERATIONS.md`

Those files define the sync, build, secret handling, commit, push, and deployment process for both macOS and Windows. Do not treat a downloaded ZIP as a working copy; use the Git clone that contains `.git`.

Before editing, run the platform sync script from `AI_AGENT_SETUP/scripts/`. Before committing, inspect `git status`, keep generated output and secrets out of Git, and follow the requested commit/push policy. Never claim that a deployment happened unless the corresponding GitHub Actions run succeeded.
