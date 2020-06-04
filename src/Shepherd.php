<?php

declare(strict_types=1);

namespace Singularo\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem as ComposerFilesystem;
use Symfony\Component\Filesystem\Filesystem;

class Shepherd {

  /**
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * @var string $eventName
   */
  protected $eventName;

  /**
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $filesystem;

  /**
   * @var string $root
   */
  protected $root;

  /**
   * @var string $settings
   */
  protected $settings;

  /**
   * Construct a Config object.
   *
   * @param \Composer\Composer $composer
   *   Composer package object.
   * @param string $event_name
   *   The event name.
   */
  public function __construct(Composer $composer, IOInterface $io, string $event_name) {
    $this->composer = $composer;
    $this->io = $io;
    $this->eventName = $event_name;
    $this->filesystem = new Filesystem();

    $this->root = $this->getProjectPath() . '/web';
    $this->settings = $this->root . '/sites/default/settings.php';
  }

  /**
   * Create settings.php file and inject Shepherd-specific settings.
   *
   * Note: does nothing if the file already exists.
   */
  public function populateSettingsFile() {
    // Check if settings.php exists, create it if not.
    if (!file_exists($this->settings)) {
      $this->filesystem->copy($this->root . '/sites/default/default.settings.php', $this->settings);
    }

    // If we haven't already written to settings.php.
    if (!(strpos(file_get_contents($this->settings), 'START SHEPHERD CONFIG') !== FALSE)) {
      // Append Shepherd-specific environment variable settings to settings.php.
      file_put_contents(
        $this->settings,
        $this->generateSettings(),
        FILE_APPEND
      );
    }
  }

  /**
   * Generates the "template" settings.php configuration.
   *
   * @return string
   *   PHP code.
   * @throws \Exception
   */
  public function generateSettings() {
    return "\n/**\n * START SHEPHERD CONFIG\n */\n" .
      "// Allow using an sqlite db for testing etc.\n" .
      "if (getenv('SQLITE_DATABASE')) {\n" .
      "  \$databases['default']['default'] = [\n" .
      "    'driver' => 'sqlite',\n" .
      "    'database' => getenv('SQLITE_DATABASE'),\n" .
      "  ];\n" .
      "}\n" .
      "else {\n" .
      "  \$databases['default']['default'] = array (\n" .
      "    'database' => getenv('DATABASE_NAME') ?: 'drupal',\n" .
      "    'username' => getenv('DATABASE_USER') ?: 'user',\n" .
      "    'password' => getenv('DATABASE_PASSWORD_FILE') ? file_get_contents(getenv('DATABASE_PASSWORD_FILE')) : 'password',\n" .
      "    'host' => getenv('DATABASE_HOST') ?: '127.0.0.1',\n" .
      "    'port' => getenv('DATABASE_PORT') ?: '3306',\n" .
      "    'driver' => getenv('DATABASE_DRIVER') ?: 'mysql',\n" .
      "    'prefix' => getenv('DATABASE_PREFIX') ?: '',\n" .
      "    'collation' => getenv('DATABASE_COLLATION') ?: 'utf8mb4_general_ci',\n" .
      "    'namespace' => getenv('DATABASE_NAMESPACE') ?: 'Drupal\\\\Core\\\\Database\\\\Driver\\\\mysql',\n" .
      "  );\n" .
      "}\n" .
      "\$settings['file_private_path'] = getenv('PRIVATE_DIR') ?: '/shared/private';\n" .
      "\$settings['file_temp_path'] = getenv('TMP_DIR') ?: '/shared/tmp';\n" .
      "\$settings['hash_salt'] = getenv('HASH_SALT') ?: '" . str_replace(['+', '/', '='], [
        '-',
        '_',
        '',
      ], base64_encode(random_bytes(55))) . "';\n" .
      "\$settings['config_sync_directory'] = DRUPAL_ROOT . '/../config-sync';\n" .
      "\$settings['shepherd_site_id'] = getenv('SHEPHERD_SITE_ID');\n" .
      "\$settings['shepherd_url'] = getenv('SHEPHERD_URL');\n" .
      "\$settings['shepherd_token'] = getenv('SHEPHERD_TOKEN_FILE') ? file_get_contents(getenv('SHEPHERD_TOKEN_FILE')) : getenv('SHEPHERD_TOKEN');\n\n" .
      "if (getenv('REDIS_ENABLED')) {\n" .
      "  \$settings['redis.connection']['interface'] = 'PhpRedis';\n" .
      "  \$settings['redis.connection']['host'] = getenv('REDIS_HOST') ?: '127.0.0.1';\n" .
      "  // Always set the fast backend for bootstrap, discover and config, otherwise\n" .
      "  // this gets lost when redis is enabled.\n" .
      "  \$settings['cache']['bins']['bootstrap'] = 'cache.backend.chainedfast';\n" .
      "  \$settings['cache']['bins']['discovery'] = 'cache.backend.chainedfast';\n" .
      "  \$settings['cache']['bins']['config'] = 'cache.backend.chainedfast';\n\n" .
      "  \$settings['cache_prefix']['default'] = getenv('REDIS_PREFIX') ?: '';\n" .
      "  // If we're not installing, include the redis services.\n" .
      "  if (!isset(\$GLOBALS['install_state'])) {\n" .
      "    \$settings['cache']['default'] = 'cache.backend.redis';\n" .
      "    \$settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';\n" .
      "  }\n" .
      "  if (getenv('REDIS_PASSWORD_FILE') || getenv('REDIS_PASSWORD')) {\n" .
      "    \$settings['redis.connection']['password'] = getenv('REDIS_PASSWORD_FILE') ? file_get_contents(getenv('REDIS_PASSWORD_FILE')) : getenv('REDIS_PASSWORD');\n" .
      "  }\n" .
      "}\n" .
      "if (getenv('MEMCACHE_ENABLED')) {\n" .
      "  \$memcache_host = getenv('MEMCACHE_HOST') ?: '127.0.0.1';\n" .
      "  \$memcache_port = getenv('MEMCACHE_PORT') ?: '11211';\n" .
      "  \$settings['memcache']['servers'] = [\$memcache_host . ':' . \$memcache_port => 'default'];\n" .
      "  \$settings['memcache']['bins'] = ['default' => 'default'];\n" .
      "  \$settings['memcache']['key_prefix'] = getenv('MEMCACHE_PREFIX') ?: '';\n" .
      "  // If we're not installing, include the memcache services.\n" .
      "  if (!isset(\$GLOBALS['install_state'])) {\n" .
      "    \$settings['cache']['default'] = 'cache.backend.memcache';\n" .
      "  }\n" .
      "}\n" .
      "if (getenv('SHEPHERD_SECRET_PATH')) {\n" .
      "  \$settings['shepherd_secrets'] = []; \n" .
      "  // Glob the secret path for secrets, that match pattern \n" .
      "  foreach ( glob( rtrim(getenv('SHEPHERD_SECRET_PATH'),DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'SHEPHERD_*') as \$secret) {\n" .
      "    \$settings['shepherd_secrets'][pathinfo(\$secret)['filename']] = file_get_contents(\$secret);\n" .
      "  }\n" .
      "}\n" .
      "if (getenv('SHEPHERD_REVERSE_PROXY')) {\n" .
      "  \$settings['reverse_proxy'] = TRUE; \n" .
      "  \$settings['reverse_proxy_header'] = getenv('SHEPHERD_REVERSE_PROXY_HEADER') ?: 'X_CLUSTER_CLIENT_IP';\n" .
      "  \$settings['reverse_proxy_addresses'] = !empty(getenv('SHEPHERD_REVERSE_PROXY_ADDRESSES')) ? explode(',', getenv('SHEPHERD_REVERSE_PROXY_ADDRESSES')) : [];\n" .
      "  \$settings['reverse_proxy_proto_header'] = getenv('SHEPHERD_REVERSE_PROXY_PROTO_HEADER') ?: 'X_FORWARDED_PROTO';\n" .
      "  \$settings['reverse_proxy_host_header'] = getenv('SHEPHERD_REVERSE_PROXY_HOST_HEADER') ?: 'X_FORWARDED_HOST';\n" .
      "  \$settings['reverse_proxy_port_header'] = getenv('SHEPHERD_REVERSE_PROXY_PORT_HEADER') ?: 'X_FORWARDED_PORT';\n" .
      "  \$settings['reverse_proxy_forwarded_header'] = getenv('SHEPHERD_REVERSE_PROXY_FORWARDED_HEADER') ?: 'FORWARDED';\n" .
      "}\n" .
      "if (getenv('TRUSTED_HOST_PATTERNS')) {\n" .
      "  \$settings['trusted_host_patterns'] = !empty(getenv('TRUSTED_HOST_PATTERNS')) ? explode(',', getenv('TRUSTED_HOST_PATTERNS')) : [];\n" .
      "}\n" .
      "/**\n * END SHEPHERD CONFIG\n */\n" .
      "\n" .
      "/**\n * START LOCAL CONFIG\n */\n" .
      "if (file_exists(__DIR__ . '/settings.local.php')) {\n" .
      "  include __DIR__ . '/settings.local.php';\n" .
      "}\n" .
      "/**\n * END LOCAL CONFIG\n */\n";
  }

