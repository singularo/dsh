#!/usr/bin/env bats
# Tests for the stop_nginx function.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
}

@test "stop_nginx does not disconnect from dsh_network" {
  run stop_nginx
  assert_success
  refute_docker_called_with "network disconnect"
}

@test "stop_nginx outputs notice about staying connected" {
  run stop_nginx
  assert_success
  assert_output --partial "dsh_network"
}
