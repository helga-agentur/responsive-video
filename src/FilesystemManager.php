<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\File\FileSystemInterface;

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
    $this->fileSystem->prepareDirectory($uri);
  }

  /**
   * @param string $currentYearMonth
   * @return void
   */
  private function prepareDirectory(string $currentYearMonth): void {
    $uri = 'public://' . self::BASE_DIRECTORY . '/' . $currentYearMonth;
    $this->fileSystem->prepareDirectory($uri);
  }

  /**
   * Prepare the current Date directory (Y-m)
   * @return void
   */
  public function prepareDateDirectory(): void {
    $this->assureBaseDirectoryExists();
    $date = date('Y-m');
    $this->prepareDirectory($date);
  }

}
