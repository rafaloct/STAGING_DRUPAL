<?php

declare(strict_types=1);

namespace Drupal\neruds_google_integration\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes sync items with Google Cloud.
 *
 * @QueueWorker(
 *   id = "neruds_google_sync",
 *   title = @Translation("NERUDS Google Sync"),
 *   cron = {"time" = 10}
 * )
 */
class NerudsGoogleSync extends QueueWorkerBase {

  public function processItem($data): void {
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($data['entity_id']);

    if (!$node) {
      \Drupal::logger('neruds_google_integration')
        ->warning('Node @id not found', ['@id' => $data['entity_id']]);
      return;
    }

    $auditService = \Drupal::service('neruds_google_integration.audit');

    switch ($data['action']) {
      case 'insert':
      case 'update':
        $auditService->syncNodeToBigQuery($node);
        break;

      case 'delete':
        $auditService->markNodeDeletedInBigQuery($node->id());
        break;
    }

    \Drupal::logger('neruds_google_integration')
      ->info('Synced @bundle @id (action: @action)', [
        '@bundle' => $data['bundle'],
        '@id' => $data['entity_id'],
        '@action' => $data['action'],
      ]);
  }
}
