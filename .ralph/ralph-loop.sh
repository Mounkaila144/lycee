#!/bin/bash

# RALPH Loop Runner for CRM-API
# Usage: ./ralph-loop.sh [max_iterations]

set -e

MAX_ITERATIONS=${1:-20}
ITERATION=0
PROMPT_FILE=".ralph/PROMPT.md"
LOG_DIR=".ralph/logs"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$LOG_DIR/ralph_${TIMESTAMP}.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create logs directory
mkdir -p "$LOG_DIR"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  RALPH Loop - CRM-API Development${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Max iterations: ${YELLOW}$MAX_ITERATIONS${NC}"
echo -e "Prompt file: ${YELLOW}$PROMPT_FILE${NC}"
echo -e "Log file: ${YELLOW}$LOG_FILE${NC}"
echo ""

# Check if PROMPT.md exists
if [ ! -f "$PROMPT_FILE" ]; then
    echo -e "${RED}Error: $PROMPT_FILE not found!${NC}"
    exit 1
fi

# Main loop
while [ $ITERATION -lt $MAX_ITERATIONS ]; do
    ITERATION=$((ITERATION + 1))

    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  Iteration $ITERATION / $MAX_ITERATIONS${NC}"
    echo -e "${GREEN}  $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo -e "${GREEN}========================================${NC}"

    # Run Claude with the prompt
    echo "--- Iteration $ITERATION ---" >> "$LOG_FILE"
    echo "Started: $(date)" >> "$LOG_FILE"

    # Execute Claude Code with the prompt
    # Note: Adjust this command based on your Claude Code installation
    if command -v claude &> /dev/null; then
        cat "$PROMPT_FILE" | claude 2>&1 | tee -a "$LOG_FILE"
    else
        echo -e "${YELLOW}Claude CLI not found. Running in simulation mode.${NC}"
        echo "Simulated output for iteration $ITERATION" >> "$LOG_FILE"
    fi

    # Check for EXIT_SIGNAL in the output
    if grep -q "EXIT_SIGNAL: true" "$LOG_FILE"; then
        echo -e "${GREEN}========================================${NC}"
        echo -e "${GREEN}  EXIT_SIGNAL detected!${NC}"
        echo -e "${GREEN}========================================${NC}"

        # Check completion status
        if grep -q "COMPLETION_STATUS: ALL_TASKS_DONE" "$LOG_FILE"; then
            echo -e "${GREEN}All tasks completed successfully!${NC}"
        elif grep -q "COMPLETION_STATUS: BLOCKED" "$LOG_FILE"; then
            echo -e "${YELLOW}Loop stopped due to blocking issue.${NC}"
            grep -A1 "BLOCKING_REASON:" "$LOG_FILE" || true
        fi

        break
    fi

    echo "Ended: $(date)" >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"

    # Small delay between iterations
    sleep 2
done

if [ $ITERATION -ge $MAX_ITERATIONS ]; then
    echo -e "${YELLOW}========================================${NC}"
    echo -e "${YELLOW}  Max iterations reached ($MAX_ITERATIONS)${NC}"
    echo -e "${YELLOW}========================================${NC}"
fi

echo ""
echo -e "${BLUE}Log saved to: $LOG_FILE${NC}"
echo -e "${BLUE}Total iterations: $ITERATION${NC}"
