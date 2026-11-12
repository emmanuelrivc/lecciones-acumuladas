# Crea la carpeta C:\SistemaLecciones, lista para copiar a una USB.
# Se corre desde C:\Herd\lecciones con:  powershell -ExecutionPolicy Bypass -File scripts\crear-paquete.ps1

$ErrorActionPreference = 'Stop'
$proyecto = Split-Path -Parent $PSScriptRoot
$phpOrigen = "$env:USERPROFILE\.config\herd\bin\php84"
$destino = 'C:\SistemaLecciones'   # fuera de OneDrive, para que no se bloquee ni se suba a la nube

if (-not (Test-Path "$phpOrigen\php.exe")) { throw "No encuentro PHP en $phpOrigen" }
if (-not (Test-Path "$proyecto\public\build\manifest.json")) { throw "Falta compilar el diseno: corra primero  node node_modules\vite\bin\vite.js build" }

Write-Host '1/6 Preparando carpeta vacia...'
if (Test-Path $destino) {
  # Se vacia la carpeta con robocopy: asi no falla aunque alguna ventana la tenga abierta
  $vacio = Join-Path $env:TEMP 'carpeta_vacia_lecciones'
  New-Item -ItemType Directory -Force -Path $vacio | Out-Null
  robocopy $vacio $destino /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
  Remove-Item $destino -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Force -Path "$destino\app" | Out-Null

Write-Host '2/6 Copiando el sistema (puede tardar un par de minutos)...'
robocopy $proyecto "$destino\app" /E /NFL /NDL /NJH /NJS /NP `
  /XD node_modules .git tests .idea .vscode scripts `
  /XF .env *.sqlite *.log .phpunit.result.cache | Out-Null

Write-Host '3/6 Copiando PHP...'
robocopy $phpOrigen "$destino\php" /E /NFL /NDL /NJH /NJS /NP | Out-Null

Write-Host '4/6 Configurando PHP...'
$ini = @"
extension_dir = "ext"
memory_limit = 512M
max_execution_time = 120
date.timezone = America/Costa_Rica
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=fileinfo
extension=curl
extension=gd
extension=zip
extension=intl
"@
Set-Content -Path "$destino\php\php.ini" -Value $ini -Encoding ASCII

Write-Host '5/6 Creando configuracion y base de datos nueva...'
New-Item -ItemType Directory -Force -Path "$destino\app\storage\logs","$destino\app\storage\app\respaldos","$destino\app\storage\framework\cache\data","$destino\app\storage\framework\sessions","$destino\app\storage\framework\views" | Out-Null
$env = @"
APP_NAME="Lecciones Escuela Los Angeles"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_LOCALE=es
LOG_CHANNEL=single
DB_CONNECTION=sqlite
SESSION_DRIVER=database
SESSION_LIFETIME=480
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log
"@
Set-Content -Path "$destino\app\.env" -Value $env -Encoding ASCII
New-Item -ItemType File -Force -Path "$destino\app\database\database.sqlite" | Out-Null

Push-Location "$destino\app"
& "$destino\php\php.exe" artisan key:generate --force
& "$destino\php\php.exe" artisan migrate:fresh --seed --force
Pop-Location

Write-Host '6/6 Copiando los botones de inicio...'
Copy-Item "$PSScriptRoot\Iniciar sistema.bat" $destino
Copy-Item "$PSScriptRoot\Clave de la directora.bat" $destino
Copy-Item "$PSScriptRoot\Crear acceso directo.bat" $destino

Write-Host ''
Write-Host "LISTO. La carpeta quedo en: $destino"
