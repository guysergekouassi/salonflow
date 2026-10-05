@echo off
REM Prepare le dossier SalonFlow pour une installation par cle USB, SANS internet sur le PC du salon.
REM A lancer UNE fois sur un PC Windows qui a internet. Il telecharge dans ce dossier :
REM   php\                         PHP 8.3 portable (rien a installer sur le PC du salon)
REM   outils\VC_redist.x64.exe     bibliotheque Microsoft dont PHP a besoin
REM   vendor\                      dependances du logiciel
REM Ensuite, copier tout le dossier sur la cle USB.
setlocal
cd /d "%~dp0"
if not exist outils mkdir outils

set TELECHARGER=powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol='Tls12'; $ProgressPreference='SilentlyContinue'; Invoke-WebRequest -UseBasicParsing

REM 1. PHP portable
if not exist "php\php.exe" (
    echo Telechargement de PHP 8.3...
    %TELECHARGER% -Uri 'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip' -OutFile 'outils\php.zip'"
    if errorlevel 1 goto erreur
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Expand-Archive -Path 'outils\php.zip' -DestinationPath 'php' -Force"
    if errorlevel 1 goto erreur
    del "outils\php.zip"
)

REM 2. Reglages de PHP (extensions SQLite, etc.)
if not exist "php\php.ini" (
    > "php\php.ini" (
        echo extension_dir="ext"
        echo extension=curl
        echo extension=fileinfo
        echo extension=mbstring
        echo extension=openssl
        echo extension=pdo_sqlite
        echo extension=sqlite3
        echo extension=zip
        echo memory_limit=512M
        echo date.timezone=Africa/Abidjan
    )
)

REM 3. Bibliotheque Microsoft Visual C++ (installee par installer.bat si le PC du salon ne l'a pas)
if not exist "outils\VC_redist.x64.exe" (
    echo Telechargement de Visual C++ Redistributable...
    %TELECHARGER% -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile 'outils\VC_redist.x64.exe'"
    if errorlevel 1 goto erreur
)

REM 4. Composer puis dependances du logiciel (dossier vendor)
if not exist "outils\composer.phar" (
    echo Telechargement de Composer...
    %TELECHARGER% -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile 'outils\composer.phar'"
    if errorlevel 1 goto erreur
)

"php\php.exe" -v >nul 2>&1
if errorlevel 1 (
    echo PHP ne demarre pas sur ce PC : installation de Visual C++ Redistributable...
    "outils\VC_redist.x64.exe" /install /passive /norestart
)

echo Installation des dependances...
"php\php.exe" "outils\composer.phar" install --no-interaction
if errorlevel 1 goto erreur

echo.
echo ============================================================
echo  Dossier pret. Copiez maintenant TOUT ce dossier sur la cle USB,
echo  puis sur le PC du salon lancez installer.bat.
echo ============================================================
pause
exit /b 0

:erreur
echo.
echo Echec de la preparation. Verifiez la connexion internet et relancez ce fichier.
pause
exit /b 1
