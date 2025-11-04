<?php

namespace Drupal\auction_commands\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * A Drush command file.
 *
 * @package Drupal\custom_auction_commands\Commands
 */
class AuctionCommands extends DrushCommands {

  /**
   * Entity type service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;
  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerChannelFactory;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * Transfer old max field to new max field.
   *
   * @command auction_commands:execute
   *
   * @aliases act
   *
   * @usage auction_commands:execute
   *
   */
  public function execute() {
    // 1. Log the start of the script.
    $this->loggerChannelFactory->get('auction_commands')->info('Update nodes batch operations start');

    // 2. Get nodes of type lot.
    $nids = \Drupal::entityQuery('node')->condition('type','lot')->execute();
    $nodes =  Node::loadMultiple($nids);

    // 3. Create the operations array for the batch.
    $operations = [];
    $numOperations = 0;
    $batchId = 1;

    foreach ($nodes as $node) {
      // Prepare the operation. Here we could do other operations on nodes.
      $this->output()->writeln("Preparing batch: " . $batchId);
      $operations[] = [
        '\Drupal\auction_commands\BatchService::processNode',
        [
          $batchId,
          t('Updating node @nid', ['@nid' => $node->id()]),
          $node->id(),
        ],
      ];
      $batchId++;
      $numOperations++;
    }

    // 4. Create the batch.
    $batch = [
      'title' => t('Updating @num node(s)', ['@num' => $numOperations]),
      'operations' => $operations,
      'finished' => '\Drupal\auction_commands\BatchService::processNodeFinished',
    ];

    // 5. Add batch operations as new batch sets.
    batch_set($batch);
    // 6. Process the batch sets.
    drush_backend_batch_process();
    // 6. Show some information.
    $this->logger()->notice("Batch operations end.");
    // 7. Log some information.
    $this->loggerChannelFactory->get('auction_commands')->info('Update batch operations end.');

  }
}
