@echo off
setlocal enabledelayedexpansion

:: -------------------------
:: Webserver Manager
:: -------------------------
:: Konfigurationsdatei
set "CFGFILE=%~dp0webmanager.cfg"

:: Default Konfiguration anlegen falls nicht vorhanden
if not exist "%CFGFILE%" (
  echo APACHE_HOME=C:\apache>"%CFGFILE%"
)

:: Konfig laden
for /f "usebackq tokens=1* delims==" %%A in ("%CFGFILE%") do (
  if /i "%%A"=="APACHE_HOME" set "APACHE_HOME=%%B"
)

:: Admin-Prüfung (einfacher Test)
net session >nul 2>&1
if %errorlevel%==0 (
  set "IS_ADMIN=1"
) else (
  set "IS_ADMIN=0"
)

:: Hilfsfunktion: Refresh Variablen
:refresh_vars
if exist "%APACHE_HOME%\bin\httpd.exe" (
  set "APACHE_INSTALLED=1"
) else (
  set "APACHE_INSTALLED=0"
)
sc query "Apache2.4" >nul 2>&1
if %errorlevel%==0 (
  set "APACHE_SERVICE=1"
) else (
  set "APACHE_SERVICE=0"
)
if exist "%~dp0php-install.bat" (
  set "PHP_INSTALLER_PRESENT=1"
) else (
  set "PHP_INSTALLER_PRESENT=0"
)
goto :eof

call :refresh_vars

:mainmenu
cls
echo =========================================
echo            Webserver Manager
echo =========================================
echo.
:: Dynamische Anzeige für Apache Option
if "%APACHE_INSTALLED%"=="1" (
  set "APACHE_LABEL=Apache - Deinstallieren"
) else (
  set "APACHE_LABEL=Apache - Installieren"
)
echo [1] %APACHE_LABEL%
if "%APACHE_INSTALLED%"=="1" (
  echo     ^> Installationspfad: %APACHE_HOME%
  echo [2] PHP - Installieren/Deinstallieren
)
echo [3] Webserver - Starten/Stoppen
if "%APACHE_INSTALLED%"=="1" (
  echo [4] Config - Einstellungen (Startpfad)
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
echo ============================
echo Apache Installation
echo ============================
if "%APACHE_INSTALLED%"=="1" (
  echo Apache ist installiert in %APACHE_HOME%.
  echo [1] Deinstallieren
  echo [0] Zurueck
  set /p "a=Auswahl: "
  if "%a%"=="1" (
    if "%IS_ADMIN%"=="0" (
      echo Deinstallation benoetigt Administratorrechte.
      pause
      goto mainmenu
    )
    :: Wenn Hersteller-Deinstallationsskript vorhanden, ausfuehren
    if exist "%~dp0apache-uninstall.bat" (
      echo Fuehre apache-uninstall.bat aus...
      call "%~dp0apache-uninstall.bat"
    ) else (
      echo Entferne Apache Service falls vorhanden...
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        "%APACHE_HOME%\bin\httpd.exe" -k uninstall -n "Apache2.4" 2>nul
      )
      sc delete "Apache2.4" >nul 2>&1
      echo Loesche Verzeichnis %APACHE_HOME% ? (y/n)
      set /p "delconfirm=>"
      if /i "%delconfirm%"=="y" (
        rd /s /q "%APACHE_HOME%" 2>nul
        echo Verzeichnis geloescht.
      ) else (
        echo Verzeichnis nicht geloescht.
      )
    )
    call :refresh_vars
    pause
    goto mainmenu
  )
  goto mainmenu
) else (
  echo Apache ist nicht installiert.
  echo [1] Installieren (verwende apache-install.bat oder apache.zip)
  echo [0] Zurueck
  set /p "a=Auswahl: "
  if "%a%"=="1" (
    :: Versuche Hersteller-Skript
    if exist "%~dp0apache-install.bat" (
      echo Fuehre apache-install.bat aus...
      call "%~dp0apache-install.bat"
      call :refresh_vars
      pause
      goto mainmenu
    )
    :: Wenn ZIP vorhanden, entpacken nach APACHE_HOME
    if exist "%~dp0apache.zip" (
      echo Entpacke apache.zip nach %APACHE_HOME% ...
      if not exist "%APACHE_HOME%" mkdir "%APACHE_HOME%"
      powershell -noprofile -command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory('%~dp0apache.zip','%APACHE_HOME%')"
      if %errorlevel%==0 (
        echo Entpacken erfolgreich.
      ) else (
        echo Entpacken fehlgeschlagen.
      )
    ) else (
      echo Keine Installationsdateien gefunden.
      echo Lege apache-install.bat oder apache.zip in den Script-Ordner.
      pause
      goto mainmenu
    )
    :: Versuche Service-Installation wenn httpd.exe vorhanden
    if exist "%APACHE_HOME%\bin\httpd.exe" (
      if "%IS_ADMIN%"=="0" (
        echo Hinweis: Service-Installation benoetigt Administratorrechte.
        echo Starte Apache manuell mit "%APACHE_HOME%\bin\httpd.exe" -k start
      ) else (
        echo Installiere Apache als Service...
        "%APACHE_HOME%\bin\httpd.exe" -k install -n "Apache2.4"
        sc config "Apache2.4" start= auto >nul 2>&1
        echo Service installiert.
      )
    )
    call :refresh_vars
    pause
    goto mainmenu
  )
  goto mainmenu
)

