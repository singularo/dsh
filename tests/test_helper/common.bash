# Common test helper for dsh tests.
# Sets up mock docker commands and loads dsh functions.

# Load bats libraries using absolute paths.
LIBS_DIR="${BATS_TEST_DIRNAME}/libs"
load "${LIBS_DIR}/bats-support/load"
load "${LIBS_DIR}/bats-assert/load"

# Path to the mock docker command.
MOCK_DIR="${BATS_TEST_DIRNAME}/test_helper/mocks"
MOCK_LOG="${BATS_TEST_TMPDIR}/docker_calls.log"

# Set up the test environment before each test.
setup_common() {
  # Put mock docker first in PATH.
  export PATH="${MOCK_DIR}:${PATH}"

  # Clear mock call log.
  > "${MOCK_LOG}"
  export MOCK_LOG

  # Set default mock responses (can be overridden per test).
  export MOCK_DOCKER_PS_A=""
  export MOCK_DOCKER_PS=""
  export MOCK_DOCKER_NETWORK_LS=""
  export MOCK_DOCKER_INSPECT=""

  # Set required dsh variables.
  export PROJECT="testproject"
  export DOMAIN="172.17.0.1.nip.io"
  export HOST_TYPE="linux"
  export COMPOSE_FILE="docker-compose.linux.yml"
  export USER_ID="1000"
  export GROUP_ID="1000"
  export DOCKER_COMPOSE="docker-compose --ansi never"
  export DOCKER_COMPOSE_QUIET="docker-compose --ansi never --progress quiet"
  export SHELL_CONTAINER="web"
}

# Source dsh functions without executing the case block.
# Extracts functions from the dsh script into a temp file and sources it.
load_dsh_functions() {
  local dsh_path="${BATS_TEST_DIRNAME}/../assets/dsh"
  local func_file="${BATS_TEST_TMPDIR}/dsh_functions.bash"

  # Extract just the function definitions and helper functions.
  sed -n '/^notice()/,/^COMMAND=/p' "${dsh_path}" | head -n -1 > "${func_file}"

  # Source the functions (disable strict mode for sourcing).
  set +euo pipefail
  source "${func_file}"
  set -euo pipefail
}

# Get all recorded docker calls.
get_docker_calls() {
  cat "${MOCK_LOG}"
}

# Assert that docker was called with specific arguments.
assert_docker_called_with() {
  local expected="$1"
  assert grep -qF "${expected}" "${MOCK_LOG}"
}

# Assert docker was NOT called with specific arguments.
refute_docker_called_with() {
  local unexpected="$1"
  run grep -F "${unexpected}" "${MOCK_LOG}"
  assert_failure
}
