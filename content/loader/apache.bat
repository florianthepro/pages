@echo off
setlocal enabledelayedexpansion

:: apache-setup.bat
:: Interaktives Menü zum Installieren/Deinstallieren einer portablen Apache+PHP-Umgebung,
:: Konfigurieren des Installationspfads (Default C:\tmp-apache\), Start/Stop des Webservers.
:: Ziel: keine Windows-Services, alles portabel in einem Ordner.
:: Hinweis: Dieses Script lädt ZIPs herunter und entpackt sie mit PowerShell.
::         Prüfe Download-URLs vor Ausführung, führe das Script mit Administratorrechten aus, wenn Ports <1024 verwendet werden.

:: -------------------------
:: Konfiguration / Defaults
:: -------------------------
set "DEFAULT_ROOT=C:\tmp-apache"
set "ROOT=%DEFAULT_ROOT%"

:: Hersteller-Default-Download-URLs (Vorschläge; können beim Install gefragt/überschrieben werden)
set "DEF_APACHE_URL=https://www.apachelounge.com/download/VC15/binaries/httpd-2.4.57-win64-VS16.zip"
set "DEF_PHP_URL=https://windows.php.net/downloads/releases/archives/php-8.2.19-Win32-vs16-x64.zip"

:: Temp-Dateien
set "TMPZIP=%TEMP%\apache_setup_download.zip"
set "LOG=%TEMP%\apache_setup_%RANDOM%.log"

echo [%date% %time%] apache-setup gestartet > "%LOG%"

:: -------------------------
:: Hilfsfunktionen (Batch-Style)
:: -------------------------
:pause_and_return
echo.
pause
goto :menu

:check_installed
:: sets flags AP_INSTALLED and PHP_INSTALLED
set "AP_INSTALLED=0"
set "PHP_INSTALLED=0"
if exist "%ROOT%\Apache24\bin\httpd.exe" set "AP_INSTALLED=1"
if exist "%ROOT%\php\php.exe" set "PHP_INSTALLED=1"
goto :eof

:download_and_extract
:: %1 = URL, %2 = target folder (full path)
set "DL_URL=%~1"
set "DL_TARGET=%~2"
echo [%date% %time%] Download: %DL_URL% >> "%LOG%"
if "%DL_URL%"=="" (
  echo Fehler: Keine URL angegeben.
  exit /b 1
)
if "%DL_TARGET%"=="" (
  echo Fehler: Kein Zielordner angegeben.
  exit /b 2
)

:: Erstelle Zielordner falls nicht vorhanden
if not exist "%DL_TARGET%" mkdir "%DL_TARGET%"

:: Download mit PowerShell (robust gegenüber Redirects)
powershell -NoProfile -Command ^
  "try { Invoke-WebRequest -Uri '%DL_URL%' -OutFile '%TMPZIP%' -UseBasicParsing -ErrorAction Stop; exit 0 } catch { Write-Error 'Download fehlgeschlagen'; exit 1 }"
if errorlevel 1 (
  echo Download fehlgeschlagen. Siehe Log.
  echo [%date% %time%] Download fehlgeschlagen: %DL_URL% >> "%LOG%"
  exit /b 3
)

:: Entpacken mit PowerShell Expand-Archive (überschreibt nicht vorhandene Dateien)
powershell -NoProfile -Command ^
  "try { Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::ExtractToDirectory('%TMPZIP%','%DL_TARGET%'); exit 0 } catch { Write-Error 'Entpacken fehlgeschlagen'; exit 2 }"
if errorlevel 1 (
  echo Entpacken fehlgeschlagen. Siehe Log.
  echo [%date% %time%] Entpacken fehlgeschlagen >> "%LOG%"
  del /f /q "%TMPZIP%" >nul 2>&1
  exit /b 4
)

del /f /q "%TMPZIP%" >nul 2>&1
echo [%date% %time%] Download und Entpacken erfolgreich >> "%LOG%"
exit /b 0

:: -------------------------
:: Menü-Logik
:: -------------------------
:menu
cls
call :check_installed
echo ============================
echo Apache Setup - Hauptmenue
echo ============================
echo Installationspfad: %ROOT%
echo.
if "%AP_INSTALLED%"=="1" (
  echo [1] Apache  - deinstallieren
) else (
  echo [1] Apache  - installieren
)
if "%PHP_INSTALLED%"=="1" (
  echo [2] PHP     - deinstallieren
) else (
  echo [2] PHP     - installieren
)
if "%AP_INSTALLED%"=="1" (
  echo [3] Config  - Pfad & Einstellungen (nur sichtbar wenn Apache installiert)
)
echo [4] Webserver - Start/Stop
echo [5] Pfad aendern (aktuell: %ROOT%)
echo [0] Beenden
echo.
set /p "CHOICE=Waehle eine Option: "

