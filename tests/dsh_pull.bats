#!/usr/bin/env bats
# Tests for the dsh_pull function.

load 'test_helper/common'

setup() {
  setup_common
  load_dsh_functions
  unset BASE_IMAGE
  mkdir -p "${BATS_TEST_TMPDIR}/project/docker"
  printf '# Comment.\nFROM example/base:1.2\nRUN true\n' > "${BATS_TEST_TMPDIR}/project/docker/Dockerfile"
  cd "${BATS_TEST_TMPDIR}/project"
}

@test "dsh_pull pulls the image the project Dockerfile builds from" {
  run dsh_pull

  assert_success
  assert_docker_called_with "docker pull example/base:1.2"
}

@test "dsh_pull honours a BASE_IMAGE override" {
  BASE_IMAGE=other/image:3 run dsh_pull

  assert_success
  assert_docker_called_with "docker pull other/image:3"
}
