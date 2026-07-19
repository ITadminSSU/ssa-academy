$html = (Invoke-WebRequest -Uri "http://localhost:8000/" -UseBasicParsing -TimeoutSec 90).Content

if ($html -match 'data-page="([^"]+)"') {
    $json = [System.Net.WebUtility]::HtmlDecode($matches[1])
    try {
        $null = $json | ConvertFrom-Json
        Write-Host "data-page JSON: OK ($($json.Length) chars)"
    } catch {
        Write-Host "data-page JSON: INVALID - $($_.Exception.Message)"
    }
} else {
    Write-Host "data-page: NOT FOUND"
}

$scripts = [regex]::Matches($html, 'src="(http[^"]+\.js)"') | ForEach-Object { $_.Groups[1].Value } | Select-Object -Unique
foreach ($src in $scripts) {
    try {
        $r = Invoke-WebRequest -Uri $src -UseBasicParsing -TimeoutSec 30
        Write-Host "JS OK ($($r.StatusCode)): $src"
    } catch {
        Write-Host "JS FAIL: $src - $($_.Exception.Message)"
    }
}
