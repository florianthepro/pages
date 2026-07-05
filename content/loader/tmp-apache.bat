@echo off
setlocal enabledelayedexpansion

:: start_apache_temp.bat (angepasst für curl-download in %TEMP%)
:: Usage:
::   cmd /c "%TEMP%\tmp_start.bat" "C:\path\to\apache" /noninteractive
::   cmd /c "%TEMP%\tmp_start.bat" /noninteractive
:: If no path given, script will try sensible defaults relative to its own location.

:: ---------- Konfiguration ----------
set "SCRIPT_DIR=%~dp0"
set "LOGFILE=%TEMP%\start_apache_%RANDOM%.log"
set "ROOT=%~1"
set "MODE=interactive"

:: Wenn zweiter Parameter /noninteractive oder wenn erster Parameter /noninteractive
if /I "%~2"=="/noninteractive" set "MODE=noninteractive"
if /I "%~2"=="/silent" set "MODE=noninteractive"
if /I "%~1"=="/noninteractive" set "MODE=noninteractive" & set "ROOT="

echo [%date% %time%] start_apache_temp.bat gestartet > "%LOGFILE%"
echo [%date% %time%] ScriptDir=%SCRIPT_DIR% >> "%LOGFILE%"
echo [%date% %time%] Initial Root param: %ROOT% >> "%LOGFILE%"
echo [%date% %time%] Mode: %MODE% >> "%LOGFILE%"

:: Wenn kein ROOT übergeben, versuche sinnvolle Defaults
if "%ROOT%"=="" (
  rem 1) Wenn das Skript in %TEMP% liegt (durch curl), prüfe ob neben der Batch ein entpackter Ordner existiert
  if /I "%SCRIPT_DIR:~0,4%"=="%TEMP:~0,4%" (
    if exist "%SCRIPT_DIR%Apache24\" set "ROOT=%SCRIPT_DIR%"
    if exist "%SCRIPT_DIR%httpd\" if "%ROOT%"=="" set "ROOT=%SCRIPT_DIR%"
    if exist "%SCRIPT_DIR%apache\" if "%ROOT%"=="" set "ROOT=%SCRIPT_DIR%"
  )

  rem 2) Prüfe Standardorte auf dem System (nur Vorschläge)
  if "%ROOT%"=="" (
    if exist "%USERPROFILE%\Downloads\Apache24\" set "ROOT=%USERPROFILE%\Downloads\Apache24\"
    if exist "C:\temp\portable-www\" if "%ROOT%"=="" set "ROOT=C:\temp\portable-www\"
  )
)

echo [%date% %time%] Root after auto-detect: %ROOT% >> "%LOGFILE%"

:: Wenn noch kein ROOT und interaktiv, frage nach Pfad
if "%ROOT%"=="" if "%MODE%"=="interactive" (
  echo.
  echo Kein Apache-Pfad automatisch gefunden.
  set /p ROOT=Gib den Pfad zur portablen Apache+PHP Installation ein (z.B. C:\temp\portable-www): 
)

:: Trim Anführungszeichen falls vorhanden
if "%ROOT:~0,1%"=="\"" set "ROOT=%ROOT:~1,-0%"
if "%ROOT:~-1%"=="\"" set "ROOT=%ROOT:~0,-1%"

:: Validierung
if "%ROOT%"=="" (
  echo [%date% %time%] Fehler: Kein Pfad angegeben. >> "%LOGFILE%"
  echo Fehler: Kein Pfad angegeben. Siehe Log: %LOGFILE%
  exit /b 2
)

if not exist "%ROOT%" (
  echo [%date% %time%] Fehler: Pfad "%ROOT%" existiert nicht. >> "%LOGFILE%"
  echo Fehler: Pfad "%ROOT%" existiert nicht. Siehe Log: %LOGFILE%
  exit /b 3
)

:: httpd.exe suchen (mehrere mögliche Orte)
set "HTTPD="
if exist "%ROOT%\Apache24\bin\httpd.exe" set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
if exist "%ROOT%\httpd\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd\bin\httpd.exe"
if exist "%ROOT%\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\bin\httpd.exe"
if exist "%ROOT%\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd.exe"

if "%HTTPD%"=="" (
  echo [%date% %time%] Fehler: httpd.exe nicht gefunden in %ROOT% >> "%LOGFILE%"
  echo Fehler: httpd.exe nicht gefunden in %ROOT%. Siehe Log: %LOGFILE%
  exit /b 4
)

:: httpd.conf finden
set "HTTPD_CONF="
if exist "%ROOT%\Apache24\conf\httpd.conf" set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if exist "%ROOT%\httpd\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd\conf\httpd.conf"
if exist "%ROOT%\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\conf\httpd.conf"
if exist "%ROOT%\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd.conf"

if "%HTTPD_CONF%"=="" (
  echo [%date% %time%] Warnung: httpd.conf nicht gefunden in Standardorten. >> "%LOGFILE%"
  if "%MODE%"=="interactive" (
    set /p HTTPD_CONF=Pfad zur httpd.conf (vollstaendig): 
    if not exist "%HTTPD_CONF%" (
      echo Fehler: angegebene httpd.conf existiert nicht. >> "%LOGFILE%"
      echo Fehler: angegebene httpd.conf existiert nicht.
      exit /b 5
    )
  ) else (
    echo Fehler: httpd.conf nicht gefunden und nicht interaktiv. >> "%LOGFILE%"
    exit /b 6
  )
)

echo [%date% %time%] Gefundene httpd.exe: %HTTPD% >> "%LOGFILE%"
echo [%date% %time%] Verwendete Konfig: %HTTPD_CONF% >> "%LOGFILE%"

:: Starten
if "%MODE%"=="noninteractive" (
  echo [%date% %time%] Starte Apache noninteractive, Ausgabe in Log >> "%LOGFILE%"
  start "" /b "%HTTPD%" -f "%HTTPD_CONF%" -k run >> "%LOGFILE%" 2>&1
  echo [%date% %time%] Apache gestartet (noninteractive). >> "%LOGFILE%"
  echo Apache gestartet. Log: %LOGFILE%
  exit /b 0
) else (
  echo [%date% %time%] Starte Apache interaktiv in neuem Fenster... >> "%LOGFILE%"
  start "Apache-Temp" "%HTTPD%" -f "%HTTPD_CONF%" -k run
  echo Apache wurde gestartet. Tippe q und Enter in diesem Fenster, um den Server sauber zu stoppen.
)

:WAIT_LOOP
set "KEY="
set /p KEY=Eingabe (q = stop): 
if /I "%KEY%"=="q" goto STOP_APACHE
echo Ungueltige Eingabe. Tippe q und Enter zum Stoppen.
goto WAIT_LOOP

:STOP_APACHE
echo [%date% %time%] Stoppe Apache (sauber)... >> "%LOGFILE%"
"%HTTPD%" -f "%HTTPD_CONF%" -k stop >> "%LOGFILE%" 2>&1
timeout /t 2 >nul

echo [%date% %time%] Ueberpruefe verbleibende Prozesse... >> "%LOGFILE%"
powershell -NoProfile -Command ^
  "$root = '%ROOT%';" ^
  "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine -like '*'+$root+'*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" 2>>"%LOGFILE%"

echo [%date% %time%] Beende. Log: %LOGFILE% >> "%LOGFILE%"
endlocal
exit /b 0
