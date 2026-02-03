<?php

/**
 * @file
 * Contains \Robo\RoboFileBase.
 *
 * Implementation of class for Robo - http://robo.li/
 */

declare(strict_types=1);

use Robo\Tasks;

/**
 * Implement specific functions to enable Drupal install builds.
 */
abstract class RoboFileBase extends Tasks {

  /**
   * Services.yml replacement patterns for debug mode.
   */
  protected const TWIG_DEBUG_FALSE = 'debug: false';
  protected const TWIG_DEBUG_TRUE = 'debug: true';
  protected const TWIG_AUTO_RELOAD_NULL = 'auto_reload: null';
  protected const TWIG_AUTO_RELOAD_TRUE = 'auto_reload: true';
  protected const TWIG_CACHE_TRUE = 'cache: true';
  protected const TWIG_CACHE_FALSE = 'cache: false';
  protected const HTTP_CACHE_DEBUG_TRUE = 'http.response.debug_cacheability_headers: true';
  protected const HTTP_CACHE_DEBUG_FALSE = 'http.response.debug_cacheability_headers: false';

  /**
   * The drupal profile to install.
   *
   * @var string
   */
  protected string $drupalProfile;

  /**
   * The application root.
   *
   * @var string
   */
  protected string $applicationRoot = '/code/web';

  /**
   * The path to the services file.
   *
   * @var string
   */
  protected string $servicesYml = '/code/web/sites/default/services.yml';

  /**
   * The config we're going to export.
   *
   * @var array
   */
  protected array $config = [];

  /**
   * Store if xdebug was enabled before run.
   *
   * @var bool
   */
  protected bool $reEnableXdebug = FALSE;

  /**
   * Initialize config variables and apply overrides.
   *
   * @throws \RuntimeException
   *   If required commands are not available.
   */
  public function __construct() {
    // Default to stopping on failures.
    $this->stopOnFail(TRUE);

    // Validate that required commands are available.
    $this->validateRequiredCommands();

    // Read config from environment variables.
    $environmentConfig = $this->readConfigFromEnv();
    $this->config = array_merge($this->config, $environmentConfig);

    // Get the drupal profile.
    $this->setDrupalProfile();
  }

  /**
   * Our own destructor to restore xdebug, maybe.
   */
  public function __destruct() {
    if ($this->reEnableXdebug) {
      $this->devXdebugEnable();
    }
  }

  /**
   * Validate that required external commands are available.
   *
   * @throws \RuntimeException
   *   If a required command is not found in PATH.
   */
  protected function validateRequiredCommands(): void {
    $requiredCommands = ['drush', 'composer'];

    foreach ($requiredCommands as $command) {
      // Use 'command -v' to check if command exists (POSIX compliant).
      $result = shell_exec("command -v $command 2>/dev/null");
      if (empty($result)) {
        throw new \RuntimeException("Required command '$command' not found in PATH. Please install $command.");
      }
    }
  }

  /**
   * Confirm the installation profile is set.
   *
   * Runs during the constructor; be careful not to use Robo methods.
   *
   * @throws \RuntimeException
   *   If SHEPHERD_INSTALL_PROFILE environment variable is not defined.
   */
  protected function setDrupalProfile(): void {
    $profile = getenv('SHEPHERD_INSTALL_PROFILE');
    if (empty($profile)) {
      throw new \RuntimeException('SHEPHERD_INSTALL_PROFILE environment variable not defined.');
    }

    $this->drupalProfile = $profile;
  }

  /**
   * Returns known configuration from environment variables.
   *
   * Runs during the constructor; be careful not to use Robo methods.
   *
   * @return array
   *   The sanitised config array.
   */
  protected function readConfigFromEnv(): array {
    $config = [
      'site' => [
        'title' => getenv('SITE_TITLE'),
        'mail' => getenv('SITE_MAIL'),
        'admin_email' => getenv('SITE_ADMIN_EMAIL'),
        'admin_user' => getenv('SITE_ADMIN_USERNAME'),
        'admin_password' => getenv('SITE_ADMIN_PASSWORD'),
      ],
      'environment' => [
        'hash_salt' => getenv('HASH_SALT'),
      ],
    ];

    // Clean up NULL values and empty arrays.
    $arrayClean = static function (&$item) use (&$arrayClean) {
      foreach ($item as $key => $value) {
        if (is_array($value)) {
          $arrayClean($value);
        }
        if (empty($value) && $value !== '0') {
          unset($item[$key]);
        }
      }
    };

    $arrayClean($config);

    return $config;
  }

