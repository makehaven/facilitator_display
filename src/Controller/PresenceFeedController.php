<?php

namespace Drupal\facilitator_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\views\Views;

/**
 * Controller for the facilitator presence JSON feed.
 */
class PresenceFeedController extends ControllerBase {

  /**
   * Returns the JSON feed of facilitators.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * The JSON response.
   */
  public function feed(): JsonResponse {
    $view = Views::getView('facilitator_schedules');
    $view->setDisplay('default'); // Use the default display of your View
    $view->execute();

    $items = [];
    if (!empty($view->result)) {
      foreach ($view->result as $row) {
        $user_entity = $row->_relationship_entities['uid'];
        $profile_entity = $row->_entity;

        // Get the facilitator's presence data.
        $presence = \Drupal::database()->select('facilitator_presence', 'fp')
          ->fields('fp', ['last_seen', 'door'])
          ->condition('uid', $user_entity->id())
          ->execute()
          ->fetchAssoc();

        // Check if the facilitator was seen within the last 4 hours.
        $is_present = ($presence && (time() - $presence['last_seen'] < 14400));

        $items[] = [
          'uid' => $user_entity->id(),
          'name' => $user_entity->getDisplayName(),
          'photo' => $profile_entity->get('field_member_photo')->entity ? $profile_entity->get('field_member_photo')->entity->createFileUrl(FALSE) : NULL,
          'schedule' => $profile_entity->get('field_coordinator_hours')->value,
          'focus' => $profile_entity->get('field_coordinator_focus')->value,
          'present' => $is_present,
          'door' => $is_present ? $presence['door'] : NULL,
          'last_seen' => $is_present ? $presence['last_seen'] : NULL,
        ];
      }
    }

    return new JsonResponse(['items' => $items, 'now' => time()]);
  }
}