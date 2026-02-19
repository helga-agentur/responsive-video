<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\MediaInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates video conversion for a single Media entity.
 *
 * Called only from the converter queue worker. All dependencies are injected;
 * no static calls. Never resaves the Media entity.
 */
final class VideoConverterService
{
  public function __construct(
    private readonly ConverterPluginManager $pluginManager,
    private readonly ConversionRepository $repository,
    private readonly FilesystemManager $filesystemManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Converts all codec/style combinations for the given Media entity.
   *
   * Called by the queue worker after a successful claimJob().
   *
   * @throws \Exception Any unrecoverable error; caller handles status update.
   */
  public function convertMedia(MediaInterface $media): void
  {
    $mid = (int) $media->id();
    $plugin = $this->pluginManager->getActivePlugin();
    if ($plugin === null) {
      throw new \RuntimeException("No converter plugin configured.");
    }

    $settings = $this->configFactory->get("responsive_video.settings");
    $maxAttempts = (int) ($settings->get("poll_max_attempts") ?? 10);
    $pollInterval = (int) ($settings->get("poll_interval_seconds") ?? 5);

    $row = $this->repository->load($mid);
    $tempFiles = [];

    try {
      // --- Upload (skip if remote_id already set from a previous attempt) ---
      if (!empty($row["remote_id"])) {
        $remoteId = $row["remote_id"];
        $this->logger->info("Reusing existing remote_id @rid for mid @mid.", [
          "@rid" => $remoteId,
          "@mid" => $mid,
        ]);
      } else {
        $sourceFile = $this->getSourceFile($media);
        $uploadResult = $plugin->uploadVideo($sourceFile);
        $remoteId = $uploadResult->remoteId;
        $this->repository->setRemoteId(
          $mid,
          $remoteId,
          $uploadResult->width,
          $uploadResult->height,
        );
      }

      // --- Load all active codecs and styles ---
      /** @var \Drupal\responsive_video\Entity\VideoCodec[] $codecs */
      $codecs = $this->entityTypeManager
        ->getStorage("video_codec")
        ->loadByProperties(["status" => true]);
      usort($codecs, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

      /** @var \Drupal\responsive_video\Entity\VideoStyle[] $styles */
      $styles = $this->entityTypeManager
        ->getStorage("video_style")
        ->loadMultiple();

      $this->filesystemManager->prepareOutputDirectory();

      // --- Poll + download each codec/style combo ---
      $fileEntries = [];
      foreach ($codecs as $codec) {
        foreach ($styles as $style) {
          $this->waitUntilReady(
            $plugin,
            $remoteId,
            $codec,
            $style,
            $maxAttempts,
            $pollInterval,
          );

          $ext = $codec->getFileEnding();
          $tempPath =
            $this->fileSystem->getTempDirectory() .
            "/rv_" .
            $mid .
            "_" .
            $codec->id() .
            "_" .
            $style->id() .
            "." .
            $ext;
          $tempFiles[] = $tempPath;

          $plugin->downloadConvertedFile($remoteId, $codec, $style, $tempPath);

          $finalUri = $this->filesystemManager->convertedFileUri(
            $mid,
            $codec->id(),
            $style->id(),
            $ext,
          );
          $this->filesystemManager->moveToFinal($tempPath, $finalUri);

          // Register as a managed file.
          $file = $this->registerManagedFile($finalUri, $mid);

          $fileEntries[] = [
            "codec" => $codec->id(),
            "style" => $style->id(),
            "fid" => (int) $file->id(),
          ];
        }
      }

      // --- Download poster ---
      $posterFid = null;
      $posterTemp =
        $this->fileSystem->getTempDirectory() . "/rv_poster_" . $mid . ".jpg";
      $tempFiles[] = $posterTemp;
      try {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
          if ($plugin->isPosterReady($remoteId)) {
            break;
          }
          if ($attempt < $maxAttempts) {
            sleep($pollInterval * 2 ** ($attempt - 1));
          }
        }
        $plugin->downloadPoster($remoteId, $posterTemp);
        $posterUri = $this->filesystemManager->posterUri($mid);
        $this->filesystemManager->moveToFinal($posterTemp, $posterUri);
        $posterFile = $this->registerManagedFile($posterUri, $mid);
        $posterFid = (int) $posterFile->id();
      } catch (\Throwable $e) {
        $this->logger->warning("Poster download failed for mid @mid: @msg", [
          "@mid" => $mid,
          "@msg" => $e->getMessage(),
        ]);
      }

      // --- Delete remote ---
      try {
        $plugin->deleteRemote($remoteId);
      } catch (\Throwable $e) {
        $this->logger->warning(
          "deleteRemote failed for mid @mid (remote_id @rid): @msg",
          [
            "@mid" => $mid,
            "@rid" => $remoteId,
            "@msg" => $e->getMessage(),
          ],
        );
      }

      // --- Persist results ---
      // Delete any previous file rows before inserting fresh ones.
      $this->repository->deleteFiles($mid);
      foreach ($fileEntries as $entry) {
        $this->repository->insertFile(
          $mid,
          $entry["codec"],
          $entry["style"],
          $entry["fid"],
        );
      }

      $reloaded = $this->repository->load($mid);
      $this->repository->markCompleted(
        mid: $mid,
        posterFid: $posterFid,
        width: (int) ($reloaded["width"] ?? 0) ?: null,
        height: (int) ($reloaded["height"] ?? 0) ?: null,
      );
      $this->repository->clearRemoteId($mid);

      // Invalidate cached render output for this media entity.
      $this->cacheTagsInvalidator->invalidateTags(["media:" . $mid]);
    } catch (\Throwable $e) {
      // Attempt remote cleanup; do not let its failure mask the original error.
      $currentRow = $this->repository->load($mid);
      if (!empty($currentRow["remote_id"])) {
        try {
          $plugin->deleteRemote($currentRow["remote_id"]);
        } catch (\Throwable $deleteEx) {
          $this->logger->error(
            "deleteRemote on failure path failed for mid @mid: @msg",
            [
              "@mid" => $mid,
              "@msg" => $deleteEx->getMessage(),
            ],
          );
        }
        $this->repository->clearRemoteId($mid);
      }
      throw $e;
    } finally {
      // Always clean up temp files.
      foreach ($tempFiles as $tmp) {
        if (file_exists($tmp)) {
          @unlink($tmp);
        }
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  /**
   * Registers an already-existing file URI as a Drupal managed file.
   *
   * Unlike FileRepository::writeData(), this does not overwrite file contents.
   */
  private function registerManagedFile(
    string $uri,
    int $mid,
  ): \Drupal\file\FileInterface {
    $existing = $this->entityTypeManager
      ->getStorage("file")
      ->loadByProperties(["uri" => $uri]);
    if ($existing) {
      $file = reset($existing);
    } else {
      $file = \Drupal\file\Entity\File::create([
        "uri" => $uri,
        "status" => 1,
      ]);
      $file->save();
    }
    $this->fileUsage->add($file, "responsive_video", "media", (string) $mid);
    return $file;
  }

  private function getSourceFile(MediaInterface $media): FileInterface
  {
    $fid = $media->get("field_media_video_file")->target_id;
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage("file")->load($fid);
    if ($file === null) {
      throw new \RuntimeException(
        "Source file {$fid} not found for media {$media->id()}.",
      );
    }
    return $file;
  }

  /**
   * Polls the plugin until the transformation is ready, with exponential backoff.
   *
   * @throws \RuntimeException When max attempts are exhausted.
   */
  private function waitUntilReady(
    ResponsiveVideoConverterApiPluginInterface $plugin,
    string $remoteId,
    \Drupal\responsive_video\Entity\VideoCodec $codec,
    \Drupal\responsive_video\Entity\VideoStyle $style,
    int $maxAttempts,
    int $baseInterval,
  ): void {
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
      if ($plugin->isReady($remoteId, $codec, $style)) {
        return;
      }
      if ($attempt < $maxAttempts) {
        // Exponential backoff: 5s, 10s, 20s, …
        sleep($baseInterval * 2 ** ($attempt - 1));
      }
    }
    throw new \RuntimeException(
      "Transformation not ready after {$maxAttempts} attempts for remote_id={$remoteId} codec={$codec->id()} style={$style->id()}.",
    );
  }
}