  /**
   * Perform a full build on the project.
   */
  public function build(): void {
    $this->taskComposerValidate()->noCheckPublish();

    $this->buildInstall();

    // If the SITE_UUID is set, set the newly built site to have the same id.
    if ($uuid = $this->getSiteUuid()) {
      $this->drush('config:set')
        ->args('system.site', 'uuid', $uuid)
        ->option('yes')
        ->run();
      $this->devCacheRebuild();

      // Unless IMPORT_CONFIG=false is set, import the config-sync dir.
      if (getenv('IMPORT_CONFIG') !== 'false') {
        $this->drush('config:import')
          ->arg('--partial')
          ->option('yes')
          ->run();
      }
    }
  }

  /**
   * Clean config and files, then install Drupal and module dependencies.
   */
  public function buildInstall(): void {
    $this->drush('site:install')
      ->arg($this->drupalProfile)
      ->option('account-mail', $this->config['site']['admin_email'])
      ->option('account-name', $this->config['site']['admin_user'])
      ->option('account-pass', $this->config['site']['admin_password'])
      ->option('site-name', $this->config['site']['title'])
      ->option('site-mail', $this->config['site']['mail'])
      ->option('yes')
      ->run();
  }

  /**
   * Set the RewriteBase value in .htaccess appropriate for the site.
   */
  public function setSitePath(): void {
    if (!empty($this->config['site']['path'])) {
      $this->say("Setting site path.");
      $this->taskReplaceInFile("$this->applicationRoot/.htaccess")
        ->from('# RewriteBase /drupal')
        ->to("\n  RewriteBase /" . ltrim($this->config['site']['path'], '/') . "\n")
        ->run();
    }
  }

  /**
   * Clean the application root in preparation for a new build.
   *
   * @throws \RuntimeException
   */
  public function buildClean(): void {
    $filesystem = new \Symfony\Component\Filesystem\Filesystem();

    $pathsToRemove = [
      "$this->applicationRoot/core",
      "$this->applicationRoot/modules/contrib",
      "$this->applicationRoot/profiles/contrib",
      "$this->applicationRoot/themes/contrib",
      "$this->applicationRoot/sites/all",
      'bin',
      'vendor',
    ];

    try {
      $filesystem->remove($pathsToRemove);
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Build clean failed: ' . $e->getMessage());
    }
  }

  /**
   * Run all the drupal updates against a build.
   */
  public function buildUpdate(): void {
    // Run the module updates.
    $this->drush('updatedb')
      ->option('yes')
      ->run();
  }

  /**
   * Retrieve the site UUID.
   *
   * Once config is set, it's likely that the UUID will need to be consistent
   * across builds. This function is used as part of the build to get that
   * from an environment var, often defined in the docker-compose.*.yml file.
   *
   * @return string|false
   *   Return either a valid site uuid, or false if there is none.
   */
  protected function getSiteUuid(): string|false {
    return getenv('SITE_UUID');
  }

  /**
   * Turns on twig debug mode, auto reload on and caching off.
   */
  public function devTwigDebugEnable(): void {
    $this->devAggregateAssetsDisable(FALSE);
    $this->devUpdateServices(TRUE);
    $this->devCacheRebuild();
  }

  /**
   * Turn off twig debug mode, autoreload off and caching on.
   */
  public function devTwigDebugDisable(): void {
    $this->devUpdateServices(FALSE);
    $this->devCacheRebuild();
  }

  /**
   * Override the cache rebuild to also restart php.
   */
  public function devCacheRebuild(): void {
    $this->drush('cache:rebuild')->run();
    if (!getenv('GITLAB_CI')) {
      $this->reloadXdebugConfig();
    }
  }

  /**
   * Check if xdebug environment variables are configured.
   *
   * @return bool
   *   TRUE if xdebug is configured via environment variables.
   */
  private function isXdebugConfigured(): bool {
    return getenv('XDEBUG_CONFIG') || getenv('XDEBUG_MODE');
  }

