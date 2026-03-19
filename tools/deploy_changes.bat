@echo off
REM Deployment helper for Windows (local use only).
REM Usage: double-click or run from git repo root.
REM Optional arguments: deploy_changes.bat <from> <to>

setlocal

set FROM=%1
set TO=%2

if "%FROM%"=="" set FROM=HEAD~1
if "%TO%"=="" set TO=HEAD

echo Comparing %FROM%..%TO%

git diff --name-only %FROM% %TO%

echo.
echo Review the list above and upload the changed files to /install/ on admin.peopledisplay.nl.
pause
