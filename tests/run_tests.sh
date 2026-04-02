#!/bin/bash
# Run all dsh bats tests.
# Usage: ./tests/run_tests.sh [optional bats args]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BATS="${SCRIPT_DIR}/libs/bats-core/bin/bats"

if [ ! -x "${BATS}" ]; then
  echo "Error: bats not found. Run: git submodule update --init --recursive"
  exit 1
fi

"${BATS}" "${@}" "${SCRIPT_DIR}"/*.bats
