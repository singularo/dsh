#!/usr/bin/env bats
# Tests for the setup_nginx function.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
}

@test "ensure_network creates dsh_network when it does not exist" {
  MOCK_DOCKER_NETWORK_LS=""

  run ensure_network
  assert_success
  assert_docker_called_with "network create dsh_network"
}

@test "ensure_network skips creation when dsh_network exists" {
  MOCK_DOCKER_NETWORK_LS="dsh_network"

  run ensure_network
  assert_success
  refute_docker_called_with "network create dsh_network"
}

@test "ensure_network quiet mode suppresses output" {
  MOCK_DOCKER_NETWORK_LS=""

  run ensure_network quiet
  assert_success
  refute_output --partial "Creating dsh_network"
}

@test "setup_nginx starts nginx-proxy when not running" {
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_PS="nginx-proxy"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run setup_nginx
  assert_success
  assert_docker_called_with "run -d -p 8080:80"
  assert_docker_called_with "nginxproxy/nginx-proxy:1.3.1"
}

@test "setup_nginx restarts nginx-proxy when already exists" {
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_PS_A="nginx-proxy"
  MOCK_DOCKER_PS="nginx-proxy"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run setup_nginx
  assert_success
  assert_docker_called_with "restart nginx-proxy"
  refute_docker_called_with "run -d"
}

@test "setup_nginx connects nginx-proxy to dsh_network when not connected" {
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_PS_A="nginx-proxy"
  MOCK_DOCKER_PS="nginx-proxy"
  # Empty inspect = not connected to network.
  MOCK_DOCKER_INSPECT=""

  run setup_nginx
  assert_success
  assert_docker_called_with "network connect dsh_network nginx-proxy"
}

@test "setup_nginx skips connect when nginx-proxy already on dsh_network" {
  MOCK_DOCKER_NETWORK_LS="dsh_network"
  MOCK_DOCKER_PS_A="nginx-proxy"
  MOCK_DOCKER_PS="nginx-proxy"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run setup_nginx
  assert_success
  refute_docker_called_with "network connect"
}

@test "setup_nginx quiet mode suppresses output" {
  MOCK_DOCKER_PS_A=""
  MOCK_DOCKER_PS="nginx-proxy"
  MOCK_DOCKER_INSPECT="172.18.0.2"

  run setup_nginx quiet
  assert_success
  refute_output --partial "Starting nginx proxy"
  refute_output --partial "Connecting nginx-proxy"
}
