<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\VideoConverterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes video conversion jobs.
 */
#[
  QueueWorker(
    id: "responsive_video_converterqueue",
    title: new TranslatableMarkup("Responsive Video: Converter"),
    cron: ["time" => 300],
  ),
]
final class Converterqueue extends QueueWorkerBase implements
  ContainerFactoryPluginInterface
{
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly VideoConverterService $converterService,
    private readonly ConversionRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("responsive_video.converter"),
      $container->get("responsive_video.conversion_repository"),
      $container->get("entity_type.manager"),
      $container->get("logger.factory")->get("responsive_video"),
    );
  }

  /**
   * {@inheritdoc}
   *
   * $data is the Media entity ID (int).
   */
  public function processItem($data): void
  {
    $mid = (int) $data;

    // Atomic claim: only one worker proceeds when multiple workers race.
    if (!$this->repository->claimJob($mid)) {
      // Another worker already claimed this job or it is no longer pending.
      return;
    }

    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $this->entityTypeManager->getStorage("media")->load($mid);
    if ($media === null) {
      $this->logger->warning(
        "Responsive Video: media @mid not found; skipping.",
        ["@mid" => $mid],
      );
      $this->repository->markFailed($mid, clearRemoteId: true);
      return;
    }

    try {
      $this->converterService->convertMedia($media);
    } catch (\Throwable $e) {
      $this->logger->error(
        "Responsive Video: conversion failed for mid @mid: @msg",
        [
          "@mid" => $mid,
          "@msg" => $e->getMessage(),
        ],
      );
      // clearRemoteId is handled inside VideoConverterService's catch block;
      // we only need to set the final failed status here.
      $this->repository->markFailed($mid, clearRemoteId: true);
    }
  }
}
