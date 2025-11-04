<?php

namespace Drupal\auction_core\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Haalt hoogste bod / startbod / staplogica op.
 * NB: huidige implementatie leest Webform submissions zoals je deed; ideaal is normaliseren naar eigen entity.
 */
final class Offers {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly Connection $db,
  ) {}

  public function getStartingBidForLot(int $lotNid): float {
    $node = $this->etm->getStorage('node')->load($lotNid);
    if (!$node || $node->getType() !== 'lot') {
      return 0.0;
    }
    return (float) ($node->get('field_minimum_offer')->value ?? 0);
  }

  public function getHighestForLot(int $lotNid): float {
    // Vind 1 offer node (zoals je deed).
    $nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'offer')->range(0, 1)->execute();
    if (!$nids) {
      return 0.0;
    }
    $offerNid = (int) reset($nids);

    // O(N) over submissions: gelijk aan jouw code, maar hier opgesloten.
    $storage = $this->etm->getStorage('webform_submission');
    $subs = $storage->loadByProperties(['entity_type' => 'node', 'entity_id' => $offerNid]);

    $highest = 0.0;
    foreach ($subs as $sub) {
      $data = $sub->getData();
      if (!empty($data['lot_select']) && (int) $data['lot_select'] === $lotNid && isset($data['bod'])) {
        $bid = (float) $data['bod'];
        if ($bid > $highest) {
          $highest = $bid;
        }
      }
    }
    return $highest;
  }

  public function getStepForStart(float $starting): int {
    if ($starting >= 4000) return 200;
    if ($starting >= 2000) return 100;
    if ($starting >= 800)  return 50;
    if ($starting >= 300)  return 20;
    if ($starting >= 80)   return 10;
    return 5;
  }

  public function getFinalYieldForLot(int $lotNid): ?float {
    // Later: definitieve opbrengst (na sluiten). Voor nu: hoogste bod.
    $highest = $this->getHighestForLot($lotNid);
    return $highest > 0 ? $highest : NULL;
  }
}
