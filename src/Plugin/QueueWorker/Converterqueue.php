<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\VideoConverterService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'responsive_video_converterqueue' queue worker.
 */
#[QueueWorker(
  id: 'responsive_video_converterqueue',
  title: new TranslatableMarkup('ConverterQueue'),  
  cron: ['time' => 300]  
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->videoConverterService->convertMediaToAllStyles($data);

  }

}
