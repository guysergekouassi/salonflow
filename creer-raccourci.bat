@echo off
REM Cree le raccourci "SalonFlow" (avec le logo du salon) sur le bureau et dans le menu Demarrer.
REM A lancer une seule fois apres l'installation. Si le dossier du projet est deplace, le relancer.
cd /d "%~dp0"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$dossier = (Get-Location).Path;" ^
  "$shell = New-Object -ComObject WScript.Shell;" ^
  "$cibles = @([Environment]::GetFolderPath('Desktop'), [Environment]::GetFolderPath('Programs'));" ^
  "foreach ($c in $cibles) {" ^
  "  $lien = $shell.CreateShortcut((Join-Path $c 'SalonFlow.lnk'));" ^
  "  $lien.TargetPath = Join-Path $dossier 'demarrer-salon.bat';" ^
  "  $lien.WorkingDirectory = $dossier;" ^
  "  $lien.IconLocation = (Join-Path $dossier 'public\logo.ico') + ',0';" ^
  "  $lien.Description = 'Caisse du salon';" ^
  "  $lien.WindowStyle = 7;" ^
  "  $lien.Save();" ^
  "}"

if errorlevel 1 (
    echo Impossible de creer le raccourci.
) else (
    echo Raccourci SalonFlow cree sur le bureau et dans le menu Demarrer.
    echo Clic droit sur l'icone du bureau puis "Epingler a la barre des taches" si vous le souhaitez.
)
pause
