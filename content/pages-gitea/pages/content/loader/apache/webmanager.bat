@echo off
setlocal enabledelayedexpansion

:: --- Konfigurationsdatei und Default ---
set "CFGFILE=%~dp0webmanager.cfg"
if not exist "%CFGFILE%" (
  echo APACHE_HOME=C:\apache>"%CFGFILE%"
)
for /f "usebackq tokens=1* delims==" %%A in ("%CFGFILE%") do (
  if /i "%%A"=="APACHE_HOME" set "APACHE_HOME=%%B"
)

:mainmenu
cls
echo ============================
echo  Webserver Manager
echo ============================
echo.
:: Prüfe ob Apache installiert (existenz von httpd.exe)
if exist "%APACHE_HOME%\bin\httpd.exe" (
  set "APACHE_INSTALLED=1"
) else (
  set "APACHE_INSTALLED=0"
)

echo [1] Apache  - Installieren/Deinstallieren
if "%APACHE_INSTALLED%"=="1" (
  echo     (Status: installiert in %APACHE_HOME%)
) else (
  echo     (Status: nicht installiert, Default Pfad: %APACHE_HOME%)
)

if "%APACHE_INSTALLED%"=="1" (
  echo [2] PHP     - Installieren/Deinstallieren
)

echo [3] Webserver - starten/stoppen
if "%APACHE_INSTALLED%"=="1" (
  echo [4] Config   - Einstellungen (Startpfad für Webserver)
)
echo [0] Beenden
echo.
set /p "choice=Waehle eine Option: "

if "%choice%"=="1" goto apache
if "%choice%"=="2" if "%APACHE_INSTALLED%"=="1" goto php
if "%choice%"=="3" goto webserver
if "%choice%"=="4" if "%APACHE_INSTALLED%"=="1" goto config
if "%choice%"=="0" goto end
goto mainmenu

:apache
cls
echo Apache Installieren/Deinstallieren
echo.
echo [1] Installieren als Service
echo [2] Deinstallieren Service
echo [0] Zurueck
set /p "a=Auswahl: "
if "%a%"=="1" (
  if not exist "%APACHE_HOME%\bin\httpd.exe" (
    echo Apache-Binaries nicht gefunden in %APACHE_HOME%.
    echo Bitte Apache entpacken oder Pfad in Config anpassen.
    pause
    goto mainmenu
  )
  :: Install als Service (Admin erforderlich)
  echo Installiere Apache Service...
  "%APACHE_HOME%\bin\httpd.exe" -k install -n "Apache2.4"
  sc config "Apache2.4" start= auto >nul 2>&1
  echo Fertig.
  pause
  goto mainmenu
)
if "%a%"=="2" (
  echo Deinstalliere Apache Service...
  "%APACHE_HOME%\bin\httpd.exe" -k uninstall -n "Apache2.4" 2>nul
  sc delete "Apache2.4" >nul 2>&1
  echo Fertig.
  pause
  goto mainmenu
)
goto mainmenu

:php
cls
echo PHP Installieren/Deinstallieren
echo.
echo Hinweis: Dieses Script erwartet ein Hersteller-Installationsskript oder ZIP.
echo [1] Installieren (lokales Installationsskript ausfuehren)
echo [2] Deinstallieren (falls vorhanden)
echo [0] Zurueck
set /p "p=Auswahl: "
if "%p%"=="1" (
  :: Beispiel: suche nach php-install.bat im selben Ordner
  if exist "%~dp0php-install.bat" (
    call "%~dp0php-install.bat"
  ) else (
    echo Kein php-install.bat gefunden im Script-Ordner.
    echo Bitte Hersteller-Skript ablegen oder manuell installieren.
  )
  pause
  goto mainmenu
)
if "%p%"=="2" (
  if exist "%~dp0php-uninstall.bat" (
    call "%~dp0php-uninstall.bat"
  ) else (
    echo Kein php-uninstall.bat gefunden.
  )
  pause
  goto mainmenu
)
goto mainmenu

:webserver
cls
echo Webserver starten/stoppen
echo.
echo [1] Starten
echo [2] Stoppen
echo [0] Zurueck
set /p "w=Auswahl: "
if "%w%"=="1" (
  if exist "%APACHE_HOME%\bin\httpd.exe" (
    echo Starte Apache...
    "%APACHE_HOME%\bin\httpd.exe" -k start
  ) else (
    echo Apache nicht gefunden.
  )
  pause
  goto mainmenu
)
if "%w%"=="2" (
  if exist "%APACHE_HOME%\bin\httpd.exe" (
    echo Stoppe Apache...
    "%APACHE_HOME%\bin\httpd.exe" -k stop
  ) else (
    echo Apache nicht gefunden.
  )
  pause
  goto mainmenu
)
goto mainmenu

:config
cls
echo Config Einstellungen
echo.
echo Aktueller Apache Pfad: %APACHE_HOME%
echo [1] Pfad aendern
echo [2] Pfad auf Default setzen (C:\apache)
echo [0] Zurueck
set /p "c=Auswahl: "
if "%c%"=="1" (
  set /p "new=Neuer Pfad: "
  if not "%new%"=="" (
    set "APACHE_HOME=%new%"
    >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
    echo Gespeichert.
  )
  pause
  goto mainmenu
)
if "%c%"=="2" (
  set "APACHE_HOME=C:\apache"
  >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
  echo Zurueckgesetzt.
  pause
  goto mainmenu
)
goto mainmenu

:end
echo Beende...
endlocal
exit /b 0
