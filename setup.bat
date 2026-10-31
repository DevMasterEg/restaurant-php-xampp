@echo off
REM ============================================================
REM   Tasty Bites - One-time setup for XAMPP (Windows)
REM   1) Finds your XAMPP folder
REM   2) Imports database.sql into MySQL (creates the 3 databases:
REM      restaurant_accounts, restaurant_menu, restaurant_orders)
REM   3) Verifies the tables
REM ============================================================
setlocal enabledelayedexpansion
chcp 65001 >nul
title Tasty Bites - Setup

echo.
echo  ============================================
echo    Tasty Bites Restaurant - First-time Setup
echo  ============================================
echo.

REM ---- Locate XAMPP (default C:\xampp) ----
set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    if exist "D:\xampp\mysql\bin\mysql.exe" set "XAMPP=D:\xampp"
)
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    for %%D in (C D E F) do (
        if exist "%%D:\xampp\mysql\bin\mysql.exe" set "XAMPP=%%D:\xampp"
    )
)
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo  [X] Could not find XAMPP automatically.
    set /p "XAMPP=  Type the path to your xampp folder (e.g. C:\xampp): "
)
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
    echo  [X] MySQL not found in "%XAMPP%". Is XAMPP installed?
    pause
    exit /b 1
)
echo  [OK] XAMPP found at: %XAMPP%

REM ---- Warn if MySQL not running, try to start it ----
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
    echo  [..] MySQL is not running - trying to start it...
    if exist "%XAMPP%\mysql_start.bat" (
        call "%XAMPP%\mysql_start.bat" >nul 2>&1
    )
    timeout /t 5 /nobreak >nul
    "%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT 1" >nul 2>&1
    if errorlevel 1 (
        echo  [X] Could not start MySQL.
        echo      Open the XAMPP Control Panel, start MySQL, then run setup.bat again.
        pause
        exit /b 1
    )
)
echo  [OK] MySQL is running

REM ---- Import database.sql ----
echo  [..] Importing database.sql ^(creates 3 databases + demo data^)...
"%XAMPP%\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 < "%~dp0database.sql"
if errorlevel 1 (
    echo  [X] Import failed - see message above.
    pause
    exit /b 1
)
echo  [OK] Databases created: restaurant_accounts, restaurant_menu, restaurant_orders

REM ---- Sanity check ----
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT COUNT(*) AS users FROM restaurant_accounts.users; SELECT COUNT(*) AS menu_items FROM restaurant_menu.menu_items; SELECT COUNT(*) AS order_tables FROM information_schema.tables WHERE table_schema='restaurant_orders';" 2>nul | findstr /R "[0-9]" >nul
if errorlevel 1 (
    echo  [!] Warning: could not verify tables.
) else (
    echo  [OK] Tables verified
)

REM ---- Next steps (auto-detect this folder's name for the URL) ----
for %%I in ("%~dp0.") do set "APPFOLDER=%%~nxI"
set "APPPATH=/%APPFOLDER%"
if /i "%APPFOLDER%"=="htdocs" set "APPPATH="
echo.
echo  ------------------------------------------------------------
echo   Next steps:
echo   1. Keep this folder inside: %XAMPP%\htdocs\
echo      ^(detected location: %XAMPP%\htdocs\%APPFOLDER%^)
echo   2. Start Apache in the XAMPP Control Panel
echo      ^(or double-click start-restaurant.bat^)
echo   3. Open:  http://localhost%APPPATH%/
echo      If anything fails, open: http://localhost%APPPATH%/check.php
echo.
echo   Admin login:   admin@restaurant.com / admin123
echo   Customer demo: customer@restaurant.com / customer123
echo   Manage users:  double-click manage-users.bat
echo  ------------------------------------------------------------
pause
