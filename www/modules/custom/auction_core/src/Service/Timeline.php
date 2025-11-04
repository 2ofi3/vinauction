<?php

namespace Drupal\auction_core\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Time\TimeInterface;

/**
 * Tijdlijn/soft-close/cascade. Vereist dat loten start/end timestamps hebben.
 * NB: Dit is een skeleton; pas tabellen/velden aan je schema.
 */
final class Timeline {

  public function __construct(
    private readonly Connection $db,
    private readonly LockBackendInterface $lock,
    private readonly CacheTagsInvalidatorInterface $invalidator,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Soft-close: verleng actief lot en verschuif volgende loten.
   */
  public function softClose(int $auctionId, int $lotId, int $sequence, int $window = 10, int $increment = 10): bool {
    $key = "auction_lot:$lotId";
    if (!$this->lock->acquire($key, 10.0)) {
      return FALSE;
    }

    $tx = $this->db->startTransaction();
    try {
      // SELECT ... FOR UPDATE — pas aan op jouw storage (nodes/velden → hier reken ik op custom 'lot' tabel).
      $lot = $this->db->query("SELECT id, end_time FROM lot WHERE id = :id FOR UPDATE", [':id' => $lotId])->fetchObject();
      if (!$lot) return FALSE;

      $now = $this->time->getCurrentTime();
      $remaining = (int) $lot->end_time - $now;
      if ($remaining > $window) return FALSE;

      $newEnd = max((int) $lot->end_time, $now) + $increment;
      $shift  = $newEnd - (int) $lot->end_time;
      if ($shift <= 0) return FALSE;

      $this->db->update('lot')->fields(['end_time' => $newEnd])->condition('id', $lotId)->execute();

      // Cascade: verschuif alle volgende loten in deze veiling.
      $this->db->query("
        UPDATE lot
           SET start_time = start_time + :shift,
               end_time   = end_time   + :shift
         WHERE auction_id = :aid
           AND sequence > :seq
      ", [
        ':shift' => $shift,
        ':aid' => $auctionId,
        ':seq' => $sequence,
      ]);

      // Invalideren voor UI.
      $this->invalidator->invalidateTags(["lot:$lotId", "auction:$auctionId"]);
      return TRUE;
    }
    finally {
      $this->lock->release($key);
    }
  }
}
