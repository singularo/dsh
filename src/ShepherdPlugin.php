<?php

declare(strict_types=1);

namespace Singularo\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling Shepherd Drupal scaffold.
 */
class ShepherdPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Composer object.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * IO object.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Run the pre installs before anything else.
      ScriptEvents::PRE_UPDATE_CMD => ['preInstall', 99],
      ScriptEvents::PRE_INSTALL_CMD => ['preInstall', 99],
      // Run the post installs after everything else.
      ScriptEvents::POST_CREATE_PROJECT_CMD => ['postInstall', -99],
      ScriptEvents::POST_INSTALL_CMD => ['postInstall', -99],
      ScriptEvents::POST_UPDATE_CMD => ['postInstall', -99]
    ];
  }

  /**
   * Post update command event to execute the scaffolding.
   *
   * @param \Composer\Script\Event $event
   */
  public function postInstall(Event $event) {
    $shepherd = new Shepherd($this->composer, $this->io, $event->getName());
    $event->getIO()->write('Creating settings.php file if not present.');
    $shepherd->populateSettingsFile();
    $event->getIO()->write('Removing write permissions on settings files.');
    $shepherd->makeReasonly();
  }

  public function preInstall(Event $event) {
    $shepherd = new Shepherd($this->composer, $this->io, $event->getName());
    $event->getIO()->write('Restoring write permissions on settings files.');
    $shepherd->makeReadWrite();
  }

}