@echo off
setlocal enabledelayedexpansion

:: start_apache_interactive.bat
:: Interaktiv: Pfad abfragen, Apache in neuem Fenster starten, auf "q" warten, sauber stoppen.
:: Keine Löschung von Webinhalten. Logdatei in %TEMP% für Fehlersuche.

set "LOG=%TEMP%\start_apache_%RANDOM%.log"
echo [%date% %time%] Script gestartet > "%LOG%"

:ASK_PATH
echo.
echo ============================
echo Portable Apache Starter
echo ============================
echo.
set "ROOT="
set /p ROOT=Gib den Pfad zur portablen Apache+PHP-Installation ein (z.B. C:\temp\portable-www) oder tippe CANCEL zum Abbruch: 
if /I "%ROOT%"=="CANCEL" (
  echo Abbruch durch Benutzer.
  echo [%date% %time%] Abbruch durch Benutzer >> "%LOG%"
  pause
  exit /b 1
)

:: Trim führende/abschliessende Anführungszeichen
if "%ROOT:~0,1%"=="\"" set "ROOT=%ROOT:~1%"
if "%ROOT:~-1%"=="\"" set "ROOT=%ROOT:~0,-1%"

if "%ROOT%"=="" (
  echo Kein Pfad eingegeben. Bitte erneut.
  goto ASK_PATH
)

if not exist "%ROOT%" (
  echo Pfad "%ROOT%" existiert nicht. Bitte erneut.
  goto ASK_PATH
)

echo [%date% %time%] Angegebener Pfad: %ROOT% >> "%LOG%"

:: Suche httpd.exe an typischen Orten
set "HTTPD="
if exist "%ROOT%\Apache24\bin\httpd.exe" set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
if exist "%ROOT%\httpd\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd\bin\httpd.exe"
if exist "%ROOT%\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\bin\httpd.exe"
if exist "%ROOT%\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd.exe"

if "%HTTPD%"=="" (
  echo.
  echo Fehler: httpd.exe wurde im angegebenen Pfad nicht gefunden.
  echo Erwartete Orte: Apache24\bin\httpd.exe  oder  httpd\bin\httpd.exe  oder  bin\httpd.exe
  echo Bitte entpacke eine portable Apache-Distribution in den angegebenen Ordner oder gib den korrekten Pfad an.
  echo [%date% %time%] httpd.exe nicht gefunden in %ROOT% >> "%LOG%"
  pause
  goto ASK_PATH
)

:: Suche httpd.conf (optional)
set "HTTPD_CONF="
if exist "%ROOT%\Apache24\conf\httpd.conf" set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if exist "%ROOT%\httpd\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd\conf\httpd.conf"
if exist "%ROOT%\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\conf\httpd.conf"
if exist "%ROOT%\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd.conf"

echo [%date% %time%] Gefundene httpd.exe: %HTTPD% >> "%LOG%"
if not "%HTTPD_CONF%"=="" echo [%date% %time%] Gefundene httpd.conf: %HTTPD_CONF% >> "%LOG%"

echo.
echo Gefundene httpd.exe: %HTTPD%
if not "%HTTPD_CONF%"=="" echo Verwendete Konfig: %HTTPD_CONF%
echo.

:: Starten in neuem Fenster; cmd /k sorgt dafür, dass das neue Fenster offen bleibt falls Fehler auftreten
echo Starte Apache in neuem Fenster...
if "%HTTPD_CONF%"=="" (
  start "Apache-Temp" cmd /k ""%HTTPD%" -k run"
) else (
  start "Apache-Temp" cmd /k ""%HTTPD%" -f "%HTTPD_CONF%" -k run"
)

if errorlevel 1 (
  echo Fehler beim Starten von Apache. Siehe Log: %LOG%
  echo [%date% %time%] Fehler beim Starten von Apache >> "%LOG%"
  pause
  exit /b 5
)

:: Kurze Wartezeit, dann prüfen ob Prozess läuft
timeout /t 1 >nul

echo.
echo Prüfe, ob httpd.exe läuft...
tasklist /FI "IMAGENAME eq httpd.exe" | findstr /I "httpd.exe" >nul
if errorlevel 1 (
  echo WARNUNG: httpd.exe wurde nicht gefunden. Möglicherweise ist der Start fehlgeschlagen.
  echo Öffne das Fenster "Apache-Temp" und prüfe Fehlermeldungen oder logs\error.log im Apache-Ordner.
  echo [%date% %time%] httpd.exe nicht in tasklist gefunden >> "%LOG%"
) else (
  echo Apache scheint zu laufen.
  echo [%date% %time%] httpd.exe läuft >> "%LOG%"
)

echo.
echo Das aktuelle Fenster steuert den Server. Tippe q und druecke Enter, um Apache sauber zu stoppen.
echo.

:WAIT_LOOP
set "KEY="
set /p KEY=Eingabe (q = stop): 
if /I "%KEY%"=="q" goto STOP_APACHE
echo Ungueltige Eingabe. Tippe q und Enter zum Stoppen.
goto WAIT_LOOP

:STOP_APACHE
echo.
echo Stoppe Apache (sauber)...
if "%HTTPD_CONF%"=="" (
  "%HTTPD%" -k stop 2>> "%LOG%"
) else (
  "%HTTPD%" -f "%HTTPD_CONF%" -k stop 2>> "%LOG%"
)

timeout /t 2 >nul

echo [%date% %time%] Versuche verbleibende httpd.exe Prozesse zu beenden >> "%LOG%"
powershell -NoProfile -Command ^
  "$root = '%ROOT%';" ^
  "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like ('*' + $root + '*') } | ForEach-Object { Write-Output ('Kille PID: ' + $_.ProcessId); Stop-Process -Id $_.ProcessId -Force }" 2>> "%LOG%"

echo Apache gestoppt (sofern aktiv). Log: %LOG%
echo.
pause
endlocal
exit /b 0
