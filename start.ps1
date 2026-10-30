# Starts the local MariaDB (if not running) and the PHP dev server on http://localhost:8000
$root = $PSScriptRoot
$mariadb = "C:\Program Files\MariaDB 13.0\bin\mariadbd.exe"

if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
    Write-Host "Starting MariaDB..."
    Start-Process -WindowStyle Hidden -FilePath $mariadb -ArgumentList "--defaults-file=`"$root\.mysql-data\my.ini`"", "--console"
    Start-Sleep -Seconds 5
}

Write-Host "Liberty Office running at http://localhost:8000  (Ctrl+C to stop)"
php -c "$root\php.ini" -S localhost:8000 -t "$root\public"
