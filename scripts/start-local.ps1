# Start SSU Academy locally (Windows)
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "Checking MySQL..." -ForegroundColor Cyan
try {
    php -r "new PDO('mysql:host=127.0.0.1;port=3306;dbname=ssu_academy','root',''); echo 'MySQL OK';"
    Write-Host ""
} catch {
    Write-Host "MySQL is NOT running. Start XAMPP/Laragon/MySQL first." -ForegroundColor Red
    Write-Host "The site will show a blank page without the database." -ForegroundColor Yellow
    Write-Host ""
}

php artisan config:clear | Out-Null

Write-Host "Starting server at http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop." -ForegroundColor Gray
php artisan serve --host=127.0.0.1 --port=8000
