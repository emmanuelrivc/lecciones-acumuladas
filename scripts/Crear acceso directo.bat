@echo off
title Crear acceso directo
powershell -NoProfile -ExecutionPolicy Bypass -Command "$d=[Environment]::GetFolderPath('Desktop'); $s=(New-Object -ComObject WScript.Shell).CreateShortcut(\"$d\Lecciones Escuela Los Angeles.lnk\"); $s.TargetPath='%~dp0Iniciar sistema.bat'; $s.WorkingDirectory='%~dp0'; $s.WindowStyle=7; $s.IconLocation='%SystemRoot%\System32\shell32.dll,13'; $s.Description='Sistema de Lecciones - Escuela Los Angeles'; $s.Save()"
echo.
echo  Listo. Ya hay un icono en el Escritorio llamado:
echo  "Lecciones Escuela Los Angeles"
echo.
pause
