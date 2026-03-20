@echo off
REM Deployment helper for Windows (local use only).
REM Usage: double-click or run from git repo root.
REM Optional arguments: deploy_changes.bat <from> <to>

setlocal

set FROM=%1
set TO=%2

if "%TO%"=="" set TO=HEAD

if "%FROM%"=="" (
    REM Prefer the most recent annotated tag when available (last released version)
    for /f "delims=" %%t in ('git describe --tags --abbrev=0 2^>nul') do set FROM=%%t
    if "%FROM%"=="" set FROM=HEAD~10
)

REM If the user explicitly wants a tag, it will be used as FROM.

echo Comparing %FROM%..%TO%

git diff --name-only %FROM% %TO%

echo.
echo (Alternative example: git diff --name-only v2.0.5..HEAD)
if "%FROM%"=="HEAD~10" echo (No tags found; showing changes from last 10 commits.)

echo Review the list above and upload the changed files to /install/ on admin.peopledisplay.nl.
pause
