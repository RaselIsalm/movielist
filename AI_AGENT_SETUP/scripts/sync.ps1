$ErrorActionPreference = "Stop"

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
if (-not (Test-Path (Join-Path $Root ".git"))) {
    throw "Not a Git working copy: $Root"
}

$Changes = (& git -C $Root status --porcelain)
if ($Changes) {
    & git -C $Root status --short
    throw "Uncommitted changes exist. Save or commit them before pulling."
}

& git -C $Root fetch origin main
& git -C $Root pull --ff-only origin main
& git -C $Root status --short --branch
