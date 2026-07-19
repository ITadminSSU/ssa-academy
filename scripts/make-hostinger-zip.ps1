# Creates ssu-academy-hostinger.zip for upload to Hostinger (testing deploy).
# Run from project root:  powershell -ExecutionPolicy Bypass -File scripts/make-hostinger-zip.ps1

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$zipPath = Join-Path $root "ssu-academy-hostinger.zip"

if (-not (Test-Path (Join-Path $root "vendor"))) {
    Write-Error "Run 'composer install --no-dev' first."
    exit 1
}
if (-not (Test-Path (Join-Path $root "public\build\manifest.json"))) {
    Write-Error "Run 'npm run build' first."
    exit 1
}

$excludeDirs = @('node_modules', '.git', '.idea', '.vscode', 'tests')
$excludeFiles = @('.env', '.env.backup', '.env.production', 'ssu-academy-hostinger.zip')

Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

Get-ChildItem -Path $root -Force | ForEach-Object {
    $name = $_.Name
    if ($excludeDirs -contains $name) { return }
    if ($excludeFiles -contains $name) { return }

    if ($_.PSIsContainer) {
        Get-ChildItem -Path $_.FullName -Recurse -Force -File | ForEach-Object {
            $rel = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
            if ($rel -match '^storage/logs/.*\.log$') { return }
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel) | Out-Null
        }
    } else {
        $rel = $_.FullName.Substring($root.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel) | Out-Null
    }
}

$zip.Dispose()
Write-Host "Created: $zipPath"
Write-Host "Upload and extract on Hostinger, then add .env from .env.hostinger.example"
