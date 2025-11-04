<?php

namespace Drupal\auction_core\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Time\TimeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Soft close per lot:
 * - Als er in de laatste WINDOW seconden van het ACTIEVE lot een bod komt:
 *   * verleng HET ACTIEVE lot met EXTEND seconden;
 *   * schuif ALLE VOLGENDE loten (zelfde veiling, hogere/gelijke volgorde?) mee met dezelfde delta.
 * - Zodra een lot zonder nieuw bod afloopt, start het volgende lot gewoon door (zonder extra gap),
 *   en geldt dezelfde soft-close regel daar opnieuw.
 */
final class SoftCloseManager {

  // Instelbare parameters (seconden).
  private const WINDOW = 10; // laatste 10s-regel
  private const EXTEND = 10; // bij bod in venster → +10s

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $invalidator,
  ) {}

  /**
   * Aanroepen NA het succesvol registreren van een bod op $lot_id.
   *
   * @param int $lot_id
   *   Node ID van het lot waarop zojuist is geboden.
   */
  public function onBidPlaced(int $lot_id): void {
    $lot = $this->loadLot($lot_id);
    if (!$lot) {
      return;
    }
    $auction_id = $this->getAuctionId($lot);
    if (!$auction_id) {
      return;
    }

    $lock_key = "auction_core:soft_close:auction:$auction_id";
    if (!$this->lock->acquire($lock_key, 5.0)) {
      // Iemand anders verwerkt een verlenging; voorkom dubbele shifts.
      return;
    }

    try {
      $now = $this->time->getRequestTime();
      $end_ts = $this->fieldToTimestamp($lot, 'field_einddatum');
      if (!$end_ts) {
        return;
      }
      $remaining = $end_ts - $now;

      // Alleen verlengen als we in de laatste WINDOW seconden zitten (en nog niet over eindtijd heen).
      if ($remaining > 0 && $remaining <= self::WINDOW) {
        $delta = self::EXTEND;

        // 1) Verleng het ACTIEVE lot.
        $this->shiftLotEnd($lot, $delta);

        // 2) Schuif ALLE VOLGENDE loten mee in dezelfde veiling (hogere volgorde).
        $current_seq = $this->getLotSequence($lot);
        foreach ($this->loadFollowingLots($auction_id, $current_seq) as $next) {
          $this->shiftLotEnd($next, $delta);
        }

        // 3) Cache-invalidation.
        $this->invalidateNodes(
          array_merge([$lot], $this->loadFollowingLots($auction_id, $current_seq))
        );
      }
    }
    finally {
      $this->lock->release($lock_key);
    }
  }

  // ----------------- Helpers -----------------

  private function loadLot(int $nid): ?NodeInterface {
    $node = $this->etm->getStorage('node')->load($nid);
    return ($node instanceof NodeInterface && $node->bundle() === 'lot') ? $node : null;
  }

  private function getAuctionId(NodeInterface $lot): ?int {
    if ($lot->hasField('field_auction_reference') && !$lot->get('field_auction_reference')->isEmpty()) {
      return (int) $lot->get('field_auction_reference')->target_id;
    }
    return null;
  }

  private function getLotSequence(NodeInterface $lot): int {
    return (int) ($lot->hasField('field_lot_nr') ? $lot->get('field_lot_nr')->value : 0);
  }

  /**
   * Laad alle loten in dezelfde veiling met volgnummer strikt hoger dan $current_seq.
   *
   * @param int $auction_id
   * @param int $current_seq
   * @return \Drupal\node\NodeInterface[]
   */
  private function loadFollowingLots(int $auction_id, int $current_seq): array {
    $query = $this->etm->getStorage('node')->getQuery()
      ->condition('type', 'lot')
      ->condition('field_auction_reference', $auction_id)
      ->condition('field_lot_nr', $current_seq, '>')
      ->accessCheck(FALSE)
      ->sort('field_lot_nr', 'ASC');

    $nids = $query->execute();
    if (empty($nids)) {
      return [];
    }
    $lots = $this->etm->getStorage('node')->loadMultiple($nids);
    // Filter voorzichtig op juiste bundle (safety).
    return array_values(array_filter($lots, fn($n) => $n instanceof NodeInterface && $n->bundle() === 'lot'));
  }

  /**
   * Converteer datetime storage (UTC) naar UNIX timestamp.
   */
  private function fieldToTimestamp(NodeInterface $node, string $field_name): ?int {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return null;
    }
    $raw = $node->get($field_name)->value;
    if (!$raw) {
      return null;
    }
    $dt = new DrupalDateTime($raw, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return $dt->getTimestamp();
  }

  /**
   * Schuif einddatum van lot met +$delta seconden en sla op als storage (UTC).
   */
  private function shiftLotEnd(NodeInterface $lot, int $delta): void {
    $raw = $lot->get('field_einddatum')->value ?? null;
    if (!$raw) {
      return;
    }
    $dt = new DrupalDateTime($raw, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $dt->modify('+' . $delta . ' seconds');
    $lot->set('field_einddatum', $dt->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    $lot->save();
  }

  /**
   * Invalideer caches van nodes (view build caches, etc.).
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   */
  private function invalidateNodes(array $nodes): void {
    $tags = [];
    foreach ($nodes as $n) {
      if ($n instanceof NodeInterface) {
        $tags = array_merge($tags, $n->getCacheTags());
      }
    }
    if ($tags) {
      $this->invalidator->invalidateTags(array_unique($tags));
    }
  }

}
