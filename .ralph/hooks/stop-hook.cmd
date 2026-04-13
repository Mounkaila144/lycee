@echo off
REM RALPH Stop Hook Wrapper for Windows
REM Executes the PowerShell stop hook script

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop-hook.ps1"