if "%CHOICE%"=="1" goto apache_action
if "%CHOICE%"=="2" goto php_action
if "%CHOICE%"=="3" if "%AP_INSTALLED%"=="1" goto config_menu
if "%CHOICE%"=="4" goto web_action
if "%CHOICE%"=="5" goto change_path
if "%CHOICE%"=="0" goto finish
echo Ungueltige Auswahl.
goto menu

:: -------------------------
:: Apache Install/Uninstall
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
    ) else (
      echo Apache deinstalliert.
      echo [%date% %time%] Apache deinstalliert >> "%LOG%"
    )
  ) else (
    echo Abbruch.
  )
  goto menu
) else (
  echo Apache wird installiert.
  echo Default-Download-URL: %DEF_APACHE_URL%
  set /p "USEDEF=Default-URL verwenden? (Y/n): "
  if /I "%USEDEF%"=="n" (
    set /p "AP_URL=Gib die Apache-ZIP-Download-URL ein: "
  ) else (
    set "AP_URL=%DEF_APACHE_URL%"
  )
  echo Zielordner: %ROOT%
  set /p "CONF=Installieren nach %ROOT% ? (Y/n): "
  if /I "%CONF%"=="n" goto menu
  echo Erstelle Zielordner falls nicht vorhanden...
  if not exist "%ROOT%" mkdir "%ROOT%"
  echo Lade und entpacke Apache...
  call :download_and_extract "%AP_URL%" "%ROOT%"
  if errorlevel 1 (
    echo Installation fehlgeschlagen. Siehe Log: %LOG%
    pause
    goto menu
  )
  :: Manche Apache-Zips entpacken in Unterordner; versuche Standardstruktur zu normalisieren
  if exist "%ROOT%\httpd-*" (
    echo Normalisiere Ordnerstruktur...
  )
  echo Apache installiert (oder entpackt). Bitte pruefe %ROOT% und httpd.conf.
  echo [%date% %time%] Apache installiert von %AP_URL% >> "%LOG%"
  pause
  goto menu
)

:: -------------------------
:: PHP Install/Uninstall
:: -------------------------
:php_action
call :check_installed
if "%PHP_INSTALLED%"=="1" (
  echo PHP ist installiert in %ROOT%\php
  set /p "CONF=Willst du PHP deinstallieren? (y/N): "
  if /I "%CONF%"=="y" (
    echo Entferne PHP-Ordner...
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
  goto menu
) else (
  echo PHP wird installiert.
  echo Default-Download-URL: %DEF_PHP_URL%
  set /p "USEDEF=Default-URL verwenden? (Y/n): "
  if /I "%USEDEF%"=="n" (
    set /p "PHP_URL=Gib die PHP-ZIP-Download-URL ein: "
  ) else (
    set "PHP_URL=%DEF_PHP_URL%"
  )
  echo Zielordner: %ROOT%\php
  set /p "CONF=Installieren nach %ROOT%\php ? (Y/n): "
  if /I "%CONF%"=="n" goto menu
  if not exist "%ROOT%\php" mkdir "%ROOT%\php"
  echo Lade und entpacke PHP...
  call :download_and_extract "%PHP_URL%" "%ROOT%\php"
  if errorlevel 1 (
    echo PHP-Installation fehlgeschlagen. Siehe Log: %LOG%
    pause
    goto menu
  )
  echo PHP installiert in %ROOT%\php
  echo [%date% %time%] PHP installiert von %PHP_URL% >> "%LOG%"
  :: Versuche automatische Apache-Konfiguration für PHP (nur wenn php8apache2_4.dll vorhanden)
  if exist "%ROOT%\php\php8apache2_4.dll" (
    echo Versuche, Apache fuer PHP zu konfigurieren...
    set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
    if exist "%HTTPD_CONF%" (
      echo >> "%HTTPD_CONF%"
      echo # PHP integration added by apache-setup.bat >> "%HTTPD_CONF%"
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
    echo Achtung: php8apache2_4.dll nicht gefunden. PHP als CGI/CLI moeglich, aber Apache-Modul-Integration evtl. nicht automatisch moeglich.
  )
  pause
  goto menu
)

:: -------------------------
:: Config Untermenue
:: -------------------------
:config_menu
echo.
echo ===== Konfiguration =====
echo [1] Zeige aktuellen Pfad
echo [2] Aendere Installationspfad (VORSICHT: keine automatische Verschiebung)
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
  if "%NEW%"=="" goto config_menu
  set "ROOT=%NEW%"
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

echo.
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
:: Startet Apache in neuem Fenster; benutzt httpd.conf falls vorhanden
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
:: Forciertes Beenden falls noetig (nur Prozesse mit Pfadfilter)
powershell -NoProfile -Command ^
  "$root = '%ROOT%';" ^
  "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like ('*' + $root + '*') } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" 2>> "%LOG%"
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
goto menu

:: -------------------------
:: Beenden
:: -------------------------
:finish
echo Beende.
echo [%date% %time%] Script beendet >> "%LOG%"
endlocal
exit /b 0
