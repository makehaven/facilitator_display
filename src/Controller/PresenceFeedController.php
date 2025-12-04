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
    
    // Get the site's timezone.
    $config = \Drupal::config('system.date');
    $timezone_name = $config->get('timezone.default') ?: date_default_timezone_get();
    $timezone = new \DateTimeZone($timezone_name);

    // Calculate start and end of "today" in the site's timezone.
    $date = new \DateTime('now', $timezone);
    $date->setTimestamp($now);
    $date->setTime(0, 0, 0);
    $start_of_day = $date->getTimestamp();
    
    $date->setTime(23, 59, 59);
    $end_of_day = $date->getTimestamp();

    $profile_ids = \Drupal::entityQuery('profile')
      ->condition('type', 'coordinator')
      ->condition('status', 1)
      ->condition('uid.entity.roles', 'facilitator')
      ->condition('field_coordinator_hours.value', $end_of_day, '<=') // Schedule starts before the end of today
      ->condition('field_coordinator_hours.end_value', $start_of_day, '>=') // Schedule ends after the start of today
      ->sort('field_coordinator_hours.value', 'ASC')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($profile_ids)) {
      return new JsonResponse(['items' => [], 'now' => $now]);
    }

    $profiles = Profile::loadMultiple($profile_ids);
    $uids = [];
    foreach ($profiles as $profile) {
      if ($uid = $profile->getOwnerId()) {
        $uids[$uid] = $uid;
      }
    }

    // Batch query presence from access_display_presence.
    $presence_data = [];
    if (!empty($uids)) {
      $results = \Drupal::database()->select('access_display_presence', 'adp')
        ->fields('adp', ['uid', 'last_seen', 'door'])
        ->condition('uid', $uids, 'IN')
        ->execute()
        ->fetchAllAssoc('uid');
      foreach ($results as $row) {
        $presence_data[$row->uid] = (array) $row;
      }
    }

    $items = [];
    $date_formatter = \Drupal::service('date.formatter');
    $config = $this->config('facilitator_display.settings');
    $presence_timeout = $config->get('presence_timeout') ?: 14400;

    foreach ($profiles as $profile) {
      $user_entity = $profile->getOwner();
      if (!$user_entity) {
        continue;
      }
      $uid = $user_entity->id();

      // Format the schedule string.
      $schedule_str = '';
      $sort_ts = 9999999999; // Default to end of list if no schedule found
      $shift_end_ts = 0;
      $shift_start_ts = 0;
      
      if (!$profile->get('field_coordinator_hours')->isEmpty()) {
        // Find the specific schedule item that matches "today".
        foreach ($profile->get('field_coordinator_hours') as $item) {
          $start_ts = (int) $item->value;
          $end_ts = (int) $item->end_value;

          if ($start_ts <= $end_of_day && $end_ts >= $start_of_day) {
            $start_date = $date_formatter->format($start_ts, 'custom', 'g:i A'); // Only time
            $end_date = $date_formatter->format($end_ts, 'custom', 'g:i A');
            $schedule_str = $start_date . ' - ' . $end_date;
            $sort_ts = $start_ts;
            $shift_start_ts = $start_ts;
            $shift_end_ts = $end_ts;
            break; // Stop after finding the first matching slot (or we could collect multiple)
          }
        }
      }

      $presence = $presence_data[$uid] ?? NULL;
      
      // Calculate presence.
      // 1. Standard Timeout: Scanned recently (default 4h).
      $is_present_standard = ($presence && ($now - $presence['last_seen'] < $presence_timeout));
      
      // 2. Shift Persistence: If they scanned in close to (2h buffer) or during their shift,
      //    assume they are here until the shift ends.
      $is_present_shift = false;
      if ($presence && $shift_start_ts && $shift_end_ts) {
        // "Within a couple hours of their shift" -> Start - 2h
        $shift_buffer_start = $shift_start_ts - 7200;
        
        if ($presence['last_seen'] >= $shift_buffer_start && $now <= $shift_end_ts) {
           $is_present_shift = true;
        }
      }
      
      $is_present = $is_present_standard || $is_present_shift;

      // Filter out facilitators whose shift has ended more than 30 minutes ago,
      // unless they are currently marked as present.
      if (!$is_present && $shift_end_ts > 0 && $now > ($shift_end_ts + 1800)) {
        continue;
      }

      $items[] = [
        'uid' => $uid,
        'name' => $user_entity->getDisplayName(),
        'photo' => $this->getPhotoUrl($uid),
        'schedule' => $schedule_str,
        'schedule_start' => $sort_ts,
        'focus' => $profile->get('field_coordinator_focus')->value,
        'present' => $is_present,
        'door' => $is_present ? $presence['door'] : NULL,
        'last_seen' => $presence ? $presence['last_seen'] : NULL, // Always send last_seen if available
      ];
    }

    // Sort items by schedule start time.
    usort($items, function($a, $b) {
      return $a['schedule_start'] <=> $b['schedule_start'];
    });

    // Remove the helper key if desired, or keep it.
    // We'll keep it as it might be useful for debug, but client doesn't use it.

    $response = new JsonResponse(['items' => $items, 'now' => $now]);
    $response->headers->set('Cache-Control', 'no-store, max-age=0');
    return $response;
  }

  /**
   * Helper to get the photo URL from the user's main profile.
   */
  private function getPhotoUrl($uid) {
    $storage = \Drupal::entityTypeManager()->getStorage('profile');
    $profiles = $storage->loadByProperties(['uid' => $uid, 'type' => 'main']);
    if ($profiles) {
      $main_profile = reset($profiles);
      if ($main_profile->hasField('field_member_photo') && !$main_profile->get('field_member_photo')->isEmpty()) {
        $file = $main_profile->get('field_member_photo')->entity;
        if ($file) {
          return $file->createFileUrl(FALSE);
        }
      }
    }
    return NULL;
  }
}
