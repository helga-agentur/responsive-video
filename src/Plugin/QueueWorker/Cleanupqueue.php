<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileUsage\FileUsageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes asynchronous file deletion jobs.
 *
 * Payload: ['mid' => int, 'fids' => int[]]
 */
#[QueueWorker(
  id: 'responsive_video_cleanupqueue',
  title: new TranslatableMarkup('Responsive Video: Cleanup'),
  cron: ['time' => 60],
)]
final class Cleanupqueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUsageInterface $fileUsage,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file.usage'),
      $container->get('logger.factory')->get('responsive_video'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $mid  = (int) ($data['mid'] ?? 0);
    $fids = (array) ($data['fids'] ?? []);

    $fileStorage = $this->entityTypeManager->getStorage('file');

    foreach ($fids as $fid) {
      /** @var \Drupal\file\FileInterface|null $file */
      $file = $fileStorage->load((int) $fid);
      if ($file === NULL) {
        continue;
      }
      // Decrement usage; do not rely on auto-cleanup (configuration-dependent).
      $this->fileUsage->delete($file, 'responsive_video', 'media', (string) $mid);
      try {
        $file->delete();
      }
      catch (\Throwable $e) {
        $this->logger->warning('Responsive Video: could not delete file @fid: @msg', [
          '@fid' => $fid,
          '@msg' => $e->getMessage(),
        ]);
      }
    }
  }

}
