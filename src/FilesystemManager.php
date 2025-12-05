<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;

/**
 * This is a Helperservice to manage Filesystem related Tasks for Responsive Videos
 */
final readonly class FilesystemManager {

  const BASE_DIRECTORY = 'responsive_videos';
  const PUBLIC_DIRECTORY = 'public://';

  /**
   * Constructs a FilesystemManager object.
   */
  public function __construct(
    private FileSystemInterface $fileSystem,
  ) {}


  /**
   * Checks if the base directory exists, creates if not
   */
  private function assureBaseDirectoryExists(): void {
    $uri = self::PUBLIC_DIRECTORY . self::BASE_DIRECTORY;
    if (!$this->fileSystem->prepareDirectory($uri)) {
      $this->fileSystem->mkdir($uri);
    };
  }

  /**
   * @param string $directoryName
   *    directory name without base
   * @return string
   *    the full qualified uri
   */
  private function prepareDirectory(string $directoryName): string {
    $uri = self::PUBLIC_DIRECTORY . self::BASE_DIRECTORY . '/' . $directoryName;
    if (!$this->fileSystem->prepareDirectory($uri)) {
      $this->fileSystem->mkdir($uri);
    };

    return $uri;
  }

  /**
   * Prepare the current Date directory (Y-m)
   * @param string $date
   * @return void
   */
  public function prepareDateDirectory(string $date): void {
    $this->assureBaseDirectoryExists();
    $this->prepareDirectory($date);
  }

  public function getMediumFileTargetId(MediaInterface $medium): string {
    return $medium->get('field_media_video_file_1')->target_id;
  }

  public function deleteAssetsOfMedium(MediaInterface $medium): void {
    $fileId = $this->getMediumFileTargetId($medium);

    /** @var File $file */
    $file = File::load($fileId);
    $fileUri = $file->getFileUri();

    $fileNameFull = basename($fileUri);
    $fileName = pathinfo($fileNameFull, PATHINFO_FILENAME);
    $dateDirectoryName = basename(dirname($fileUri));

    $base = $this->fileSystem->realpath(self::PUBLIC_DIRECTORY . self::BASE_DIRECTORY);

    $pattern = "{$base}/*/{$dateDirectoryName}/{$fileName}.*";

    $mediumAssets = glob($pattern);

    foreach ($mediumAssets as $asset) {
      $this->fileSystem->delete($asset);
    }
  }

  public function saveFile(string $fileContents, string $uri) {
    // directory is without base_directory (responsive_image) but with video-style (s, m, l)
    $directory = dirname($uri);
    $fullUri = $this->prepareDirectory($directory) . '/' . basename($uri);
    $this->fileSystem->saveData($fileContents, $fullUri, FileExists::Replace);
  }
}
