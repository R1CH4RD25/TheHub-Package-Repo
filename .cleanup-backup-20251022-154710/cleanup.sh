#!/bin/bash
# Auto-generated cleanup script
# Review before executing!

set -e

echo 'Starting cleanup...'


# Remove PHP files
rm -f 'cli/test-email.php'
echo 'Removed: cli/test-email.php'
rm -f 'cli/test-themes.php'
echo 'Removed: cli/test-themes.php'
rm -f 'test-groups.php'
echo 'Removed: test-groups.php'

echo 'Cleanup complete!'
