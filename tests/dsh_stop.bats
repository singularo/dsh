#!/usr/bin/env bats
# Tests for dsh_stop and dsh_down functions.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
}

@test "dsh_stop calls docker-compose stop" {
  run dsh_stop
  assert_success
  assert_docker_called_with "docker-compose --ansi never --progress quiet stop"
}

@test "dsh_stop does not disconnect nginx from network" {
  run dsh_stop
  assert_success
  refute_docker_called_with "network disconnect"
}

@test "dsh_down calls docker-compose down with volumes" {
  run dsh_down
  assert_success
  assert_docker_called_with "docker-compose --ansi never --progress quiet down -v"
}

@test "dsh_down does not disconnect nginx from network" {
  run dsh_down
  assert_success
  refute_docker_called_with "network disconnect"
}