  /**
   * Debug enable.
   *
   * Xdebug enable is typically long-lived, so there is no flag to re-disable.
   *
   * @param bool $reload
   *   Whether to reload the service.
   *
   * @throws \RuntimeException
   *   If xdebug cannot be enabled.
   *
   * @aliases debug
   */
  public function devXdebugEnable(bool $reload = TRUE): void {
    if (!$this->isXdebugConfigured()) {
      $this->say('Environment variables not setup for xdebug');
      return;
    }
    if (extension_loaded('xdebug')) {
      $this->say('Xdebug already enabled');
      return;
    }
    $this->say('Enabling xdebug.');
    if (!$this->taskExec('sudo phpenmod -v ALL -s ALL xdebug')
      ->printOutput(FALSE)
      ->run()
      ->wasSuccessful()) {
      throw new \RuntimeException('Unable to enable xdebug.');
    }
    if ($reload) {
      $this->reloadXdebugConfig();
    }
    $this->say('Enabled xdebug.');
  }

  /**
   * Debug disable.
   *
   * Store the state so we can decide to re-enable it in some cases.
   * Xdebug is commonly disabled for speed, but then should be re-enabled.
   * This function allows for that to occur.
   *
   * @param bool $reload
   *   Whether to reload the service.
   *
   * @throws \RuntimeException
   *   If xdebug cannot be disabled.
   *
   * @aliases nodebug
   */
  public function devXdebugDisable(bool $reload = TRUE): void {
    if (!$this->isXdebugConfigured()) {
      $this->say('Environment variables not setup for xdebug');
      return;
    }
    if (!extension_loaded('xdebug')) {
      $this->say('Xdebug already disabled');
      return;
    }

    // If its not being explicitly disabled, set re-enable on destruct.
    $command = $this->input()->getArguments()['command'];
    if (!in_array($command, ['dev:xdebug-disable', 'nodebug'])) {
      $this->reEnableXdebug = TRUE;
    }

    // Actually disable xdebug.
    $this->say('Disabling xdebug.');
    if (!$this->taskExec('sudo phpdismod -v ALL -s ALL xdebug')
      ->printOutput(FALSE)
      ->run()
      ->wasSuccessful()) {
      throw new \RuntimeException('Unable to disable xdebug.');
    }

    if ($reload) {
      $this->reloadXdebugConfig();
    }
    $this->say('Disabled xdebug.');
  }

  /**
   * Helper function to check which container type and restart processes.
   *
   * Reloads PHP-FPM or Apache to apply xdebug configuration changes.
   * Uses s6-overlay service control if available, otherwise signals Apache.
   */
  protected function reloadXdebugConfig() {
    // Check if using s6, otherwise its apache2.
    if (file_exists('/etc/s6-overlay/s6-rc.d/php-fpm/run')) {
      $this->_exec('sudo /command/s6-svc -r /service/php-fpm');
    }
    else {
      $this->yell('You will need to start ./dsh again');
      $this->_exec('sudo kill -HUP 1');
    }
  }

  /**
   * Replace strings in the services yml.
   *
   * Updates Twig debug settings, auto-reload, cache, and response headers
   * in the services.yml file for development vs production configurations.
   *
   * @param bool $enable
   *   Whether to disable or enable debug parameters.
   *
   * @throws \RuntimeException
   *   If the services.yml file cannot be updated.
   */
  private function devUpdateServices(bool $enable = TRUE): void {
    $replacements = [
      [self::TWIG_DEBUG_FALSE, self::TWIG_DEBUG_TRUE],
      [self::TWIG_AUTO_RELOAD_NULL, self::TWIG_AUTO_RELOAD_TRUE],
      [self::TWIG_CACHE_TRUE, self::TWIG_CACHE_FALSE],
      [self::HTTP_CACHE_DEBUG_TRUE, self::HTTP_CACHE_DEBUG_FALSE],
    ];

    foreach ($replacements as [$production, $debug]) {
      [$from, $to] = $enable ? [$production, $debug] : [$debug, $production];

      if (!$this->taskReplaceInFile($this->servicesYml)
        ->from($from)
        ->to($to)
        ->run()
        ->wasSuccessful()) {
        throw new \RuntimeException('Unable to update services.yml');
      }
    }
  }

