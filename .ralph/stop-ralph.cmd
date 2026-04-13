@echo off
REM RALPH Stopper for Windows
REM Removes the activation flag to stop the loop

echo ========================================
echo   RALPH Mode - Deactivation
echo ========================================
echo.

REM Remove the active flag
if exist "%~dp0.ralph_active" (
    del "%~dp0.ralph_active"
    echo RALPH mode has been DEACTIVATED
) else (
    echo RALPH mode was not active
)

echo ========================================
