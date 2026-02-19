<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

/**
 * Manages the public filesystem directories used by Responsive Video.
 */
class FilesystemManager
{
  public function __construct(
    private FileSystemInterface $fileSystem,
    private ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the configured output directory name (relative to public://).
   */
  public function outputDirectory(): string
  {
    $dir = $this->configFactory
      ->get("responsive_video.settings")
      ->get("output_directory");
    return $dir ?: "responsive_videos";
  }

  /**
   * Returns the base URI: public://<output_directory>/Y-m/.
   */
  public function dateBaseUri(): string
  {
    return "public://" . $this->outputDirectory() . "/" . date("Y-m");
  }

  /**
   * Ensures the date-scoped output directory exists and is writable.
   */
  public function prepareOutputDirectory(): void
  {
    $uri = $this->dateBaseUri();
    $this->fileSystem->prepareDirectory(
      $uri,
      FileSystemInterface::CREATE_DIRECTORY |
        FileSystemInterface::MODIFY_PERMISSIONS,
    );
  }

  /**
   * Moves a temp file to its final public URI, replacing any existing file.
   *
   * @param string $tempPath  Absolute temp path (from getTempDirectory()).
   * @param string $finalUri  Final public:// URI.
   */
  public function moveToFinal(string $tempPath, string $finalUri): void
  {
    $directory = dirname($finalUri);
    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY |
        FileSystemInterface::MODIFY_PERMISSIONS,
    );
    $this->fileSystem->move($tempPath, $finalUri, FileExists::Replace);
  }

  /**
   * Builds the final URI for a converted video file.
   *
   * @param int    $mid      Media entity ID.
   * @param string $codecId  VideoCodec entity ID.
   * @param string $styleId  VideoStyle entity ID.
   * @param string $ext      File extension (without dot).
   */
  public function convertedFileUri(
    int $mid,
    string $codecId,
    string $styleId,
    string $ext,
  ): string {
    return $this->dateBaseUri() . "/{$mid}/{$styleId}/{$codecId}.{$ext}";
  }

  /**
   * Builds the final URI for the poster image.
   *
   * @param int $mid  Media entity ID.
   */
  public function posterUri(int $mid): string
  {
    return $this->dateBaseUri() . "/{$mid}/poster.jpg";
  }
}
