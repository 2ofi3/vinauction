<?php

namespace Drupal\auction_core\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Link;

final class BidLinkBuilder {
  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly RouteBuilderInterface $routerBuilder,
  ) {}

  /**
   * Bouwt een renderable link (of fallback tekst) naar de offer-node.
   */
  public function build(int $lotNid, int $auctionNid): array|string {
    $current_user = \Drupal::currentUser();
    if (!$current_user->isAuthenticated()) {
      return $this->t('Inloggen om te bieden');
    }

    // Zoek 1 offer node (TODO: maak dit specifieker als je meerdere hebt).
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'offer')
      ->range(0, 1)
      ->execute();

    if (!$nids) {
      return $this->t('Geen biedformulier gevonden');
    }

    $offer_id = (int) reset($nids);
    $link = Link::createFromRoute(
      $this->t('Bieden'),
      'entity.node.canonical',
      ['node' => $offer_id],
      [
        'attributes' => ['class' => 'order-btn'],
        'query' => [
          'lot_select' => $lotNid,
          'auction_select' => $auctionNid,
        ],
      ]
    );

    return $link->toRenderable();
  }
}
