@echo off
REM ============================================================
REM   Tasty Bites - Manage Users (full user management CLI)
REM   List / search / add / promote / demote / enable / disable
REM   reset passwords / edit / delete - straight into MySQL.
REM ============================================================
chcp 65001 >nul
title Tasty Bites - User Manager

REM ---- Locate XAMPP's php.exe ----
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" (
    for %%D in (C D E F) do (
        if exist "%%D:\xampp\php\php.exe" set "PHP=%%D:\xampp\php\php.exe"
    )
)
if not exist "%PHP%" (
    echo  [X] php.exe not found. Please install XAMPP first.
    pause
    exit /b 1
)

REM ---- Make sure MySQL is running ----
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" (
    for %%D in (C D E F) do (
        if exist "%%D:\xampp\mysql\bin\mysql.exe" set "MYSQL=%%D:\xampp\mysql\bin\mysql.exe"
    )
)
if exist "%MYSQL%" (
    "%MYSQL%" -u root -e "SELECT 1" >nul 2>&1
    if errorlevel 1 (
        echo  [..] Starting MySQL...
        call "C:\xampp\mysql_start.bat" >nul 2>&1 || (
            for %%D in (C D E F) do (
                if exist "%%D:\xampp\mysql_start.bat" call "%%D:\xampp\mysql_start.bat" >nul 2>&1
            )
        )
        timeout /t 4 /nobreak >nul
    )
)
"%PHP%" "%~dp0manage_users_cli.php"
pause
