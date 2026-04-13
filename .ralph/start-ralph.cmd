@echo off
REM RALPH Starter for Windows
REM Creates the activation flag and displays instructions

echo ========================================
echo   RALPH Mode - Activation
echo ========================================
echo.

REM Create the active flag
echo active > "%~dp0.ralph_active"

echo RALPH mode is now ACTIVE
echo.
echo To start the loop, run Claude Code with:
echo   claude "Execute les instructions dans .ralph/PROMPT.md"
echo.
echo The loop will continue until all tasks in @fix_plan.md are done.
echo.
echo To stop RALPH manually, run: stop-ralph.cmd
echo ========================================
