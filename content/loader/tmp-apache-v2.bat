@echo off
setlocal enabledelayedexpansion

:: interactive start_apache_temp.bat
:: - fragt interaktiv nach dem Pfad zur portablen Apache+PHP-Installation
:: - startet Apache in einem neuen Fenster
:: - wartet auf "q" (oder "Q") in diesem Fenster zum sauberen Stoppen
:: - versucht verbleibende httpd.exe Prozesse, die den angegebenen Pfad enthalten, zu beenden
:: - löscht oder verändert keine Webinhalte

echo.
echo ============================
echo Temporärer Apache Starter
echo ============================
echo.

:: Pfad abfragen
set "ROOT="
set /p ROOT=Gib den Pfad zur portablen Apache+PHP-Installation ein (z.B. C:\temp\portable-www): 

:: Trim führende Anführungszeichen
if "%ROOT:~0,1%"=="\"" set "ROOT=%ROOT:~1%"
:: Trim abschließende Anführungszeichen (falls vorhanden)
if "%ROOT:~-1%"=="\"" set "ROOT=%ROOT:~0,-1%"

if "%ROOT%"=="" (
  echo Kein Pfad angegeben. Abbruch.
  exit /b 1
)

if not exist "%ROOT%" (
  echo Pfad "%ROOT%" existiert nicht. Abbruch.
  exit /b 2
)

:: httpd.exe suchen (mehrere mögliche Orte)
set "HTTPD="
if exist "%ROOT%\Apache24\bin\httpd.exe" set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
if exist "%ROOT%\httpd\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd\bin\httpd.exe"
if exist "%ROOT%\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\bin\httpd.exe"
if exist "%ROOT%\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd.exe"

if "%HTTPD%"=="" (
  echo httpd.exe wurde im angegebenen Pfad nicht gefunden.
  echo Erwartete Orte: Apache24\bin\httpd.exe  oder  httpd\bin\httpd.exe  oder  bin\httpd.exe
  exit /b 3
)

:: httpd.conf finden (optional)
set "HTTPD_CONF="
if exist "%ROOT%\Apache24\conf\httpd.conf" set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if exist "%ROOT%\httpd\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd\conf\httpd.conf"
if exist "%ROOT%\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\conf\httpd.conf"
if exist "%ROOT%\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd.conf"

if "%HTTPD_CONF%"=="" (
  echo Achtung: httpd.conf nicht automatisch gefunden.
  set /p HTTPD_CONF=Pfad zur httpd.conf (vollstaendig) oder ENTER zum Standard: 
  if not "%HTTPD_CONF%"=="" (
    if not exist "%HTTPD_CONF%" (
      echo Angegebene httpd.conf existiert nicht. Abbruch.
      exit /b 4
    )
  )
)

echo.
echo Gefundene httpd.exe: %HTTPD%
if not "%HTTPD_CONF%"=="" echo Verwendete Konfig: %HTTPD_CONF%
echo.

:: Starten: neues Fenster, damit dieses Fenster für "q" frei bleibt
echo Starte Apache in neuem Fenster...
if "%HTTPD_CONF%"=="" (
  start "Apache-Temp" "%HTTPD%" -k run
) else (
  start "Apache-Temp" "%HTTPD%" -f "%HTTPD_CONF%" -k run
)

if errorlevel 1 (
  echo Fehler beim Starten von Apache.
  exit /b 5
)

echo Apache gestartet.
echo Tippe q und druecke Enter in diesem Fenster, um den Server sauber zu stoppen.
echo.

:WAIT_LOOP
set "KEY="
set /p KEY=Eingabe (q = stop): 
if /I "%KEY%"=="q" goto STOP_APACHE
echo Ungueltige Eingabe. Tippe q und Enter zum Stoppen.
goto WAIT_LOOP

:STOP_APACHE
echo Stoppe Apache (sauber)...
if "%HTTPD_CONF%"=="" (
  "%HTTPD%" -k stop >nul 2>&1
) else (
  "%HTTPD%" -f "%HTTPD_CONF%" -k stop >nul 2>&1
)

:: kurze Wartezeit
timeout /t 2 >nul

:: Versuche verbleibende httpd.exe Prozesse zu beenden, die den angegebenen Pfad in der CommandLine haben
powershell -NoProfile -Command ^
  "$r = '%ROOT%';" ^
  "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like ('*' + $r + '*') } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" >nul 2>&1

echo Apache gestoppt (sofern noch aktiv). Ende.
endlocal
exit /b 0
