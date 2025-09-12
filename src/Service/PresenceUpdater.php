<?php

namespace Drupal\facilitator_display\Service;

use Drupal\Core\Database\Connection;
use Drupal\user\UserInterface;

/**
 * Service to update facilitator presence.
 */
class PresenceUpdater {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new PresenceUpdater object.
   *
   * @param \Drupal\Core\Database\Connection $database
   * The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Upserts a facilitator's presence record.
   *
   * @param \Drupal\user\UserInterface $account
   * The user account of the facilitator.
   * @param string $door
   * The door where the facilitator was seen.
   * @param int $ts
   * The timestamp of the presence.
   */
  public function upsert(UserInterface $account, string $door, int $ts): void {
    $this->database->merge('facilitator_presence')
      ->key(['uid' => $account->id()])
      ->fields([
        'last_seen' => $ts,
        'door' => $door,
      ])
      ->execute();
  }
}