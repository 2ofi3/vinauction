<?php

namespace Drupal\auction_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * JSON endpoints voor live countdowns.
 */
class AuctionStatusController extends ControllerBase {

  /**
   * Eén lot: effectieve eindtijd (UNIX ts).
   */
  public function lotEnd(int $lot, Request $request): JsonResponse {
    $svc = \Drupal::service('auction_core.soft_close');
    $end_ts = $svc->getEffectiveEndTimestampById($lot);
    if (!$end_ts) {
      return new JsonResponse(['ok' => false], 404);
    }
    $now = \Drupal::time()->getRequestTime();
    $res = new JsonResponse([
      'ok' => true,
      'lot' => $lot,
      'end_ts' => (int) $end_ts,
      'server_now' => (int) $now,
    ]);
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
    return $res;
  }

  /**
   * Batch: meerdere loten in één call.
   * /auction-core/auction/{auction}/ends?lots=1,2,3
   */
  public function auctionEnds(int $auction, Request $request): JsonResponse {
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query->get('lots', '')))));
    if (!$ids) {
      return new JsonResponse(['ok' => false, 'error' => 'no lots'], 400);
    }
    $svc = \Drupal::service('auction_core.soft_close');
    $now = \Drupal::time()->getRequestTime();
    $ends = [];
    foreach ($ids as $lot_id) {
      $end = $svc->getEffectiveEndTimestampById($lot_id);
      if ($end) {
        $ends[$lot_id] = (int) $end;
      }
    }
    $res = new JsonResponse([
      'ok' => true,
      'server_now' => (int) $now,
      'ends' => $ends,
    ]);
    $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
    return $res;
  }

}
