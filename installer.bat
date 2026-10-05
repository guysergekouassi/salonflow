@echo off
REM Installe SalonFlow sur le PC du salon, SANS internet.
REM Le dossier doit avoir ete prepare avec preparer-cle-usb.bat (ou composer install) puis copie depuis la cle USB.
setlocal
cd /d "%~dp0"

REM PHP portable du dossier s'il existe, sinon le PHP installe sur le PC
set "PHP=php"
if exist "php\php.exe" set "PHP=%CD%\php\php.exe"

if not exist "vendor\autoload.php" (
    echo Le dossier vendor est absent : lancez d'abord preparer-cle-usb.bat sur un PC avec internet.
    goto erreur
)

REM PHP a besoin de Visual C++ Redistributable : on l'installe depuis la cle si besoin
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    if exist "outils\VC_redist.x64.exe" (
        echo Installation de Visual C++ Redistributable...
        "outils\VC_redist.x64.exe" /install /passive /norestart
    )
)
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo PHP ne demarre pas sur ce PC.
    goto erreur
)

REM Fichier de reglages .env et cle de l'application (une seule fois)
if not exist ".env" copy /y ".env.example" ".env" >nul
findstr /r /c:"^APP_KEY=..*" ".env" >nul
if errorlevel 1 (
    "%PHP%" artisan key:generate --force
    if errorlevel 1 goto erreur
)

REM Base de donnees SQLite, tables, catalogue et code PIN de depart (sans effet si deja installe)
if not exist "database\database.sqlite" type nul > "database\database.sqlite"
"%PHP%" artisan migrate --seed --force
if errorlevel 1 goto erreur

echo.
echo ============================================================
echo  SalonFlow est installe. Code PIN de depart : 1234 (a changer).
echo  Ouvrez le fichier .env avec le Bloc-notes pour mettre le nom,
echo  l'adresse et le telephone du salon.
echo ============================================================
echo.
call creer-raccourci.bat
exit /b 0

:erreur
echo.
echo Echec de l'installation.
pause
exit /b 1
