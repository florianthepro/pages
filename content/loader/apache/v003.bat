@echo off
setlocal enabledelayedexpansion

:: -------------------------
:: Webserver Manager (Batch)
:: -------------------------
set "CFGFILE=%~dp0webmanager.cfg"

:: Default config anlegen
if not exist "%CFGFILE%" (
  echo APACHE_HOME=C:\apache>"%CFGFILE%"
)

:: Config laden
for /f "usebackq tokens=1* delims==" %%A in ("%CFGFILE%") do (
  if /i "%%A"=="APACHE_HOME" set "APACHE_HOME=%%B"
)

:: Admin prüfen (einfach)
net session >nul 2>&1
if %errorlevel%==0 (set "IS_ADMIN=1") else (set "IS_ADMIN=0")

:refresh
if exist "%APACHE_HOME%\bin\httpd.exe" (set "APACHE_INSTALLED=1") else (set "APACHE_INSTALLED=0")
sc query "Apache2.4" >nul 2>&1
if %errorlevel%==0 (set "APACHE_SERVICE=1") else (set "APACHE_SERVICE=0")
if exist "%~dp0php-install.bat" (set "PHP_INSTALLER_PRESENT=1") else (set "PHP_INSTALLER_PRESENT=0")
goto :eof

call :refresh

:main
cls
echo =========================================
echo            Webserver Manager
echo =========================================
echo.
if "%APACHE_INSTALLED%"=="1" (set "APACHE_LABEL=Apache - Deinstallieren") else (set "APACHE_LABEL=Apache - Installieren")
echo [1] %APACHE_LABEL%
if "%APACHE_INSTALLED%"=="1" (
  echo     ^> Pfad: %APACHE_HOME%
  echo [2] PHP - Installieren/Deinstallieren
)
echo [3] Webserver - Starten/Stoppen
if "%APACHE_INSTALLED%"=="1" echo [4] Config - Startpfad aendern
echo [0] Beenden
echo.
set /p "choice=Waehle eine Option: "

if "%choice%"=="1" goto apache
if "%choice%"=="2" if "%APACHE_INSTALLED%"=="1" goto php
if "%choice%"=="3" goto webserver
if "%choice%"=="4" if "%APACHE_INSTALLED%"=="1" goto config
if "%choice%"=="0" goto end
goto main

:apache
cls
echo === Apache ===
if "%APACHE_INSTALLED%"=="1" (
  echo Apache ist installiert in %APACHE_HOME%.
  echo [1] Deinstallieren
  echo [0] Zurueck
  set /p "a=Auswahl: "
  if "%a%"=="1" (
    if "%IS_ADMIN%"=="0" (
      echo Deinstallation benoetigt Administratorrechte.
      pause
      goto main
    )
    if exist "%~dp0apache-uninstall.bat" (
      echo Fuehre apache-uninstall.bat aus...
      call "%~dp0apache-uninstall.bat"
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        echo Entferne Service...
        "%APACHE_HOME%\bin\httpd.exe" -k uninstall -n "Apache2.4" 2>nul
        sc delete "Apache2.4" >nul 2>&1
      )
      echo Loesche Verzeichnis %APACHE_HOME%? (y/n)
      set /p "delc=>"
      if /i "%delc%"=="y" rd /s /q "%APACHE_HOME%" 2>nul & echo Verzeichnis geloescht.
    )
    call :refresh
    pause
    goto main
  )
  goto main
) else (
  echo Apache ist nicht installiert.
  echo [1] Installieren
  echo [0] Zurueck
  set /p "a=Auswahl: "
  if "%a%"=="1" (
    if exist "%~dp0apache-install.bat" (
      echo Fuehre apache-install.bat aus...
      call "%~dp0apache-install.bat"
    ) else if exist "%~dp0apache.zip" (
      echo Entpacke apache.zip nach %APACHE_HOME%...
      if not exist "%APACHE_HOME%" mkdir "%APACHE_HOME%"
      powershell -noprofile -command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory('%~dp0apache.zip','%APACHE_HOME%')"
      if %errorlevel%==0 echo Entpacken erfolgreich.
    ) else (
      echo Keine Installationsdateien gefunden. Lege apache-install.bat oder apache.zip in diesen Ordner.
    )
    :: Versuche Service zu installieren, falls vorhanden und Admin
    if exist "%APACHE_HOME%\bin\httpd.exe" (
      if "%IS_ADMIN%"=="1" (
        echo Installiere Service...
        "%APACHE_HOME%\bin\httpd.exe" -k install -n "Apache2.4"
        sc config "Apache2.4" start= auto >nul 2>&1
      ) else (
        echo Hinweis: Service-Installation benoetigt Adminrechte. Starte Script als Administrator fuer Service-Install.
      )
    )
    call :refresh
    pause
    goto main
  )
  goto main
)

