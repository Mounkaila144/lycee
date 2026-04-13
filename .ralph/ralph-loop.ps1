# RALPH Loop Runner for CRM-API (PowerShell)
# Usage: .\ralph-loop.ps1 [-MaxIterations 20]

param(
    [int]$MaxIterations = 20
)

$PromptFile = ".ralph\PROMPT.md"
$LogDir = ".ralph\logs"
$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$LogFile = "$LogDir\ralph_$Timestamp.log"

# Create logs directory
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

Write-Host "========================================" -ForegroundColor Blue
Write-Host "  RALPH Loop - CRM-API Development" -ForegroundColor Blue
Write-Host "========================================" -ForegroundColor Blue
Write-Host ""
Write-Host "Max iterations: $MaxIterations" -ForegroundColor Yellow
Write-Host "Prompt file: $PromptFile" -ForegroundColor Yellow
Write-Host "Log file: $LogFile" -ForegroundColor Yellow
Write-Host ""

# Check if PROMPT.md exists
if (-not (Test-Path $PromptFile)) {
    Write-Host "Error: $PromptFile not found!" -ForegroundColor Red
    exit 1
}

$Iteration = 0

# Main loop
while ($Iteration -lt $MaxIterations) {
    $Iteration++

    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  Iteration $Iteration / $MaxIterations" -ForegroundColor Green
    Write-Host "  $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green

    # Log iteration start
    "--- Iteration $Iteration ---" | Add-Content $LogFile
    "Started: $(Get-Date)" | Add-Content $LogFile

    # Execute Claude Code with the prompt
    # Method 1: Using claude CLI directly
    try {
        $PromptContent = Get-Content $PromptFile -Raw
        $Output = $PromptContent | claude 2>&1
        $Output | Tee-Object -FilePath $LogFile -Append
    }
    catch {
        Write-Host "Claude CLI not found or error occurred." -ForegroundColor Yellow
        Write-Host "Running in simulation mode." -ForegroundColor Yellow
        "Simulated output for iteration $Iteration" | Add-Content $LogFile
    }

    # Check for EXIT_SIGNAL
    $LogContent = Get-Content $LogFile -Raw
    if ($LogContent -match "EXIT_SIGNAL: true") {
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "  EXIT_SIGNAL detected!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green

        if ($LogContent -match "COMPLETION_STATUS: ALL_TASKS_DONE") {
            Write-Host "All tasks completed successfully!" -ForegroundColor Green
        }
        elseif ($LogContent -match "COMPLETION_STATUS: BLOCKED") {
            Write-Host "Loop stopped due to blocking issue." -ForegroundColor Yellow
        }

        break
    }

    "Ended: $(Get-Date)" | Add-Content $LogFile
    "" | Add-Content $LogFile

    # Small delay between iterations
    Start-Sleep -Seconds 2
}

if ($Iteration -ge $MaxIterations) {
    Write-Host "========================================" -ForegroundColor Yellow
    Write-Host "  Max iterations reached ($MaxIterations)" -ForegroundColor Yellow
    Write-Host "========================================" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Log saved to: $LogFile" -ForegroundColor Blue
Write-Host "Total iterations: $Iteration" -ForegroundColor Blue
