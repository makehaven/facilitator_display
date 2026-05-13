<?php

namespace Drupal\facilitator_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JSON feed of fallback content shown when the facilitator grid is empty
 * or sparsely populated: top badge earners, recently earned badges, and
 * upcoming public CiviCRM events.
 */
class FallbackFeedController extends ControllerBase {

  const CACHE_ID = 'facilitator_display:fallback_feed';
  const CACHE_TTL = 300;

  /**
   * Returns the JSON feed.
   */
  public function feed(): JsonResponse {
    $cache = \Drupal::cache();
    if ($cached = $cache->get(self::CACHE_ID)) {
      $data = $cached->data;
    }
    else {
      $data = [
        'top_badges' => $this->topBadgeEarners(),
        'recent_badges' => $this->recentBadges(),
        'upcoming_events' => $this->upcomingEvents(),
      ];
      $expire = \Drupal::time()->getRequestTime() + self::CACHE_TTL;
      $cache->set(self::CACHE_ID, $data, $expire);
    }

    $response = new JsonResponse($data);
    $response->headers->set('Cache-Control', 'public, max-age=' . self::CACHE_TTL);
    return $response;
  }

  /**
   * Top members by active badge count.
   */
  private function topBadgeEarners(): array {
    $db = \Drupal::database();
    $sql = "SELECT m.field_member_to_badge_target_id AS uid, COUNT(DISTINCT n.nid) AS cnt
      FROM {node_field_data} n
      INNER JOIN {node__field_member_to_badge} m ON m.entity_id = n.nid
      INNER JOIN {node__field_badge_status} s ON s.entity_id = n.nid AND s.field_badge_status_value = 'active'
      INNER JOIN {users_field_data} u ON u.uid = m.field_member_to_badge_target_id AND u.status = 1
      INNER JOIN {user__roles} r ON r.entity_id = u.uid AND r.roles_target_id = 'member'
      WHERE n.type = 'badge_request' AND n.status = 1
      GROUP BY m.field_member_to_badge_target_id
      ORDER BY cnt DESC, m.field_member_to_badge_target_id ASC
      LIMIT 5";

    $items = [];
    foreach ($db->query($sql)->fetchAll() as $row) {
      $user = User::load($row->uid);
      if (!$user) {
        continue;
      }
      $items[] = [
        'name' => $user->getDisplayName(),
        'count' => (int) $row->cnt,
      ];
    }
    return $items;
  }

  /**
   * Five most recently approved badges.
   */
  private function recentBadges(): array {
    $db = \Drupal::database();
    $sql = "SELECT n.nid, n.title, n.changed,
        m.field_member_to_badge_target_id AS uid,
        t.name AS badge_name
      FROM {node_field_data} n
      INNER JOIN {node__field_badge_status} s ON s.entity_id = n.nid AND s.field_badge_status_value = 'active'
      INNER JOIN {node__field_member_to_badge} m ON m.entity_id = n.nid
      LEFT JOIN {node__field_badge_requested} br ON br.entity_id = n.nid
      LEFT JOIN {taxonomy_term_field_data} t ON t.tid = br.field_badge_requested_target_id
      WHERE n.type = 'badge_request' AND n.status = 1
      ORDER BY n.changed DESC
      LIMIT 5";

    $items = [];
    foreach ($db->query($sql)->fetchAll() as $row) {
      $user = User::load($row->uid);
      if (!$user) {
        continue;
      }
      // Prefer the referenced taxonomy term name; fall back to a parsed title;
      // last resort, raw title.
      $label = $row->badge_name;
      if (!$label && $row->title && preg_match('/^Badge Request for (.+?) by User /', $row->title, $m)) {
        $label = $m[1];
      }
      if (!$label) {
        $label = $row->title;
      }
      $items[] = [
        'badge' => $label,
        'member' => $user->getDisplayName(),
        'when' => (int) $row->changed,
      ];
    }
    return $items;
  }

  /**
   * Next public, active CiviCRM events.
   */
  private function upcomingEvents(): array {
    $db = \Drupal::database();
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT id, title, start_date FROM {civicrm_event}
      WHERE is_active = 1 AND is_public = 1 AND start_date >= :now
      ORDER BY start_date ASC LIMIT 5";

    $items = [];
    foreach ($db->query($sql, [':now' => $now])->fetchAll() as $row) {
      $items[] = [
        'title' => $row->title,
        'start' => strtotime($row->start_date),
      ];
    }
    return $items;
  }

}
