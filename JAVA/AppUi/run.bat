@echo off
cd /d "C:\Users\Bichou\Desktop\Workforce-Platform\JAVA\AppUi"
echo Compiling application...
call mvn clean compile
if errorlevel 1 (
    echo Build failed!
    pause
    exit /b 1
)
echo Starting application...
call mvn exec:java
pause
