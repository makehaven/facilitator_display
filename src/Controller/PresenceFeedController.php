<?php

namespace Drupal\facilitator_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\profile\Entity\Profile;
use Symfony\Component\HttpFoundation\JsonResponse;

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
    $now = \Drupal::time()->getRequestTime();
    $start_window = $now - (2 * 3600); // 2 hours ago
    $end_window = $now + (7 * 24 * 3600); // 1 week from now

    $profile_ids = \Drupal::entityQuery('profile')
      ->condition('type', 'coordinator')
      ->condition('status', 1)
      ->condition('uid.entity.roles', 'facilitator')
      ->condition('field_coordinator_hours.value', $start_window, '>=')
      ->condition('field_coordinator_hours.value', $end_window, '<')
      ->sort('field_coordinator_hours.value', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    if (empty($profile_ids)) {
      return new JsonResponse(['items' => [], 'now' => $now]);
    }

    $profiles = Profile::loadMultiple($profile_ids);
    $items = [];
    $date_formatter = \Drupal::service('date.formatter');
    $config = $this->config('facilitator_display.settings');
    $presence_timeout = $config->get('presence_timeout') ?: 14400;

    foreach ($profiles as $profile) {
      $user_entity = $profile->getOwner();
      if (!$user_entity) {
        continue;
      }

      // Get the facilitator's presence data.
      $presence = \Drupal::database()->select('facilitator_presence', 'fp')
        ->fields('fp', ['last_seen', 'door'])
        ->condition('uid', $user_entity->id())
        ->execute()
        ->fetchAssoc();

      $is_present = ($presence && ($now - $presence['last_seen'] < $presence_timeout));

      // Format the schedule string.
      $schedule_str = '';
      if (!$profile->get('field_coordinator_hours')->isEmpty()) {
        $start_ts = $profile->get('field_coordinator_hours')->value;
        $end_ts = $profile->get('field_coordinator_hours')->end_value;
        $start_date = $date_formatter->format($start_ts, 'custom', 'D, M j, g:i A');
        $end_date = $date_formatter->format($end_ts, 'custom', 'g:i A');
        $schedule_str = $start_date . ' - ' . $end_date;
      }

      $items[] = [
        'uid' => $user_entity->id(),
        'name' => $user_entity->getDisplayName(),
        'photo' => !$profile->get('field_member_photo')->isEmpty() ? $profile->get('field_member_photo')->entity->createFileUrl(FALSE) : NULL,
        'schedule' => $schedule_str,
        'focus' => $profile->get('field_coordinator_focus')->value,
        'present' => $is_present,
        'door' => $is_present ? $presence['door'] : NULL,
        'last_seen' => $is_present ? $presence['last_seen'] : NULL,
      ];
    }

    return new JsonResponse(['items' => $items, 'now' => $now]);
  }
}