  /**
   * Disable asset aggregation.
   *
   * @param bool $cacheClear
   *   Whether to clear the cache after changes.
   */
  public function devAggregateAssetsDisable(bool $cacheClear = TRUE): void {
    $this->preprocessSet(FALSE, $cacheClear);
  }

  /**
   * Enable asset aggregation.
   *
   * @param bool $cacheClear
   *   Whether to clear the cache after changes.
   */
  public function devAggregateAssetsEnable(bool $cacheClear = TRUE): void {
    $this->preprocessSet(TRUE, $cacheClear);
  }

  /**
   * Helper to actually update files.
   *
   * Sets JavaScript and CSS preprocessing (aggregation) on or off.
   *
   * @param bool $enable
   *   TRUE to enable preprocessing, FALSE to disable.
   * @param bool $cacheClear
   *   Whether to clear the cache after changes.
   *
   * @throws \RuntimeException
   *   If config set operations fail.
   */
  private function preprocessSet(bool $enable = TRUE, bool $cacheClear = TRUE): void {
    $status = $enable ? 1 : 0;

    $result = $this->drush('config:set')
      ->rawArg("system.performance js.preprocess $status")
      ->option('yes')
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \RuntimeException('Config set failed.');
    }

    $result = $this->drush('config:set')
      ->rawArg("system.performance css.preprocess $status")
      ->option('yes')
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \RuntimeException('Config set failed.');
    }

    if ($cacheClear) {
      $this->devCacheRebuild();
    }
  }

  /**
   * Imports a database, updates the admin user password and applies updates.
   *
   * @param string $sqlFile
   *   Path to sql file to import.
   */
  public function devImportDb(string $sqlFile): void {
    $this->drush('sql:drop')
      ->option('yes')
      ->run();

    $this->drush('sql:query')
      ->option('file', $sqlFile)
      ->run();

    $this->devCacheRebuild();
    $this->devResetAdminPass();
  }

  /**
   * Find the username of user 1 which is the 'admin' user for Drupal.
   *
   * @param string $password
   *   Password for admin user. Falls back to SITE_ADMIN_PASSWORD config.
   *
   * @throws \RuntimeException
   *   If user 1 cannot be found or password reset fails.
   */
  public function devResetAdminPass(string $password = ''): void {
    // Use config value if no password provided.
    if (empty($password)) {
      $password = $this->config['site']['admin_password'] ?? 'password';
    }

    // Retrieve the name of the admin user, it might not be 'admin'.
    $result = $this->drush('user:information')
      ->arg('1')
      ->option('format', 'json')
      ->printOutput(FALSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \RuntimeException('Unable to retrieve user 1 information.');
    }

    $userData = json_decode($result->getMessage(), true);
    $adminUser = $userData['name'] ?? null;

    if (empty($adminUser)) {
      throw new \RuntimeException('No user with uid 1, this is probably bad.');
    }

    // Perform the password reset.
    $result = $this->drush('user:password')
      ->args($adminUser, $password)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \RuntimeException('Failed resetting password.');
    }
  }

  /**
   * Exports a database and gzips the sql file.
   *
   * @param string $name
   *   Name of sql file to be exported.
   */
  public function devExportDb(string $name = 'dump'): void {
    $this->drush('sql:dump')
      ->option('gzip')
      ->option('result-file', "$name.sql")
      ->run();
  }

  /**
   * Run coding standards checks for PHP files on the project.
   *
   * @param string $path
   *   An optional path to lint.
   */
  public function devLintPhp(string $path = ''): void {
    $this->_exec(trim('phpcs ' . $path));
    $this->_exec('phpstan analyze --no-progress');
  }

  /**
   * Fix coding standards violations for PHP files on the project.
   *
   * @param string $path
   *   An optional path to fix.
   */
  public function devLintFix(string $path = ''): void {
    $this->_exec(trim('phpcbf ' . $path));
  }

  /**
   * Provide drush wrapper.
   *
   * @param string $command
   *   The command to run.
   *
   * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Base\Exec
   *   The task to exec.
   */
  protected function drush(string $command) {
    $task = $this->taskExec('vendor/bin/drush');
    $task->rawArg($command);

    return $task;
  }

}
