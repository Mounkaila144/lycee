#!/bin/bash

# RALPH Stop Hook for Claude Code
# This hook checks if there are remaining tasks and re-injects the prompt if needed

RALPH_DIR="$(dirname "$0")/.."
FIX_PLAN="$RALPH_DIR/@fix_plan.md"
ACTIVE_FLAG="$RALPH_DIR/.ralph_active"

# Read stdin (Claude Code passes context as JSON)
INPUT=$(cat)

# Check if RALPH is active
if [ ! -f "$ACTIVE_FLAG" ]; then
    # RALPH not active, allow normal stop
    echo '{"decision": "allow"}'
    exit 0
fi

# Check for EXIT_SIGNAL in the response
if echo "$INPUT" | grep -q "EXIT_SIGNAL: true"; then
    # Exit signal detected, clean up and allow stop
    rm -f "$ACTIVE_FLAG"
    echo '{"decision": "allow"}'
    exit 0
fi

# Check for COMPLETION_STATUS: ALL_TASKS_DONE
if echo "$INPUT" | grep -q "COMPLETION_STATUS: ALL_TASKS_DONE"; then
    rm -f "$ACTIVE_FLAG"
    echo '{"decision": "allow"}'
    exit 0
fi

# Check for COMPLETION_STATUS: BLOCKED
if echo "$INPUT" | grep -q "COMPLETION_STATUS: BLOCKED"; then
    rm -f "$ACTIVE_FLAG"
    echo '{"decision": "allow"}'
    exit 0
fi

# Check if there are pending tasks
if [ -f "$FIX_PLAN" ]; then
    PENDING_COUNT=$(grep -c "\[PENDING\]" "$FIX_PLAN" 2>/dev/null || echo "0")
    IN_PROGRESS_COUNT=$(grep -c "\[IN_PROGRESS\]" "$FIX_PLAN" 2>/dev/null || echo "0")

    if [ "$PENDING_COUNT" -gt 0 ] || [ "$IN_PROGRESS_COUNT" -gt 0 ]; then
        # There are remaining tasks, re-inject the prompt
        PROMPT=$(cat "$RALPH_DIR/PROMPT.md" | tr '\n' ' ' | sed 's/"/\\"/g')
        echo "{\"decision\": \"block\", \"reason\": \"Continue avec la prochaine tache selon .ralph/PROMPT.md\"}"
        exit 0
    fi
fi

# No remaining tasks, allow stop
rm -f "$ACTIVE_FLAG"
echo '{"decision": "allow"}'
exit 0
