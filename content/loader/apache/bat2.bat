@echo off
setlocal

:: Ordner des Batch-Skripts
set "SDIR=%~dp0"

:: Erzeuge/überschreibe webmanager.ps1
> "%SDIR%webmanager.ps1" (
  echo # Autarkes PowerShell Webserver-Manager-Skript
  echo $ErrorActionPreference = 'Stop'
  echo $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
  echo $CfgFile = Join-Path $ScriptDir "webmanager.cfg"
  echo if (-not (Test-Path $CfgFile)) { "APACHE_HOME=C:\apache" ^| Out-File -FilePath $CfgFile -Encoding UTF8 }
  echo function Load-Config {
  echo ^    try {
  echo ^        $lines = Get-Content $CfgFile -ErrorAction Stop
  echo ^        foreach ($l in $lines) {
  echo ^            if ($l -match '^\s*APACHE_HOME\s*=\s*(.+)\s*$') { $global:APACHE_HOME = $Matches[1].Trim() }
  echo ^        }
  echo ^        if (-not $global:APACHE_HOME) { $global:APACHE_HOME = "C:\apache" }
  echo ^    } catch {
  echo ^        Write-Host "Fehler beim Laden der Konfiguration: $_" -ForegroundColor Red
  echo ^        Read-Host "Drücke Enter zum Beenden..."
  echo ^        exit 1
  echo ^    }
  echo }
  echo function Save-Config {
  echo ^    try {
  echo ^        "APACHE_HOME=$APACHE_HOME" ^| Out-File -FilePath $CfgFile -Encoding UTF8 -Force
  echo ^    } catch {
  echo ^        Write-Host "Fehler beim Speichern der Konfiguration: $_" -ForegroundColor Red
  echo ^        Read-Host "Drücke Enter zum Fortfahren..."
  echo ^    }
  echo }
  echo function Ensure-Elevated {
  echo ^    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
  echo ^    if (-not $isAdmin) {
  echo ^        Write-Host "Starte mit Administratorrechten neu..." -ForegroundColor Yellow
  echo ^        $arg = "-NoProfile -ExecutionPolicy Bypass -File `"$($MyInvocation.MyCommand.Path)`""
  echo ^        Start-Process -FilePath "powershell.exe" -ArgumentList $arg -Verb RunAs
  echo ^        exit
  echo ^    }
  echo }
  echo function Refresh-Status {
  echo ^    $global:ApacheInstalled = Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")
  echo ^    $svc = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
  echo ^    $global:ApacheService = $null -ne $svc
  echo ^    $global:PhpFound = Test-Path (Join-Path $APACHE_HOME "php\php.exe") -or Test-Path (Join-Path $ScriptDir "php\php.exe") -or Test-Path (Join-Path $ScriptDir "php.exe")
  echo }
  echo function Try-Call {
  echo ^    param($ScriptPath)
  echo ^    try {
  echo ^        & $ScriptPath
  echo ^    } catch {
  echo ^        Write-Host "Fehler beim Ausführen von $ScriptPath" -ForegroundColor Red
  echo ^        Write-Host $_.Exception.Message -ForegroundColor Red
  echo ^        Read-Host "Drücke Enter zum Fortfahren..."
  echo ^    }
  echo }
  echo # Default-Download-URLs (kann angepasst werden)
  echo $global:DefaultApacheUrl = "https://www.apachelounge.com/download/VC15/binaries/httpd-2.4.54-win64-VS16.zip"
  echo $global:DefaultPhpUrl = "https://windows.php.net/downloads/releases/php-8.1.0-Win32-vs16-x64.zip"
  echo function Download-IfMissing {
  echo ^    param($Url, $OutFile)
  echo ^    if (-not (Test-Path $OutFile)) {
  echo ^        try {
  echo ^            Write-Host "Lade $Url ..." -ForegroundColor Cyan
  echo ^            Invoke-WebRequest -Uri $Url -OutFile $OutFile -UseBasicParsing -ErrorAction Stop
  echo ^            Write-Host "Download abgeschlossen: $OutFile" -ForegroundColor Green
  echo ^        } catch {
  echo ^            Write-Host "Download fehlgeschlagen: $Url" -ForegroundColor Yellow
  echo ^            Write-Host $_.Exception.Message -ForegroundColor Red
  echo ^            return $false
  echo ^        }
  echo ^    } else {
  echo ^        Write-Host "Bereits vorhanden: $OutFile" -ForegroundColor DarkCyan
  echo ^    }
  echo ^    return $true
  echo }
  echo function Extract-Zip {
  echo ^    param($ZipPath, $Dest)
  echo ^    try {
  echo ^        Add-Type -AssemblyName System.IO.Compression.FileSystem
  echo ^        [IO.Compression.ZipFile]::ExtractToDirectory($ZipPath, $Dest)
  echo ^        return $true
  echo ^    } catch {
  echo ^        Write-Host "Entpacken fehlgeschlagen: $ZipPath -> $Dest" -ForegroundColor Red
  echo ^        Write-Host $_.Exception.Message -ForegroundColor Red
  echo ^        return $false
  echo ^    }
  echo }
  echo function Install-Apache {
  echo ^    Ensure-Elevated
  echo ^    $installerBat = Join-Path $ScriptDir "apache-install.bat"
  echo ^    $zipFile = Join-Path $ScriptDir "apache.zip"
  echo ^    if (Test-Path $installerBat) {
  echo ^        Try-Call $installerBat
  echo ^    } elseif (Test-Path $zipFile) {
  echo ^        if (-not (Test-Path $APACHE_HOME)) { New-Item -ItemType Directory -Path $APACHE_HOME | Out-Null }
  echo ^        if (Extract-Zip -ZipPath $zipFile -Dest $APACHE_HOME) { Write-Host "Apache entpackt nach $APACHE_HOME" -ForegroundColor Green }
  echo ^    } else {
  echo ^        $dl = Read-Host "apache.zip nicht gefunden. Automatisch von Default-URL herunterladen? (y/n)"
  echo ^        if ($dl -match '^[Yy]') {
  echo ^            $out = Join-Path $ScriptDir "apache.zip"
  echo ^            if (Download-IfMissing -Url $DefaultApacheUrl -OutFile $out) {
  echo ^                if (-not (Test-Path $APACHE_HOME)) { New-Item -ItemType Directory -Path $APACHE_HOME | Out-Null }
  echo ^                Extract-Zip -ZipPath $out -Dest $APACHE_HOME | Out-Null
  echo ^            }
  echo ^        } else { Write-Host "Abgebrochen." -ForegroundColor Yellow }
  echo ^    }
  echo ^    if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) {
  echo ^        & (Join-Path $APACHE_HOME "bin\httpd.exe") -k install -n "Apache2.4" 2>$null
  echo ^        sc.exe config "Apache2.4" start= auto | Out-Null
  echo ^        Write-Host "Service-Installation versucht." -ForegroundColor Green
  echo ^    }
  echo }
  echo function Uninstall-Apache {
  echo ^    Ensure-Elevated
  echo ^    $uninst = Join-Path $ScriptDir "apache-uninstall.bat"
  echo ^    if (Test-Path $uninst) { Try-Call $uninst } else {
  echo ^        if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k uninstall -n "Apache2.4" 2>$null }
  echo ^        sc.exe delete "Apache2.4" | Out-Null
  echo ^        $del = Read-Host "Verzeichnis $APACHE_HOME loeschen? (y/n)"
  echo ^        if ($del -match '^[Yy]') { Remove-Item -Recurse -Force -Path $APACHE_HOME -ErrorAction SilentlyContinue }
  echo ^    }
  echo }
  echo function Install-PHP {
  echo ^    Ensure-Elevated
  echo ^    $installerBat = Join-Path $ScriptDir "php-install.bat"
  echo ^    $zipFile = Join-Path $ScriptDir "php.zip"
  echo ^    if (Test-Path $installerBat) { Try-Call $installerBat }
  echo ^    elseif (Test-Path $zipFile) {
  echo ^        $dest = Join-Path $APACHE_HOME "php"
  echo ^        if (-not (Test-Path $dest)) { New-Item -ItemType Directory -Path $dest | Out-Null }
  echo ^        if (Extract-Zip -ZipPath $zipFile -Dest $dest) { Write-Host "PHP entpackt nach $dest" -ForegroundColor Green }
  echo ^        Write-Host "Bitte httpd.conf anpassen, um PHP zu aktivieren." -ForegroundColor Yellow
  echo ^    } else {
  echo ^        $dl = Read-Host "php.zip nicht gefunden. Automatisch von Default-URL herunterladen? (y/n)"
  echo ^        if ($dl -match '^[Yy]') {
  echo ^            $out = Join-Path $ScriptDir "php.zip"
  echo ^            if (Download-IfMissing -Url $DefaultPhpUrl -OutFile $out) {
  echo ^                $dest = Join-Path $APACHE_HOME "php"
  echo ^                if (-not (Test-Path $dest)) { New-Item -ItemType Directory -Path $dest | Out-Null }
  echo ^                Extract-Zip -ZipPath $out -Dest $dest | Out-Null
  echo ^                Write-Host "PHP entpackt nach $dest. Bitte httpd.conf anpassen." -ForegroundColor Yellow
  echo ^            }
  echo ^        } else { Write-Host "Abgebrochen." -ForegroundColor Yellow }
  echo ^    }
  echo }
  echo function Uninstall-PHP {
  echo ^    Ensure-Elevated
  echo ^    $uninst = Join-Path $ScriptDir "php-uninstall.bat"
  echo ^    if (Test-Path $uninst) { Try-Call $uninst } else {
  echo ^        $dest = Join-Path $APACHE_HOME "php"
  echo ^        if (Test-Path $dest) { Remove-Item -Recurse -Force $dest -ErrorAction SilentlyContinue; Write-Host "PHP-Verzeichnis geloescht." -ForegroundColor Green }
  echo ^        else { Write-Host "Kein PHP-Verzeichnis gefunden." -ForegroundColor Yellow }
  echo ^    }
  echo }
  echo function Start-Apache {
  echo ^    Ensure-Elevated
  echo ^    if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) {
  echo ^        $svc = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
  echo ^        if ($null -ne $svc) { Start-Service -Name "Apache2.4" -ErrorAction SilentlyContinue; Write-Host "Service gestartet." -ForegroundColor Green }
  echo ^        else { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k start; Write-Host "Apache gestartet (bin\httpd.exe -k start)." -ForegroundColor Green }
  echo ^    } else { Write-Host "httpd.exe nicht gefunden in $APACHE_HOME" -ForegroundColor Yellow }
  echo }
  echo function Stop-Apache {
  echo ^    Ensure-Elevated
  echo ^    if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) {
  echo ^        $svc = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
  echo ^        if ($null -ne $svc) { Stop-Service -Name "Apache2.4" -ErrorAction SilentlyContinue; Write-Host "Service gestoppt." -ForegroundColor Green }
  echo ^        else { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k stop; Write-Host "Apache gestoppt (bin\httpd.exe -k stop)." -ForegroundColor Green }
  echo ^    } else { Write-Host "httpd.exe nicht gefunden in $APACHE_HOME" -ForegroundColor Yellow }
  echo }
  echo Load-Config
  echo Refresh-Status
  echo function Show-Menu {
  echo ^    Refresh-Status
  echo ^    Clear-Host
  echo ^    Write-Host "========================================="
  echo ^    Write-Host "           Webserver Manager"
  echo ^    Write-Host "========================================="
  echo ^    if ($ApacheInstalled) { $apacheLabel = "Apache - Deinstallieren" } else { $apacheLabel = "Apache - Installieren" }
  echo ^    Write-Host "[1] $apacheLabel"
  echo ^    if ($ApacheInstalled) { Write-Host "     Pfad: $APACHE_HOME"; Write-Host "[2] PHP - Installieren/Deinstallieren" }
  echo ^    Write-Host "[3] Webserver - Starten/Stoppen"
  echo ^    if ($ApacheInstalled) { Write-Host "[4] Config - Startpfad aendern" }
  echo ^    Write-Host "[0] Beenden"
  echo }
  echo while ($true) {
  echo ^    Show-Menu
  echo ^    $choice = Read-Host "Waehle eine Option"
  echo ^    switch ($choice) {
  echo ^        '1' {
  echo ^            if ($ApacheInstalled) { Uninstall-Apache } else { Install-Apache }
  echo ^            Save-Config
  echo ^            Read-Host "Druecke Enter um fortzufahren..."
  echo ^        }
  echo ^        '2' {
  echo ^            if (-not $ApacheInstalled) { Write-Host "PHP-Option nur verfuegbar wenn Apache installiert ist." -ForegroundColor Yellow; Start-Sleep -Seconds 1; continue }
  echo ^            $sub = Read-Host "[1] Installieren [2] Deinstallieren [0] Zurueck"
  echo ^            if ($sub -eq '1') { Install-PHP }
  echo ^            elseif ($sub -eq '2') { Uninstall-PHP }
  echo ^            Read-Host "Druecke Enter um fortzufahren..."
  echo ^        }
  echo ^        '3' {
  echo ^            $sub = Read-Host "[1] Starten [2] Stoppen [0] Zurueck"
  echo ^            if ($sub -eq '1') { Start-Apache }
  echo ^            elseif ($sub -eq '2') { Stop-Apache }
  echo ^            Read-Host "Druecke Enter um fortzufahren..."
  echo ^        }
  echo ^        '4' {
  echo ^            if (-not $ApacheInstalled) { Write-Host "Config nur verfuegbar wenn Apache installiert ist." -ForegroundColor Yellow; Start-Sleep -Seconds 1; continue }
  echo ^            $sub = Read-Host "[1] Pfad aendern [2] Auf Default setzen [0] Zurueck"
  echo ^            if ($sub -eq '1') { $new = Read-Host "Neuer Pfad (z.B. C:\apache)"; if ($new) { $APACHE_HOME = $new; Save-Config } }
  echo ^            elseif ($sub -eq '2') { $APACHE_HOME = "C:\apache"; Save-Config }
  echo ^            Read-Host "Druecke Enter um fortzufahren..."
  echo ^        }
  echo ^        '0' { break }
  echo ^        default { continue }
  echo ^    }
  echo }
  echo Write-Host "Beende..." -ForegroundColor Green
  echo Read-Host "Drücke Enter zum Schließen..."
)

:: Starte das PowerShell-Skript in neuem Fenster und lasse es offen (-NoExit)
start "" powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%SDIR%webmanager.ps1"

endlocal
