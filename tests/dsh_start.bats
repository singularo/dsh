#!/usr/bin/env bats
# Tests for the dsh_start function.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
}

@test "dsh_start brings up containers when not running" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  assert_docker_called_with "docker-compose --ansi never up -d"
}

@test "dsh_start skips when containers already running" {
  MOCK_DOCKER_PS="testproject_web"
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  refute_docker_called_with "up -d"
}

@test "dsh_start calls setup_nginx" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  # setup_nginx should have checked for nginx-proxy.
  assert_docker_called_with "nginx-proxy"
}

@test "dsh_start displays project URL" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  assert_output --partial "http://testproject.172.17.0.1.nip.io:8080"
}

@test "dsh_start quiet mode suppresses URL output" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start quiet
  assert_success
  refute_output --partial "http://testproject"
}

@test "dsh_start does not create per-project network" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  # Should NOT create a project-specific network.
  refute_docker_called_with "network create testproject_default"
}

@test "dsh_start creates dsh_network before compose up" {
  MOCK_DOCKER_PS=""
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_NETWORK_LS=""
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run dsh_start
  assert_success
  # Network creation must appear in the log before compose up.
  assert_docker_called_with "network create dsh_network"
  assert_docker_called_with "docker-compose --ansi never up -d"
}
