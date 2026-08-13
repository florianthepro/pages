@echo off
setlocal enabledelayedexpansion

:: -------------------------
:: Webserver Manager (rein Batch)
:: -------------------------
:: Konfigurationsdatei
set "CFG=%~dp0webmanager.cfg"
if not exist "%CFG%" echo APACHE_HOME=C:\apache>"%CFG%"

:: Lade Konfig
for /f "usebackq tokens=1* delims==" %%A in ("%CFG%") do (
  if /i "%%A"=="APACHE_HOME" set "APACHE_HOME=%%B"
)
if "%APACHE_HOME%"=="" set "APACHE_HOME=C:\apache"

:: Prüfe Admin-Rechte (einfach)
net session >nul 2>&1
if %errorlevel%==0 (set "IS_ADMIN=1") else (set "IS_ADMIN=0")

:refresh
if exist "%APACHE_HOME%\bin\httpd.exe" (set "APACHE_INSTALLED=1") else (set "APACHE_INSTALLED=0")
sc query "Apache2.4" >nul 2>&1
if %errorlevel%==0 (set "APACHE_SERVICE=1") else (set "APACHE_SERVICE=0")
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
  echo     Pfad: %APACHE_HOME%
  echo [2] PHP - Installieren/Deinstallieren
)
echo [3] Webserver - Starten/Stoppen
if "%APACHE_INSTALLED%"=="1" echo [4] Config - Startpfad aendern
echo [0] Beenden
echo.
set /p "CHOICE=Waehle eine Option: "

if "%CHOICE%"=="1" goto apache
if "%CHOICE%"=="2" if "%APACHE_INSTALLED%"=="1" goto php
if "%CHOICE%"=="3" goto webserver
if "%CHOICE%"=="4" if "%APACHE_INSTALLED%"=="1" goto config
if "%CHOICE%"=="0" goto end
goto main

