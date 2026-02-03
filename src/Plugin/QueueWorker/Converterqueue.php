<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\VideoConverterService;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'responsive_video_converterqueue' queue worker.
 */
#[QueueWorker(
  id: 'responsive_video_converterqueue',
  title: new TranslatableMarkup('ConverterQueue'),
)]
final class Converterqueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new Converterqueue instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly VideoConverterService $videoConverterService,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('responsive_video.converter'),
      $container->get('logger.channel.responsive_video'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Log any exceptions during processing, but do not halt execution.
    try {
      $this->videoConverterService->convertMediaToAllStyles($data);
    }
    catch (\Exception $e) {
      $this->logger->error('Error processing video conversion queue item for media ID %media_id: %message', [
        '%media_id' => $data,
        '%message' => $e->getMessage(),
      ]);
    }
  }

}
