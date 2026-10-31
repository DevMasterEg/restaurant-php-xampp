@echo off
REM ============================================================
REM   Tasty Bites - Start everything (XAMPP Apache + MySQL)
REM   Works from ANY folder name inside htdocs (even "0").
REM   If the database is missing, it imports it automatically.
REM ============================================================
setlocal enabledelayedexpansion
chcp 65001 >nul
title Tasty Bites - Start

REM ---- Locate XAMPP ----
set "XAMPP=C:\xampp"
if not exist "%XAMPP%\apache\bin\httpd.exe" (
    for %%D in (C D E F) do (
        if exist "%%D:\xampp\apache\bin\httpd.exe" set "XAMPP=%%D:\xampp"
    )
)
if not exist "%XAMPP%\apache\bin\httpd.exe" (
    echo  [X] XAMPP not found. Please install XAMPP first.
    pause
    exit /b 1
)

REM ---- Detect this project's folder name (for the correct URL) ----
for %%I in ("%~dp0.") do set "APPFOLDER=%%~nxI"
set "APPPATH=/!APPFOLDER!"
if /i "!APPFOLDER!"=="htdocs" set "APPPATH="
echo  [OK] App folder detected: !APPFOLDER!  -^> http://localhost!APPPATH!/

REM ---- Start MySQL ----
echo  [..] Starting MySQL...
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    if exist "%XAMPP%\mysql_start.bat" call "%XAMPP%\mysql_start.bat" >nul 2>&1
    timeout /t 4 /nobreak >nul
)
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo  [X] MySQL failed to start. Open XAMPP Control Panel and start it manually.
) else (
    echo  [OK] MySQL running
)

REM ---- Make sure the database exists (auto-import if missing) ----
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "USE restaurant_accounts; SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo  [..] Databases not found - importing database.sql (creates 3 databases)...
    "%XAMPP%\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%~dp0database.sql"
    if errorlevel 1 (
        echo  [X] Import failed. Run setup.bat to see the exact error.
    ) else (
        echo  [OK] Databases created: restaurant_accounts, restaurant_menu, restaurant_orders
    )
) else (
    echo  [OK] Databases exist (restaurant_accounts / _menu / _orders)
)

REM ---- Start Apache ----
echo  [..] Starting Apache...
if exist "%XAMPP%\apache_start.bat" call "%XAMPP%\apache_start.bat" >nul 2>&1
timeout /t 3 /nobreak >nul
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I "httpd.exe" >nul
if errorlevel 1 (
    echo  [!] Apache may not be running - start it in the XAMPP Control Panel.
) else (
    echo  [OK] Apache running
)

echo.
echo  ==========================================
echo   Your restaurant app:
echo     http://localhost!APPPATH!/
echo   Admin login:     admin@restaurant.com / admin123
echo   Customer demo:   customer@restaurant.com / customer123
echo   Manage users:    manage-users.bat
echo   Problems? Open:  http://localhost!APPPATH!/check.php
echo  ==========================================
echo.
start "" "http://localhost!APPPATH!/"
echo  Tip: you can close this window - the servers keep running.
timeout /t 8 /nobreak >nul
