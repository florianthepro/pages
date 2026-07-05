@echo off
setlocal enabledelayedexpansion

:: apache-setup-final.bat
:: Interaktives Menü zur portablen Installation/Deinstallation von Apache + PHP,
:: Start/Stop des Webservers und Konfiguration.
:: Default-Installationspfad: C:\tmp-apache
:: Hinweis: Dieses Script verwendet PowerShell zum Herunterladen/Entpacken.
:: Prüfe und passe die Download-URLs an deine gewünschten Hersteller/Versionen an.

:: -------------------------
:: Defaults (anpassen falls gewünscht)
:: -------------------------
set "ROOT=C:\tmp-apache"
:: Beispiel-URLs (Platzhalter). Ersetze durch die tatsächlichen Hersteller-Download-Links.
set "APACHE_URL=https://www.apachelounge.com/download/VC15/binaries/httpd-2.4.57-win64-VS16.zip"
set "PHP_URL=https://windows.php.net/downloads/releases/archives/php-8.2.19-Win32-vs16-x64.zip"

set "TMPZIP=%TEMP%\apache_setup_download.zip"
set "LOG=%TEMP%\apache_setup_%RANDOM%.log"

echo [%date% %time%] apache-setup-final gestartet > "%LOG%"

:: -------------------------
:: Hilfsfunktionen
:: -------------------------
:check_installed
set "AP_INSTALLED=0"
set "PHP_INSTALLED=0"
if exist "%ROOT%\Apache24\bin\httpd.exe" set "AP_INSTALLED=1"
if exist "%ROOT%\php\php.exe" set "PHP_INSTALLED=1"
goto :eof

:download_file
:: %1 = URL, %2 = OutFile
set "DL_URL=%~1"
set "DL_OUT=%~2"
if "%DL_URL%"=="" (
  echo Fehler: Keine URL angegeben. >> "%LOG%"
  exit /b 1
)
if "%DL_OUT%"=="" (
  echo Fehler: Kein Ausgabepfad angegeben. >> "%LOG%"
  exit /b 2
)
echo [%date% %time%] Lade %DL_URL% nach %DL_OUT% >> "%LOG%"
powershell -NoProfile -Command "try { Invoke-WebRequest -Uri '%DL_URL%' -OutFile '%DL_OUT%' -UseBasicParsing -ErrorAction Stop } catch { exit 1 }"
if errorlevel 1 (
  echo Download fehlgeschlagen: %DL_URL% >> "%LOG%"
  exit /b 3
)
exit /b 0

:extract_zip
:: %1 = zipfile, %2 = target folder
set "ZIPFILE=%~1"
set "TARGET=%~2"
if not exist "%ZIPFILE%" (
  echo Zipfile %ZIPFILE% nicht gefunden. >> "%LOG%"
  exit /b 1
)
if "%TARGET%"=="" set "TARGET=%~dp0"
if not exist "%TARGET%" mkdir "%TARGET%"
echo [%date% %time%] Entpacke %ZIPFILE% nach %TARGET% >> "%LOG%"
powershell -NoProfile -Command "try { Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::ExtractToDirectory('%ZIPFILE%','%TARGET%') } catch { exit 1 }"
if errorlevel 1 (
  echo Entpacken fehlgeschlagen >> "%LOG%"
  exit /b 2
)
exit /b 0

:: -------------------------
:: Hauptmenü
:: -------------------------
:menu
cls
call :check_installed
echo ============================================
echo Apache Setup - Hauptmenue
echo ============================================
echo Installationspfad: %ROOT%
echo.
if "%AP_INSTALLED%"=="1" (
  echo [1] Apache  - Deinstallieren
) else (
  echo [1] Apache  - Installieren (hersteller-script)
)
if "%AP_INSTALLED%"=="1" (
  if "%PHP_INSTALLED%"=="1" (
    echo [2] PHP     - Deinstallieren
  ) else (
    echo [2] PHP     - Installieren (hersteller-script)
  )
)
if "%AP_INSTALLED%"=="1" (
  echo [3] Config  - Einstellungen (nur sichtbar wenn Apache installiert)
)
echo [4] Webserver - Start / Stop
echo [5] Pfad aendern (aktuell: %ROOT%)
echo [0] Beenden
echo.
set /p "CHOICE=Waehle eine Option: "

if "%CHOICE%"=="1" goto apache_action
if "%CHOICE%"=="2" if "%AP_INSTALLED%"=="1" goto php_action
if "%CHOICE%"=="3" if "%AP_INSTALLED%"=="1" goto config_menu
if "%CHOICE%"=="4" goto web_action
if "%CHOICE%"=="5" goto change_path
if "%CHOICE%"=="0" goto finish
echo Ungueltige Auswahl.
pause
goto menu

