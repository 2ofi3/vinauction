<?php

namespace Drupal\auction_core\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Time\TimeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Soft-close zonder lot-einddatums:
 * EffectiveEnd = BaseEnd(veiling, seq) + LotExt(lot) + TailShift(veiling, seq).
 */
final class SoftCloseManager {

  private const WINDOW  = 10; // laatste 10s-regel
  private const EXTEND  = 10; // +10s bij laat bod

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly Connection $db,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $invalidator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  // ---------------- Public API ----------------

  /**
   * Aanroepen NA succesvol bod: als we binnen WINDOW zitten:
   * - LotExt(lot) += EXTEND
   * - TailShift(auction, seq+1..) += EXTEND
   */
  public function onBidPlaced(int $lot_id): void {
    $lot = $this->loadLot($lot_id);
    if (!$lot) return;

    $auction = $this->getAuction($lot);
    if (!$auction) return;

    $auction_id = (int) $auction->id();
    $lock_key = "auction_core:soft_close:$auction_id";
    if (!$this->lock->acquire($lock_key, 5.0)) return;

    try {
      $now = $this->time->getRequestTime();
      $effective_end = $this->getEffectiveEndTimestamp($lot);
      if (!$effective_end) return;

      $remaining = $effective_end - $now;
      if ($remaining > 0 && $remaining <= self::WINDOW) {
        $delta = self::EXTEND;
        $this->addLotExtension($lot->id(), $delta);
        $seq = $this->getLotSequence($lot);
        $this->addShiftEdge($auction_id, $seq + 1, $delta);
        $this->invalidator->invalidateTags($lot->getCacheTags());
      }
    }
    finally {
      $this->lock->release($lock_key);
    }
  }

  /**
   * Effectieve eindtijd (UNIX ts) voor een lot.
   */
  public function getEffectiveEndTimestamp(NodeInterface $lot): ?int {
    $auction = $this->getAuction($lot);
    if (!$auction) return null;

    $seq  = $this->getLotSequence($lot);
    $base = $this->getBaseEndForLot($auction, $seq);
    if (!$base) return null;

    $ext  = $this->getLotExtension($lot->id());
    $tail = $this->getCumulativeShift((int) $auction->id(), $seq);

    return $base + $ext + $tail;
  }

  /**
   * Helper: via lot id.
   */
  public function getEffectiveEndTimestampById(int $lot_id): ?int {
    $lot = $this->loadLot($lot_id);
    return $lot ? $this->getEffectiveEndTimestamp($lot) : null;
  }

  // ---------------- Base time model ----------------

  /**
   * BaseEnd uit veiling:
   * 1) Als field_einddatum: eind + (max(0, seq-1) * spacing)
   * 2) Else als field_auction_date: start + (seq * spacing)
   */
  private function getBaseEndForLot(NodeInterface $auction, int $seq): ?int {
    $spacing = $this->getSpacingSeconds($auction);

    if ($auction->hasField('field_einddatum') && !$auction->get('field_einddatum')->isEmpty()) {
      $raw = $auction->get('field_einddatum')->value;
      $dt = new DrupalDateTime($raw, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      return $dt->getTimestamp() + (max(0, $seq - 1) * $spacing);
    }

    if ($auction->hasField('field_auction_date') && !$auction->get('field_auction_date')->isEmpty()) {
      $raw = $auction->get('field_auction_date')->value;
      $dt = new DrupalDateTime($raw, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      return $dt->getTimestamp() + ($seq * $spacing);
    }

    return null;
  }

  /**
   * Spacing eerst van veilingveld 'field_lot_spacing_seconds', anders config.
   */
  private function getSpacingSeconds(NodeInterface $auction): int {
    if ($auction->hasField('field_lot_spacing_seconds') && !$auction->get('field_lot_spacing_seconds')->isEmpty()) {
      $val = (int) $auction->get('field_lot_spacing_seconds')->value;
      if ($val > 0) return $val;
    }
    $cfg = $this->configFactory->get('auction_core.settings');
    $val = (int) $cfg->get('spacing_seconds');
    return $val > 0 ? $val : 20;
  }

  // ---------------- Data access: extensies & shifts ----------------

  private function addLotExtension(int $lot_id, int $delta): void {
    $now = $this->time->getRequestTime();
    $exists = $this->db->select('auction_core_lot_ext', 'e')
      ->fields('e', ['lot_id'])
      ->condition('lot_id', $lot_id)
      ->execute()
      ->fetchField();

    if ($exists) {
      $this->db->update('auction_core_lot_ext')
        ->expression('ext_seconds', 'ext_seconds + :d', [':d' => $delta])
        ->fields(['updated' => $now])
        ->condition('lot_id', $lot_id)
        ->execute();
    }
    else {
      $this->db->insert('auction_core_lot_ext')
        ->fields([
          'lot_id' => $lot_id,
          'ext_seconds' => $delta,
          'updated' => $now,
        ])->execute();
    }
  }

  private function getLotExtension(int $lot_id): int {
    $val = $this->db->select('auction_core_lot_ext', 'e')
      ->fields('e', ['ext_seconds'])
      ->condition('lot_id', $lot_id)
      ->execute()
      ->fetchField();
    return (int) $val;
  }

  private function addShiftEdge(int $auction_id, int $seq_from, int $delta): void {
    $this->db->insert('auction_core_shift_edges')
      ->fields([
        'auction_id' => $auction_id,
        'seq_from' => $seq_from,
        'delta_seconds' => $delta,
        'created' => $this->time->getRequestTime(),
      ])->execute();
  }

  private function getCumulativeShift(int $auction_id, int $seq): int {
    $sum = $this->db->select('auction_core_shift_edges', 's')
      ->addExpression('COALESCE(SUM(delta_seconds), 0)', 'sumd')
      ->condition('auction_id', $auction_id)
      ->condition('seq_from', $seq, '<=')
      ->execute()
      ->fetchField();
    return (int) $sum;
  }

  // ---------------- Entity helpers ----------------

  private function loadLot(int $nid): ?NodeInterface {
    $n = $this->etm->getStorage('node')->load($nid);
    return ($n instanceof NodeInterface && $n->bundle() === 'lot') ? $n : null;
  }

  private function getAuction(NodeInterface $lot): ?NodeInterface {
    if ($lot->hasField('field_auction_reference') && !$lot->get('field_auction_reference')->isEmpty()) {
      $aid = (int) $lot->get('field_auction_reference')->target_id;
      $a = $this->etm->getStorage('node')->load($aid);
      return ($a instanceof NodeInterface) ? $a : null;
    }
    return null;
  }

  private function getLotSequence(NodeInterface $lot): int {
    return (int) ($lot->hasField('field_lot_nr') ? $lot->get('field_lot_nr')->value : 0);
  }
}
