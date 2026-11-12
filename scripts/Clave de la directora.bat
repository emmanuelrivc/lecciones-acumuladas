@echo off
title Clave de la directora
cd /d "%~dp0app"
"%~dp0php\php.exe" artisan directora:clave
echo.
pause