:: -------------------------
:: Apache Install / Deinstall
:: -------------------------
:apache_action
call :check_installed
if "%AP_INSTALLED%"=="1" (
  echo Apache ist installiert in %ROOT%\Apache24
  set /p "CONF=Willst du Apache deinstallieren? (y/N): "
  if /I "%CONF%"=="y" (
    echo Stoppe Apache falls aktiv...
    if exist "%ROOT%\Apache24\bin\httpd.exe" (
      "%ROOT%\Apache24\bin\httpd.exe" -k stop 2>nul
      timeout /t 1 >nul
    )
    echo Entferne Apache-Ordner...
    rd /s /q "%ROOT%\Apache24" 2>nul
    if exist "%ROOT%\Apache24" (
      echo Fehler beim Loeschen. Pruefe Berechtigungen.
      pause
    ) else (
      echo Apache deinstalliert.
      echo [%date% %time%] Apache deinstalliert >> "%LOG%"
    )
  ) else (
    echo Abbruch.
  )
  pause
  goto menu
) else (
  echo Apache-Installation (hersteller-script)...
  echo Standard-Download-URL: %APACHE_URL%
  set /p "USEDEF=Default-URL verwenden? (Y/n): "
  if /I "%USEDEF%"=="n" (
    set /p "AP_URL=Gib die Apache-Download-URL (ZIP oder EXE) ein: "
  ) else (
    set "AP_URL=%APACHE_URL%"
  )
  echo Zielordner: %ROOT%
  set /p "CONF=Installieren nach %ROOT% ? (Y/n): "
  if /I "%CONF%"=="n" goto menu
  if not exist "%ROOT%" mkdir "%ROOT%"
  echo Herunterladen...
  call :download_file "%AP_URL%" "%TMPZIP%"
  if errorlevel 1 (
    echo Download fehlgeschlagen. Siehe Log: %LOG%
    pause
    goto menu
  )
  rem Wenn EXE -> ausführen, wenn ZIP -> entpacken
  echo %AP_URL% | findstr /i "\.zip$" >nul
  if errorlevel 0 (
    call :extract_zip "%TMPZIP%" "%ROOT%"
    if errorlevel 1 (
      echo Entpacken fehlgeschlagen. Siehe Log: %LOG%
      del /f /q "%TMPZIP%" >nul 2>&1
      pause
      goto menu
    )
    del /f /q "%TMPZIP%" >nul 2>&1
    echo Apache entpackt nach %ROOT%.
  ) else (
    echo Gefundene Datei ist keine ZIP, versuche als EXE-Installer auszufuehren...
    start /wait "" "%TMPZIP%"
    del /f /q "%TMPZIP%" >nul 2>&1
    echo EXE-Installer ausgefuehrt.
  )
  echo [%date% %time%] Apache-Install ausgefuehrt von %AP_URL% >> "%LOG%"
  pause
  goto menu
)

:: -------------------------
:: PHP Install / Deinstall
:: -------------------------
:php_action
call :check_installed
if "%PHP_INSTALLED%"=="1" (
  echo PHP ist installiert in %ROOT%\php
  set /p "CONF=Willst du PHP deinstallieren? (y/N): "
  if /I "%CONF%"=="y" (
    rd /s /q "%ROOT%\php" 2>nul
    if exist "%ROOT%\php" (
      echo Fehler beim Loeschen. Pruefe Berechtigungen.
    ) else (
      echo PHP deinstalliert.
      echo [%date% %time%] PHP deinstalliert >> "%LOG%"
    )
  ) else (
    echo Abbruch.
  )
  pause
  goto menu
) else (
  echo PHP-Installation (hersteller-script)...
  echo Standard-Download-URL: %PHP_URL%
  set /p "USEDEF=Default-URL verwenden? (Y/n): "
  if /I "%USEDEF%"=="n" (
    set /p "PHP_URL_IN=Gib die PHP-Download-URL (ZIP oder EXE) ein: "
  ) else (
    set "PHP_URL_IN=%PHP_URL%"
  )
  echo Zielordner: %ROOT%\php
  set /p "CONF=Installieren nach %ROOT%\php ? (Y/n): "
  if /I "%CONF%"=="n" goto menu
  if not exist "%ROOT%\php" mkdir "%ROOT%\php"
  echo Herunterladen...
  call :download_file "%PHP_URL_IN%" "%TMPZIP%"
  if errorlevel 1 (
    echo Download fehlgeschlagen. Siehe Log: %LOG%
    pause
    goto menu
  )
  echo %PHP_URL_IN% | findstr /i "\.zip$" >nul
  if errorlevel 0 (
    call :extract_zip "%TMPZIP%" "%ROOT%\php"
    if errorlevel 1 (
      echo Entpacken fehlgeschlagen. Siehe Log: %LOG%
      del /f /q "%TMPZIP%" >nul 2>&1
      pause
      goto menu
    )
    del /f /q "%TMPZIP%" >nul 2>&1
    echo PHP entpackt nach %ROOT%\php.
  ) else (
    echo Gefundene Datei ist keine ZIP, versuche als EXE-Installer auszufuehren...
    start /wait "" "%TMPZIP%"
    del /f /q "%TMPZIP%" >nul 2>&1
    echo EXE-Installer ausgefuehrt.
  )
  echo [%date% %time%] PHP-Install ausgefuehrt von %PHP_URL_IN% >> "%LOG%"
  rem Versuche automatische Apache-Konfiguration falls passende DLL vorhanden
  if exist "%ROOT%\php\php8apache2_4.dll" (
    set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
    if exist "%HTTPD_CONF%" (
      echo # PHP integration added by apache-setup-final.bat >> "%HTTPD_CONF%"
      echo LoadModule php_module "%ROOT%\php\php8apache2_4.dll" >> "%HTTPD_CONF%"
      echo PHPIniDir "%ROOT%\php" >> "%HTTPD_CONF%"
      echo AddHandler application/x-httpd-php .php >> "%HTTPD_CONF%"
      echo DirectoryIndex index.php index.html >> "%HTTPD_CONF%"
      echo [%date% %time%] Apache-Konfig fuer PHP hinzugefuegt >> "%LOG%"
      echo Apache-Konfiguration aktualisiert (php module).
    ) else (
      echo Apache-Konfig nicht gefunden; bitte manuell php in httpd.conf eintragen.
    )
  ) else (
    echo Hinweis: php8apache2_4.dll nicht gefunden; automatische Modul-Integration nicht moeglich.
  )
  pause
  goto menu
)

