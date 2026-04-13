# RALPH Stop Hook for Claude Code (PowerShell)
# This hook checks if there are remaining tasks and re-injects the prompt if needed

$RalphDir = Split-Path -Parent $PSScriptRoot
$FixPlan = Join-Path $RalphDir "@fix_plan.md"
$ActiveFlag = Join-Path $RalphDir ".ralph_active"

# Read stdin (Claude Code passes context as JSON)
$Input = $input | Out-String

# Check if RALPH is active
if (-not (Test-Path $ActiveFlag)) {
    # RALPH not active, allow normal stop
    Write-Output '{"decision": "allow"}'
    exit 0
}

# Check for EXIT_SIGNAL in the response
if ($Input -match "EXIT_SIGNAL: true") {
    # Exit signal detected, clean up and allow stop
    Remove-Item -Path $ActiveFlag -Force -ErrorAction SilentlyContinue
    Write-Output '{"decision": "allow"}'
    exit 0
}

# Check for COMPLETION_STATUS: ALL_TASKS_DONE
if ($Input -match "COMPLETION_STATUS: ALL_TASKS_DONE") {
    Remove-Item -Path $ActiveFlag -Force -ErrorAction SilentlyContinue
    Write-Output '{"decision": "allow"}'
    exit 0
}

# Check for COMPLETION_STATUS: BLOCKED
if ($Input -match "COMPLETION_STATUS: BLOCKED") {
    Remove-Item -Path $ActiveFlag -Force -ErrorAction SilentlyContinue
    Write-Output '{"decision": "allow"}'
    exit 0
}

# Check if there are pending tasks
if (Test-Path $FixPlan) {
    $Content = Get-Content $FixPlan -Raw
    $PendingCount = ([regex]::Matches($Content, "\[PENDING\]")).Count
    $InProgressCount = ([regex]::Matches($Content, "\[IN_PROGRESS\]")).Count

    if ($PendingCount -gt 0 -or $InProgressCount -gt 0) {
        # There are remaining tasks, re-inject the prompt
        Write-Output '{"decision": "block", "reason": "Continue avec la prochaine tache selon .ralph/PROMPT.md"}'
        exit 0
    }
}

# No remaining tasks, allow stop
Remove-Item -Path $ActiveFlag -Force -ErrorAction SilentlyContinue
Write-Output '{"decision": "allow"}'
exit 0
