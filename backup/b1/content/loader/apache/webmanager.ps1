# webmanager.ps1
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$CfgFile = Join-Path $ScriptDir "webmanager.cfg"
if (-not (Test-Path $CfgFile)) { "APACHE_HOME=C:\apache" | Out-File -FilePath $CfgFile -Encoding UTF8 }

function Load-Config {
  $lines = Get-Content $CfgFile
  foreach ($l in $lines) {
    $parts = $l -split '='
    if ($parts[0].Trim() -eq 'APACHE_HOME') { $global:APACHE_HOME = $parts[1].Trim() }
  }
}
Load-Config

function Ensure-Elevated {
  $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
  if (-not $isAdmin) {
    Start-Process -FilePath "powershell.exe" -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$($MyInvocation.MyCommand.Path)`"" -Verb RunAs
    exit
  }
}

function Refresh-Status {
  $global:ApacheInstalled = Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")
  $svc = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
  $global:ApacheService = $null -ne $svc
  $global:PhpFound = Test-Path (Join-Path $APACHE_HOME "php\php.exe") -or Test-Path (Join-Path $ScriptDir "php\php.exe")
}

function Save-Config { "APACHE_HOME=$APACHE_HOME" | Out-File -FilePath $CfgFile -Encoding UTF8 }

Refresh-Status

while ($true) {
  Refresh-Status
  Clear-Host
  if ($ApacheInstalled) { $apacheLabel = "Apache - Deinstallieren" } else { $apacheLabel = "Apache - Installieren" }
  Write-Host "========================================="
  Write-Host "           Webserver Manager"
  Write-Host "========================================="
  Write-Host "[1] $apacheLabel"
  if ($ApacheInstalled) { Write-Host "     Pfad: $APACHE_HOME"; Write-Host "[2] PHP - Installieren/Deinstallieren" }
  Write-Host "[3] Webserver - Starten/Stoppen"
  if ($ApacheInstalled) { Write-Host "[4] Config - Startpfad aendern" }
  Write-Host "[0] Beenden"
  $choice = Read-Host "Waehle eine Option"

  switch ($choice) {
    '1' {
      Ensure-Elevated
      if ($ApacheInstalled) {
        if (Test-Path (Join-Path $ScriptDir "apache-uninstall.bat")) { & (Join-Path $ScriptDir "apache-uninstall.bat") }
        else { if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k uninstall -n "Apache2.4" } sc.exe delete "Apache2.4" | Out-Null; $del = Read-Host "Verzeichnis loeschen? (y/n)"; if ($del -match '^[Yy]') { Remove-Item -Recurse -Force $APACHE_HOME } }
      } else {
        if (Test-Path (Join-Path $ScriptDir "apache-install.bat")) { & (Join-Path $ScriptDir "apache-install.bat") }
        elseif (Test-Path (Join-Path $ScriptDir "apache.zip")) { if (-not (Test-Path $APACHE_HOME)) { New-Item -ItemType Directory -Path $APACHE_HOME | Out-Null }; Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory((Join-Path $ScriptDir "apache.zip"), $APACHE_HOME) }
        if (Test-Path (Join-Path $APACHE_HOME "bin\httpd.exe")) { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k install -n "Apache2.4"; sc.exe config "Apache2.4" start= auto | Out-Null }
      }
      Save-Config
      Read-Host "Druecke Enter um fortzufahren..."
    }
    '2' {
      if (-not $ApacheInstalled) { Write-Host "PHP-Option nur verfuegbar wenn Apache installiert ist."; Start-Sleep -Seconds 1; continue }
      $sub = Read-Host "[1] Installieren [2] Deinstallieren [0] Zurueck"
      if ($sub -eq '1') { Ensure-Elevated; if (Test-Path (Join-Path $ScriptDir "php-install.bat")) { & (Join-Path $ScriptDir "php-install.bat") } elseif (Test-Path (Join-Path $ScriptDir "php.zip")) { $dest = Join-Path $APACHE_HOME "php"; if (-not (Test-Path $dest)) { New-Item -ItemType Directory -Path $dest | Out-Null }; Add-Type -AssemblyName System.IO.Compression.FileSystem; [IO.Compression.ZipFile]::ExtractToDirectory((Join-Path $ScriptDir "php.zip"), $dest); Write-Host "Bitte httpd.conf anpassen." } }
      elseif ($sub -eq '2') { Ensure-Elevated; if (Test-Path (Join-Path $ScriptDir "php-uninstall.bat")) { & (Join-Path $ScriptDir "php-uninstall.bat") } else { $dest = Join-Path $APACHE_HOME "php"; if (Test-Path $dest) { Remove-Item -Recurse -Force $dest } } }
      Read-Host "Druecke Enter um fortzufahren..."
    }
    '3' {
      $sub = Read-Host "[1] Starten [2] Stoppen [0] Zurueck"
      if ($sub -eq '1') { Ensure-Elevated; if ($ApacheInstalled) { if ($ApacheService) { Start-Service -Name "Apache2.4" -ErrorAction SilentlyContinue } else { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k start } } else { Write-Host "Apache nicht installiert." } }
      elseif ($sub -eq '2') { Ensure-Elevated; if ($ApacheInstalled) { if ($ApacheService) { Stop-Service -Name "Apache2.4" -ErrorAction SilentlyContinue } else { & (Join-Path $APACHE_HOME "bin\httpd.exe") -k stop } } else { Write-Host "Apache nicht installiert." } }
      Read-Host "Druecke Enter um fortzufahren..."
    }
    '4' {
      if (-not $ApacheInstalled) { Write-Host "Config nur verfuegbar wenn Apache installiert ist."; Start-Sleep -Seconds 1; continue }
      $sub = Read-Host "[1] Pfad aendern [2] Auf Default setzen [0] Zurueck"
      if ($sub -eq '1') { $new = Read-Host "Neuer Pfad (z.B. C:\apache)"; if ($new) { $APACHE_HOME = $new; Save-Config } }
      elseif ($sub -eq '2') { $APACHE_HOME = "C:\apache"; Save-Config }
      Read-Host "Druecke Enter um fortzufahren..."
    }
    '0' { break }
    default { continue }
  }
}

Write-Host "Beende..."