:: -------------------------
:: Config Untermenue
:: -------------------------
:config_menu
cls
echo ===== Konfiguration =====
echo [1] Zeige aktuellen Pfad
echo [2] Aendere Installationspfad (Achtung: verschiebt keine Dateien)
echo [3] Zeige Log
echo [0] Zurueck
set /p "C=Waehle: "
if "%C%"=="1" (
  echo Aktueller Pfad: %ROOT%
  pause
  goto config_menu
)
if "%C%"=="2" (
  echo Achtung: Aendern des Pfads verschiebt keine Dateien automatisch.
  set /p "NEW=Neuen Pfad eingeben (z.B. C:\tmp-apache): "
  if not "%NEW%"=="" set "ROOT=%NEW%"
  echo Neuer Pfad gesetzt auf %ROOT%
  echo [%date% %time%] Pfad geaendert auf %ROOT% >> "%LOG%"
  pause
  goto menu
)
if "%C%"=="3" (
  if exist "%LOG%" (
    type "%LOG%"
  ) else (
    echo Kein Log vorhanden.
  )
  pause
  goto config_menu
)
goto menu

:: -------------------------
:: Webserver Start/Stop
:: -------------------------
:web_action
call :check_installed
if "%AP_INSTALLED%"=="0" (
  echo Apache ist nicht installiert. Bitte zuerst Apache installieren.
  pause
  goto menu
)
cls
echo Webserver-Aktion:
echo [1] Starten
echo [2] Stoppen
echo [0] Zurueck
set /p "W=Waehle: "
if "%W%"=="0" goto menu
if "%W%"=="1" goto web_start
if "%W%"=="2" goto web_stop
goto web_action

:web_start
set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if not exist "%HTTPD%" (
  echo httpd.exe nicht gefunden in %ROOT%\Apache24\bin
  pause
  goto menu
)
echo Starte Apache...
if exist "%HTTPD_CONF%" (
  start "Apache-Server" cmd /k ""%HTTPD%" -f "%HTTPD_CONF%" -k run"
) else (
  start "Apache-Server" cmd /k ""%HTTPD%" -k run"
)
echo Apache gestartet (Fenster 'Apache-Server' oeffnet sich). Falls das Fenster sofort schliesst, oeffne es und lese Fehlermeldungen.
pause
goto menu

:web_stop
set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
if not exist "%HTTPD%" (
  echo httpd.exe nicht gefunden.
  pause
  goto menu
)
echo Stoppe Apache (sauber)...
"%HTTPD%" -k stop 2>nul
timeout /t 1 >nul
powershell -NoProfile -Command "$root = '%ROOT%'; Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like ('*' + $root + '*') } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" 2>> "%LOG%"
echo Apache gestoppt (sofern aktiv).
pause
goto menu

:: -------------------------
:: Pfad aendern
:: -------------------------
:change_path
echo Aktueller Pfad: %ROOT%
set /p "NEWROOT=Neuen Pfad eingeben (ENTER um abzubrechen): "
if "%NEWROOT%"=="" goto menu
set "ROOT=%NEWROOT%"
echo Neuer Pfad gesetzt auf %ROOT%
echo [%date% %time%] Pfad geaendert auf %ROOT% >> "%LOG%"
pause
goto menu

:finish
echo Beende.
echo [%date% %time%] Script beendet >> "%LOG%"
endlocal
exit /b 0
