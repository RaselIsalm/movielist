$ErrorActionPreference = "Stop"

$Root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
$ConfigFile = if ($args.Count -gt 0) { (Resolve-Path $args[0]).Path } else { Join-Path $Root "AI_AGENT_SETUP\config\local.json" }
if (-not (Test-Path $ConfigFile)) {
    throw "Missing $ConfigFile. Copy AI_AGENT_SETUP\config\local.example.json to config\local.json and fill in local values."
}

$AppDir = Get-ChildItem -Path (Join-Path $Root "DTLive v2") -Directory -Recurse |
    Where-Object { $_.FullName -like "*\Mainfiles_DTLive\Appcode\yourappname" } |
    Select-Object -First 1
if (-not $AppDir) {
    throw "Flutter app directory was not found."
}

Set-Location $AppDir.FullName
& flutter pub get
& flutter build web --release "--dart-define-from-file=$ConfigFile"
