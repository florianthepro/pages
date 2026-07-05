@echo off
setlocal enabledelayedexpansion

:: start_apache_interactive.bat
:: Interaktives Script: fragt Pfad, startet Apache in neuem Fenster, wartet auf "q" zum Stoppen.
:: Verändert keine Webinhalte; versucht nur Prozesse zu stoppen, die zur angegebenen Installation gehören.

echo.
echo ============================
echo Portable Apache Starter
echo ============================
echo.

:: Pfad abfragen
set "ROOT="
set /p ROOT=Gib den Pfad zur portablen Apache+PHP-Installation ein (z.B. C:\temp\portable-www): 

:: Trim führende/abschließende Anführungszeichen
if "%ROOT:~0,1%"=="\"" set "ROOT=%ROOT:~1%"
if "%ROOT:~-1%"=="\"" set "ROOT=%ROOT:~0,-1%"

if "%ROOT%"=="" (
  echo.
  echo Kein Pfad angegeben. Abbruch.
  pause
  exit /b 1
)

if not exist "%ROOT%" (
  echo.
  echo Pfad "%ROOT%" existiert nicht. Abbruch.
  pause
  exit /b 2
)

:: Suche httpd.exe an mehreren typischen Orten
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
  pause
  exit /b 3
)

:: Suche httpd.conf (optional)
set "HTTPD_CONF="
if exist "%ROOT%\Apache24\conf\httpd.conf" set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if exist "%ROOT%\httpd\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd\conf\httpd.conf"
if exist "%ROOT%\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\conf\httpd.conf"
if exist "%ROOT%\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd.conf"

echo.
echo Gefundene httpd.exe: %HTTPD%
if not "%HTTPD_CONF%"=="" echo Verwendete Konfig: %HTTPD_CONF%
echo.

:: Starten in neuem Fenster, damit dieses Fenster für Steuerung offen bleibt
echo Starte Apache in neuem Fenster...
if "%HTTPD_CONF%"=="" (
  start "Apache-Temp" "%HTTPD%" -k run
) else (
  start "Apache-Temp" "%HTTPD%" -f "%HTTPD_CONF%" -k run
)

:: Kurze Wartezeit, dann prüfen ob Prozess läuft
timeout /t 1 >nul

echo.
echo Prüfe, ob httpd.exe läuft...
tasklist /FI "IMAGENAME eq httpd.exe" | findstr /I "httpd.exe" >nul
if errorlevel 1 (
  echo WARNUNG: httpd.exe wurde nicht gefunden. Möglicherweise ist der Start fehlgeschlagen.
  echo Prüfe das neue Fenster "Apache-Temp" auf Fehlermeldungen oder die httpd.conf.
  echo Das Script bleibt offen; druecke q und Enter zum Beenden.
) else (
  echo Apache scheint zu laufen.
)

echo.
echo Tippe q und druecke Enter in diesem Fenster, um den Server sauber zu stoppen.
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
  "%HTTPD%" -k stop 2>nul
) else (
  "%HTTPD%" -f "%HTTPD_CONF%" -k stop 2>nul
)

:: Warte kurz, dann forcieren, falls noch Prozesse existieren
timeout /t 2 >nul

echo Überprüfe verbleibende httpd.exe Prozesse und beende sie falls nötig...
powershell -NoProfile -Command ^
  "$root = '%ROOT%';" ^
  "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like ('*' + $root + '*') } | ForEach-Object { Write-Output ('Kille PID: ' + $_.ProcessId); Stop-Process -Id $_.ProcessId -Force }" 2>nul

echo Apache gestoppt (sofern aktiv). Fertig.
pause
endlocal
exit /b 0
