#!/bin/bash
# Helper script to verify file contents without caching
# Usage: ./verify_file_content.sh <filepath> <search_string>

if [ $# -lt 2 ]; then
    echo "Usage: $0 <filepath> <search_string>"
    exit 1
fi

FILEPATH="$1"
SEARCH_STRING="$2"

if [ ! -f "$FILEPATH" ]; then
    echo "ERROR: File not found: $FILEPATH"
    exit 1
fi

echo "=== Verifying: $FILEPATH ==="
echo "Looking for: $SEARCH_STRING"
echo ""

if grep -q "$SEARCH_STRING" "$FILEPATH"; then
    echo "✅ FOUND"
    echo "Context:"
    grep -n -C 3 "$SEARCH_STRING" "$FILEPATH" | head -20
    exit 0
else
    echo "❌ NOT FOUND"
    echo "File size: $(wc -l < "$FILEPATH") lines"
    echo "Last modified: $(stat -c %y "$FILEPATH")"
    exit 1
fi