:php
cls
echo === PHP ===
:: PHP nur wenn Apache installiert
if "%APACHE_INSTALLED%"=="0" (
  echo Apache nicht installiert. PHP-Option nicht verfuegbar.
  pause
  goto main
)
:: Check ob PHP vorhanden (einfach)
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
      if exist "%APACHE_HOME%\php" rd /s /q "%APACHE_HOME%\php" 2>nul & echo PHP-Verzeichnis geloescht.
    )
    pause
    goto main
  )
  goto main
) else (
  echo PHP ist nicht installiert.
  echo [1] Installieren
  echo [0] Zurueck
  set /p "p=Auswahl: "
  if "%p%"=="1" (
    if exist "%~dp0php-install.bat" (
      echo Fuehre php-install.bat aus...
      call "%~dp0php-install.bat"
    ) else if exist "%~dp0php.zip" (
      echo Entpacke php.zip nach %APACHE_HOME%\php...
      if not exist "%APACHE_HOME%\php" mkdir "%APACHE_HOME%\php"
      powershell -noprofile -command "Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory('%~dp0php.zip','%APACHE_HOME%\php')"
      if %errorlevel%==0 echo Entpacken erfolgreich.
      echo Bitte httpd.conf anpassen, um PHP zu aktivieren.
    ) else (
      echo Keine Installationsdateien gefunden. Lege php-install.bat oder php.zip in diesen Ordner.
    )
    pause
    goto main
  )
  goto main
)

:webserver
cls
echo === Webserver ===
echo [1] Starten
echo [2] Stoppen
echo [0] Zurueck
set /p "w=Auswahl: "
if "%w%"=="1" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="1" (net start "Apache2.4") else echo Starten des Services benoetigt Adminrechte.
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" ("%APACHE_HOME%\bin\httpd.exe" -k start) else echo httpd.exe nicht gefunden.
    )
  ) else echo Apache nicht installiert.
  pause
  goto main
)
if "%w%"=="2" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="1" (net stop "Apache2.4") else echo Stoppen des Services benoetigt Adminrechte.
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" ("%APACHE_HOME%\bin\httpd.exe" -k stop) else echo httpd.exe nicht gefunden.
    )
  ) else echo Apache nicht installiert.
  pause
  goto main
)
goto main

:config
cls
echo === Config ===
echo Aktueller Apache Pfad: %APACHE_HOME%
echo [1] Pfad aendern
echo [2] Auf Default (C:\apache) setzen
echo [0] Zurueck
set /p "c=Auswahl: "
if "%c%"=="1" (
  set /p "new=Neuer Pfad: "
  if not "%new%"=="" (
    set "APACHE_HOME=%new%"
    >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
    echo Gespeichert.
    call :refresh
  )
  pause
  goto main
)
if "%c%"=="2" (
  set "APACHE_HOME=C:\apache"
  >"%CFGFILE%" echo APACHE_HOME=%APACHE_HOME%
  echo Zurueckgesetzt.
  call :refresh
  pause
  goto main
)
goto main

:end
echo Beende...
endlocal
exit /b 0
