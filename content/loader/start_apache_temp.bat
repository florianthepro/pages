@echo off
setlocal enabledelayedexpansion

:: start_apache_temp.bat
:: - Parameter:
::    %1 = Pfad zur portablen Apache-Installation (optional)
::    /noninteractive oder /silent = kein Prompt, startet Apache und beendet sich (Logs werden geschrieben)
:: Beispiel remote: cmd /c tmp_start_apache.bat "C:\temp\apache" /noninteractive

:: ---------- Konfiguration ----------
set "LOGFILE=%TEMP%\start_apache_%RANDOM%.log"
set "TMP_CREATED=0"
set "ROOT=%~1"
set "MODE=interactive"
if /I "%~2"=="/noninteractive" set "MODE=noninteractive"
if /I "%~2"=="/silent" set "MODE=noninteractive"
if /I "%~1"=="/noninteractive" set "MODE=noninteractive" & set "ROOT="

:: Wenn kein Pfad übergeben wurde, benutze Standardvorschlag (falls entpackt via remote download)
if "%ROOT%"=="" (
  if exist "%~dp0Apache24" set "ROOT=%~dp0"
  if exist "%~dp0httpd" if "%ROOT%"=="" set "ROOT=%~dp0"
)

:: Logging starten
echo [%date% %time%] Starte start_apache_temp.bat > "%LOGFILE%"
echo [%date% %time%] Mode=%MODE% >> "%LOGFILE%"
echo [%date% %time%] Root initial: %ROOT% >> "%LOGFILE%"

:: Falls kein ROOT, frage interaktiv (nur im interactive mode)
if "%ROOT%"=="" if "%MODE%"=="interactive" (
  set /p ROOT=Gib den Pfad zur portablen Apache+PHP Installation ein (z.B. C:\temp\portable-www): 
)

if not exist "%ROOT%" (
  echo [%date% %time%] Fehler: Pfad "%ROOT%" existiert nicht. >> "%LOGFILE%"
  echo Fehler: Pfad "%ROOT%" existiert nicht. Siehe Log: %LOGFILE%
  exit /b 2
)

:: httpd.exe suchen
set "HTTPD="
if exist "%ROOT%\Apache24\bin\httpd.exe" set "HTTPD=%ROOT%\Apache24\bin\httpd.exe"
if exist "%ROOT%\httpd\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\httpd\bin\httpd.exe"
if exist "%ROOT%\bin\httpd.exe" if "%HTTPD%"=="" set "HTTPD=%ROOT%\bin\httpd.exe"

if "%HTTPD%"=="" (
  echo [%date% %time%] Fehler: httpd.exe nicht gefunden in %ROOT% >> "%LOGFILE%"
  echo Fehler: httpd.exe nicht gefunden in %ROOT%. Siehe Log: %LOGFILE%
  exit /b 3
)

:: httpd.conf finden
set "HTTPD_CONF="
if exist "%ROOT%\Apache24\conf\httpd.conf" set "HTTPD_CONF=%ROOT%\Apache24\conf\httpd.conf"
if exist "%ROOT%\httpd\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\httpd\conf\httpd.conf"
if exist "%ROOT%\conf\httpd.conf" if "%HTTPD_CONF%"=="" set "HTTPD_CONF=%ROOT%\conf\httpd.conf"

if "%HTTPD_CONF%"=="" (
  echo [%date% %time%] Warnung: httpd.conf nicht gefunden. >> "%LOGFILE%"
  if "%MODE%"=="interactive" (
    set /p HTTPD_CONF=Pfad zur httpd.conf (vollstaendig): 
    if not exist "%HTTPD_CONF%" (
      echo Fehler: angegebene httpd.conf existiert nicht. >> "%LOGFILE%"
      echo Fehler: angegebene httpd.conf existiert nicht.
      exit /b 4
    )
  ) else (
    echo Fehler: httpd.conf nicht gefunden und nicht interaktiv. >> "%LOGFILE%"
    exit /b 5
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

:: Entferne nur temporäre Dateien, die dieses Skript erzeugt hat (Log bleibt zur Fehlersuche)
echo [%date% %time%] Beende. Log: %LOGFILE% >> "%LOGFILE%"
endlocal
exit /b 0
