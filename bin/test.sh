#!/usr/bin/env bash
# =============================================================================
# test.sh
# Runs all standalone plugin test scripts.
#
# Usage:
#   ./bin/test.sh
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

TEST_FILES=(
  "tests/test-ms365-auth.php"
  "tests/test-ms365-admin.php"
  "tests/test-ms365-login.php"
)

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php is not installed or not available in PATH." >&2
  exit 1
fi

echo "==> Running standalone test scripts"

failures=0
for test_file in "${TEST_FILES[@]}"; do
  echo ""
  echo "---- $test_file ----"
  if ! php "$PLUGIN_DIR/$test_file"; then
    failures=$((failures + 1))
  fi
done

echo ""
if [[ "$failures" -eq 0 ]]; then
  echo "==> All tests passed"
  exit 0
fi

echo "==> Test run finished with $failures failing script(s)"
exit 1
