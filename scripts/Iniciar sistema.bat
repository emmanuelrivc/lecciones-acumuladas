@echo off
title Sistema de Lecciones - Escuela Los Angeles
cd /d "%~dp0app"
echo.
echo  ==============================================
echo   Sistema de Lecciones - Escuela Los Angeles
echo  ==============================================
echo.
echo  El sistema se esta abriendo en su navegador.
echo  NO CIERRE ESTA VENTANA mientras use el sistema.
echo  Para terminar, cierre esta ventana.
echo.
rem Espera 4 segundos a que el sistema arranque y entonces abre el navegador
start "" cmd /c "timeout /t 4 /nobreak >nul & start http://127.0.0.1:8000"
"%~dp0php\php.exe" artisan serve --host=127.0.0.1 --port=8000
pause
