#!/usr/bin/env bats
# Tests for the dsh_build function.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
}

@test "dsh_build builds project images without starting containers" {
  run dsh_build

  assert_success
  assert_docker_called_with "docker-compose --ansi never --progress quiet build"
  refute_docker_called_with "up -d"
}