  /**
   * Remove all write permissions on Drupal configuration files and folders.
   */
  public function makeReadOnly() {
    $this->checkExistsSetPerm([
      $this->root . '/sites/default' => 0555,
      $this->root . '/sites/default/default.services.yml' => 0664,
      $this->settings => 0444,
      $this->getProjectPath() . '/dsh' => 0755,
      $this->getProjectPath() . '/dsh_bash' => 0755,
    ]);
  }

  /**
   * Restore write permissions on Drupal configuration files and folders.
   */
  public function makeReadWrite() {
    $this->checkExistsSetPerm([
      $this->root . '/sites/default' => 0755,
      $this->root . '/sites/default/default.services.yml' => 0664,
      $this->settings => 0664,
    ]);
  }

  /**
   * Ensure that the shared directory exists and is writable.
   */
  public function ensureShared() {
    $this->filesystem->mkdir(
      $this->getProjectPath() . '/shared', 0755
    );
  }

  /**
   * Check file exists before trying to set permission.
   *
   * @param array $files
   *   Array of file paths and octal permissions to set on the files.
   */
  private function checkExistsSetPerm(array $files): void {
    foreach ($files as $file => $permission) {
      if ($this->filesystem->exists($file)) {
        $this->filesystem->chmod($file, $permission);
      }
      else {
        $this->io->writeError($file . ': file does not exist');
      }
    }
  }

  /**
   * Get the path to the vendor directory.
   *
   * E.g. /home/user/code/project/vendor
   *
   * @return string
   */
  public function getVendorPath() {
    // Load ComposerFilesystem to get access to path normalisation.
    $composerFilesystem = new ComposerFilesystem();

    $config = $this->composer->getConfig();
    $composerFilesystem->ensureDirectoryExists($config->get('vendor-dir'));
    return $composerFilesystem->normalizePath(realpath($config->get('vendor-dir')));
  }

  /**
   * Get the path to the project directory.
   *
   * E.g. /home/user/code/project
   *
   * @return string
   */
  public function getProjectPath() {
    return dirname($this->getVendorPath());
  }

}
