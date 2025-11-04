<?php

namespace Drupal\auction_commands;

use Drupal\node\Entity\Node;

/**
 * Class BatchService
 *
 * @package Drupal\auction_commands
 */
class BatchService {

  /**
   * Batch process callback.
   *
   * @param int $id
   *   ID of the batch.
   * @param string $operation_details
   *   Details of the operation.
   * @param object $context
   *   Context for the operation
   * @param string $nid
   *   The node id to process
   */
  public function processNode($id, $operation_details, $nid, &$context) {
    // Store some results for post-processing in the 'finished' callback.
    // The contents of 'results' will be available as $results in the
    // 'finished' function (in this example, processMyNodeFinished()).
    $context['results'][] = $id;

    $node = Node::load($nid);
    $estimate_text = $node->get('field_maximum_estimate_text')->getValue();
    if (empty($estimate_text)) {
      $max_estimate = $node->get('field_maximum_estimate')->first()->getValue();
      $node->set('field_maximum_estimate_text', $max_estimate);
      $node->save();
    }

    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details',
      ['@id' => $id, '@details' => $operation_details]
    );
  }

  /**
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function processNodeFinished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      // Here we could do something meaningful with the results.
      // We just display the number of nodes we processed...
      $messenger->addMessage(t('@count results processed.', ['@count' => count($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
