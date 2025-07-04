@echo off
REM This script is used to clean up expired vouchers in the Super Spot Wifi application
REM Check if the directory exists
if not exist "C:\xampp\htdocs\super-spot-wifi" (
    echo Directory C:\xampp\htdocs\super-spot-wifi does not exist.
    exit /b 1
)
REM Change to the application directory
cd C:\xampp\htdocs\super-spot-wifi
REM Run the Laravel Artisan command to clean up expired vouchers
php artisan app:cleanup-expired-vouchers

REM Check if the command was successful
if %errorlevel% neq 0 (
    echo Error occurred while cleaning up expired vouchers.
    exit /b %errorlevel%
)
REM Check if the command was successful
echo Cleanup of expired vouchers completed successfully.