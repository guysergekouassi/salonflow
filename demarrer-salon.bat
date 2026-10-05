@echo off
REM Lance la caisse SalonFlow sur ce PC (aucune connexion internet necessaire)
cd /d "%~dp0"

REM Port de la caisse : a changer ici si 8008 est deja pris par une autre application
set PORT=8008

REM Met la base de donnees a jour apres une mise a jour du logiciel (sans effet si deja a jour)
php artisan migrate --force >nul 2>&1

REM Met a jour l'icone des raccourcis SalonFlow deja crees (bureau, menu Demarrer, barre des taches) apres un changement de logo
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ico = (Join-Path (Get-Location).Path 'public\icone-salon.ico') + ',0';" ^
  "$shell = New-Object -ComObject WScript.Shell;" ^
  "$dossiers = @([Environment]::GetFolderPath('Desktop'), [Environment]::GetFolderPath('Programs'), (Join-Path $env:APPDATA 'Microsoft\Internet Explorer\Quick Launch\User Pinned\TaskBar'));" ^
  "$change = $false;" ^
  "foreach ($d in $dossiers) {" ^
  "  $f = Join-Path $d 'SalonFlow.lnk';" ^
  "  if (Test-Path $f) { $l = $shell.CreateShortcut($f); if ($l.IconLocation -ne $ico) { $l.IconLocation = $ico; $l.Save(); $change = $true } }" ^
  "}" ^
  "if ($change) { ie4uinit.exe -show }" >nul 2>&1

REM Demarre le serveur local dans une fenetre reduite, sauf s'il tourne deja
netstat -ano | findstr /r /c:"127.0.0.1:%PORT% .*LISTENING" >nul
if errorlevel 1 (
    start "SalonFlow - serveur (ne pas fermer)" /min php artisan serve --host=127.0.0.1 --port=%PORT%
    REM Laisse 3 secondes au serveur pour demarrer
    timeout /t 3 /nobreak >nul
)

REM Ouvre Chrome en mode application (fenetre a part, avec le logo dans la barre des taches).
REM --kiosk-printing : le ticket part directement sur l'imprimante par defaut, sans boite de dialogue
set CHROME="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%LocalAppData%\Google\Chrome\Application\chrome.exe"

if exist %CHROME% (
    start "" %CHROME% --kiosk-printing --app=http://127.0.0.1:%PORT% --start-maximized
) else (
    start "" http://127.0.0.1:%PORT%
)