:apache
cls
echo === Apache ===
if "%APACHE_INSTALLED%"=="1" (
  echo Apache ist installiert in %APACHE_HOME%.
  echo [1] Deinstallieren
  echo [0] Zurueck
  set /p "A=Auswahl: "
  if "%A%"=="1" (
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
        echo Entferne Apache-Service...
        "%APACHE_HOME%\bin\httpd.exe" -k uninstall -n "Apache2.4" 2>nul
        sc delete "Apache2.4" >nul 2>&1
      ) else echo Kein httpd.exe gefunden; Service eventuell nicht installiert.
      set /p "DEL=Verzeichnis %APACHE_HOME% loeschen? (y/n): "
      if /i "%DEL%"=="y" (
        rd /s /q "%APACHE_HOME%" 2>nul
        if %errorlevel%==0 (echo Verzeichnis geloescht.) else (echo Loeschen fehlgeschlagen oder keine Rechte.)
      )
    )
    call :refresh
    pause
    goto main
  )
  goto main
) else (
  echo Apache ist nicht installiert.
  echo [1] Installieren (verwende apache-install.bat oder entpacke apache.zip manuell)
  echo [0] Zurueck
  set /p "A=Auswahl: "
  if "%A%"=="1" (
    if exist "%~dp0apache-install.bat" (
      echo Fuehre apache-install.bat aus...
      call "%~dp0apache-install.bat"
    ) else (
      echo Kein apache-install.bat gefunden.
      echo Lege die Apache-Binaries in %APACHE_HOME% oder lege apache-install.bat in diesen Ordner.
      echo Beispiel: entpacke Apache in %APACHE_HOME% und dann Service installieren.
    )
    :: Falls httpd.exe jetzt vorhanden, optional Service installieren
    if exist "%APACHE_HOME%\bin\httpd.exe" (
      if "%IS_ADMIN%"=="1" (
        echo Installiere Apache-Service...
        "%APACHE_HOME%\bin\httpd.exe" -k install -n "Apache2.4" 2>nul
        sc config "Apache2.4" start= auto >nul 2>&1
        echo Service-Installation versucht.
      ) else (
        echo Hinweis: Service-Installation benoetigt Administratorrechte. Starte Batch als Administrator.
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
if "%APACHE_INSTALLED%"=="0" (
  echo PHP-Option nur verfuegbar wenn Apache installiert ist.
  pause
  goto main
)
set "PHP_FOUND=0"
if exist "%APACHE_HOME%\php\php.exe" set "PHP_FOUND=1"
if exist "%~dp0php\php.exe" set "PHP_FOUND=1"
if exist "%~dp0php.exe" set "PHP_FOUND=1"

if "%PHP_FOUND%"=="1" (
  echo PHP ist installiert.
  echo [1] Deinstallieren
  echo [0] Zurueck
  set /p "P=Auswahl: "
  if "%P%"=="1" (
    if exist "%~dp0php-uninstall.bat" (
      echo Fuehre php-uninstall.bat aus...
      call "%~dp0php-uninstall.bat"
    ) else (
      if exist "%APACHE_HOME%\php" (
        rd /s /q "%APACHE_HOME%\php" 2>nul
        if %errorlevel%==0 (echo PHP-Verzeichnis geloescht.) else (echo Loeschen fehlgeschlagen.)
      ) else echo Kein PHP-Verzeichnis in %APACHE_HOME% gefunden.
    )
    pause
    goto main
  )
  goto main
) else (
  echo PHP ist nicht installiert.
  echo [1] Installieren (verwende php-install.bat oder entpacke php.zip manuell nach %APACHE_HOME%\php)
  echo [0] Zurueck
  set /p "P=Auswahl: "
  if "%P%"=="1" (
    if exist "%~dp0php-install.bat" (
      echo Fuehre php-install.bat aus...
      call "%~dp0php-install.bat"
    ) else (
      echo Kein php-install.bat gefunden.
      echo Lege PHP in %APACHE_HOME%\php oder php-install.bat in diesen Ordner.
      echo Nach dem Entpacken: httpd.conf anpassen, um PHP zu aktivieren.
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
set /p "W=Auswahl: "
if "%W%"=="1" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="1" (
        echo Starte Apache-Service...
        net start "Apache2.4"
      ) else (
        echo Service-Start benoetigt Administratorrechte. Starte Batch als Administrator.
      )
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        echo Starte Apache (bin\httpd.exe -k start)...
        "%APACHE_HOME%\bin\httpd.exe" -k start
      ) else echo httpd.exe nicht gefunden.
    )
  ) else echo Apache nicht installiert.
  pause
  goto main
)
if "%W%"=="2" (
  if "%APACHE_INSTALLED%"=="1" (
    if "%APACHE_SERVICE%"=="1" (
      if "%IS_ADMIN%"=="1" (
        echo Stoppe Apache-Service...
        net stop "Apache2.4"
      ) else (
        echo Service-Stop benoetigt Administratorrechte. Starte Batch als Administrator.
      )
    ) else (
      if exist "%APACHE_HOME%\bin\httpd.exe" (
        echo Stoppe Apache (bin\httpd.exe -k stop)...
        "%APACHE_HOME%\bin\httpd.exe" -k stop
      ) else echo httpd.exe nicht gefunden.
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
echo [2] Auf Default setzen (C:\apache)
echo [3] Konfig anzeigen
echo [0] Zurueck
set /p "C=Auswahl: "
if "%C%"=="1" (
  set /p "NEW=Neuer Pfad: "
  if not "%NEW%"=="" (
    set "APACHE_HOME=%NEW%"
    >"%CFG%" echo APACHE_HOME=%APACHE_HOME%
    call :refresh
    echo Gespeichert.
  )
  pause
  goto main
)
if "%C%"=="2" (
  set "APACHE_HOME=C:\apache"
  >"%CFG%" echo APACHE_HOME=%APACHE_HOME%
  call :refresh
  echo Zurueckgesetzt.
  pause
  goto main
)
if "%C%"=="3" (
  type "%CFG%"
  pause
  goto main
)
goto main

:end
echo Beende...
pause
endlocal
exit /b 0