:php
cls
echo ============================
echo PHP Installation
echo ============================
echo Hinweis: PHP wird nur angeboten wenn Apache installiert ist.
echo.
if "%APACHE_INSTALLED%"=="0" (
  echo Apache nicht installiert. PHP-Option nicht verfuegbar.
  pause
  goto mainmenu
)
:: Prüfe ob PHP bereits installiert (einfacher Check: php.exe in APACHE_HOME\php oder %~dp0\php)
set "PHP_FOUND=0"
if exist "%APACHE_HOME%\php\php.exe" set "PHP_FOUND=1"
if exist "%~dp0php\php.exe" set "PHP_FOUND=1"
if exist "%~dp0php.exe" set "PHP_FOUND=1"

if "%PHP_FOUND%"=="1" (
  echo PHP ist installiert.
  echo [1] Deinstallieren
  echo [0] Zurueck
  set /p "p=Auswahl: "
  if "%p%"=="1" (
    if exist "%~dp0php-uninstall.bat" (
      echo Fuehre php-uninstall.bat aus...
      call "%~dp0php-uninstall.bat"
    ) else (
      echo Entferne PHP-Ordner aus %APACHE_HOME%\php falls vorhanden.
      if exist "%APACHE_HOME%\php" (
        rd /s /q "%APACHE_HOME%\php" 2>nul
        echo PHP-Verzeichnis geloescht.
      ) else (
        echo Kein PHP-Verzeichnis in %APACHE_HOME% gefunden.
      )
    )
    pause
    goto mainmenu
  )
  goto mainmenu
) else (
  echo PHP ist nicht installiert.
  echo [1] Installieren
  echo [0] Zurueck
  set /p "p=Auswahl: "
  if "%p%"=="1" (
    :: Versuche Hersteller-Skript
    if exist "%~dp0php-install.bat" (
      echo Fuehre php-install.bat aus...
      call "%~dp0php-install.bat"
      pause
      goto mainmenu
    )
    :: Versuche php.zip zu entpacken nach %APACHE_HOME%\php
    if exist "%~dp0php.zip" (
      echo Entpacke php.zip nach %APACHE_HOME%\php ...
      if not exist "%APACHE_HOME%\php" mkdir "%APACHE_HOME%\php"
      powershell -noprofile -command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory('%~dp0php.zip','%APACHE_HOME%\php')"
      if %errorlevel%==0 (
        echo Entpacken erfolgreich.
        :: Beispiel: Apache config anpassen (httpd.conf) um PHP zu nutzen - nur Hinweis
        echo Bitte httpd.conf anpassen, um PHP als Modul/Handler zu registrieren.
      ) else (
        echo Entpacken fehlgeschlagen.
      )
    ) else (
      echo Kein php-install.bat oder php.zip gefunden.
      echo Lege php-install.bat oder php.zip in den Script-Ordner.
    )
    pause
    goto mainmenu
  )
  goto mainmenu
)

:webserver
cls
echo ============================
echo Webserver Start / Stopp
echo ============================
echo.
echo [1] Starten
echo [2] Stoppen
echo [0] Zurueck
set /p "w=Auswahl: "
if "%w%"=="1" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="0" (
        echo Starten des Services benoetigt Administratorrechte.
      ) else (
        echo Starte Apache Service...
        net start "Apache2.4"
      )
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        echo Starte Apache (bin\httpd.exe -k start)...
        "%APACHE_HOME%\bin\httpd.exe" -k start
      ) else (
        echo httpd.exe nicht gefunden in %APACHE_HOME%\bin.
      )
    )
  ) else (
    echo Apache ist nicht installiert.
  )
  pause
  goto mainmenu
)
if "%w%"=="2" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="0" (
        echo Stoppen des Services benoetigt Administratorrechte.
      ) else (
        echo Stoppe Apache Service...
        net stop "Apache2.4"
      )
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        echo Stoppe Apache (bin\httpd.exe -k stop)...
        "%APACHE_HOME%\bin\httpd.exe" -k stop
      ) else (
        echo httpd.exe nicht gefunden in %APACHE_HOME%\bin.
      )
    )
  ) else (
    echo Apache ist nicht installiert.
  )
  pause
  goto mainmenu
)
goto mainmenu

:config
cls
echo ============================
echo Config Einstellungen
echo ============================
echo Aktueller Apache Pfad: %APACHE_HOME%
echo.
echo [1] Pfad aendern
echo [2] Pfad auf Default setzen (C:\apache)
echo [3] Konfigurationsdatei anzeigen
echo [0] Zurueck
set /p "c=Auswahl: "
if "%c%"=="1" (
  set /p "new=Neuer Pfad (z.B. C:\apache): "
  if not "%new%"=="" (
    set "APACHE_HOME=%new%"
    >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
    echo Gespeichert.
    call :refresh_vars
  )
  pause
  goto mainmenu
)
if "%c%"=="2" (
  set "APACHE_HOME=C:\apache"
  >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
  echo Zurueckgesetzt.
  call :refresh_vars
  pause
  goto mainmenu
)
if "%c%"=="3" (
  type "%CFGFILE%"
  pause
  goto mainmenu
)
goto mainmenu

:end
echo Beende...
endlocal
exit /b 0
