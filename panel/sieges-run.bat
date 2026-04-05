@echo off
REM sieges-run.bat <activityId>
SETLOCAL ENABLEDELAYEDEXPANSION
set act=%1
if "%act%"=="" (
  echo missing activity id
  exit /b 1
)
echo TEST: running wrapper for activity %act%
set PHPCMD=
REM 0) prefer the Winamp PHP path provided by user if present
if exist "C:\winamp64\bin\php\php8.1.31\php.exe" (
  set PHPCMD="C:\winamp64\bin\php\php8.1.31\php.exe"
  echo FOUND "C:\winamp64\bin\php\php8.1.31\php.exe"
) else (
  REM 1) prefer php in PATH
  where php >nul 2>&1
  if %ERRORLEVEL%==0 (
    set PHPCMD=php
    echo FOUND php in PATH
  ) else (
    echo php not in PATH, probing common locations...
  if exist "C:\\php\\php.exe" (set PHPCMD="C:\\php\\php.exe" & echo FOUND C:\php\php.exe)
  if not defined PHPCMD if exist "C:\\Program Files\\PHP\\php.exe" (set PHPCMD="C:\\Program Files\\PHP\\php.exe" & echo FOUND "C:\Program Files\PHP\php.exe")
  if not defined PHPCMD if exist "C:\\Program Files (x86)\\PHP\\php.exe" (set PHPCMD="C:\\Program Files (x86)\\PHP\\php.exe" & echo FOUND "C:\Program Files (x86)\\PHP\\php.exe")
  if not defined PHPCMD if exist "C:\\xampp\\php\\php.exe" (set PHPCMD="C:\\xampp\\php\\php.exe" & echo FOUND "C:\xampp\php\php.exe")
  if not defined PHPCMD if exist "C:\\wamp\\bin\\php\\php.exe" (set PHPCMD="C:\\wamp\\bin\\php\\php.exe" & echo FOUND "C:\wamp\bin\php\php.exe")
  if not defined PHPCMD if exist "C:\\Program Files\\EasyPHP\\php\\php.exe" (set PHPCMD="C:\\Program Files\\EasyPHP\\php\\php.exe" & echo FOUND "C:\Program Files\EasyPHP\php\php.exe")
  if not defined PHPCMD if exist "C:\\winamp64\\bin\\php\\php8.1.31\\php.exe" (set PHPCMD="C:\\winamp64\\bin\\php\\php8.1.31\\php.exe" & echo FOUND "C:\winamp64\bin\php\php8.1.31\php.exe")
)
if "%PHPCMD%"=="" (
  echo ERROR: php.exe not found on this machine. Checked PATH and common locations.
  echo Please install PHP CLI or add it to PATH.
  exit /b 2
)
echo Using PHP command: %PHPCMD%
set OUTFILE=%~dp0tmp\sieges-worker-%act%.out
echo Logging worker output to %OUTFILE%
%PHPCMD% -d display_errors=1 -f "%~dp0sieges-worker.php" %act% >> "%OUTFILE%" 2>&1
echo worker rc=%ERRORLEVEL% >> "%OUTFILE%"
echo Done. Return code %ERRORLEVEL%
exit /b %ERRORLEVEL%