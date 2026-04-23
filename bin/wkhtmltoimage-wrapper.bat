@echo off
"C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe" %*
exit /b %ERRORLEVEL%

