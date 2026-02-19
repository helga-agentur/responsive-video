<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;

/**
 * Repository for conversion state stored in the responsive_video_conversion tables.
 *
 * All DB access for conversion state goes through this service.
 */
class ConversionRepository
{
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Inserts a new conversion row with status 'pending'.
   */
  public function insert(int $mid): void
  {
    $this->database
      ->insert("responsive_video_conversion")
      ->fields([
        "mid" => $mid,
        "status" => "pending",
        "changed" => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Atomically claims a pending job by setting status to 'processing'.
   *
   * @return bool TRUE if the claim succeeded (this worker owns the job).
   */
  public function claimJob(int $mid): bool
  {
    $updated = $this->database
      ->update("responsive_video_conversion")
      ->fields([
        "status" => "processing",
        "changed" => $this->time->getRequestTime(),
      ])
      ->condition("mid", $mid)
      ->condition("status", "pending")
      ->execute();

    return (int) $updated === 1;
  }

  /**
   * Resets a row to pending, preserving remote_id for upload resume.
   */
  public function resetToPending(int $mid): void
  {
    $this->database
      ->update("responsive_video_conversion")
      ->fields([
        "status" => "pending",
        "changed" => $this->time->getRequestTime(),
      ])
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Marks a job as completed and stores poster FID and dimensions.
   */
  public function markCompleted(
    int $mid,
    ?int $posterFid = null,
    ?int $width = null,
    ?int $height = null,
  ): void {
    $fields = [
      "status" => "completed",
      "remote_id" => null,
      "changed" => $this->time->getRequestTime(),
    ];
    if ($posterFid !== null) {
      $fields["poster_fid"] = $posterFid;
    }
    if ($width !== null) {
      $fields["width"] = $width;
    }
    if ($height !== null) {
      $fields["height"] = $height;
    }
    $this->database
      ->update("responsive_video_conversion")
      ->fields($fields)
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Marks a job as failed and optionally clears the remote_id.
   */
  public function markFailed(int $mid, bool $clearRemoteId = false): void
  {
    $fields = [
      "status" => "failed",
      "changed" => $this->time->getRequestTime(),
    ];
    if ($clearRemoteId) {
      $fields["remote_id"] = null;
    }
    $this->database
      ->update("responsive_video_conversion")
      ->fields($fields)
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Stores the remote ID and source dimensions after a successful upload.
   */
  public function setRemoteId(
    int $mid,
    string $remoteId,
    ?int $width = null,
    ?int $height = null,
  ): void {
    $fields = ["remote_id" => $remoteId];
    if ($width !== null) {
      $fields["width"] = $width;
    }
    if ($height !== null) {
      $fields["height"] = $height;
    }
    $this->database
      ->update("responsive_video_conversion")
      ->fields($fields)
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Clears the remote ID (after successful remote deletion).
   */
  public function clearRemoteId(int $mid): void
  {
    $this->database
      ->update("responsive_video_conversion")
      ->fields(["remote_id" => null])
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Loads the full conversion row for a media entity.
   *
   * @return array|null Row data or NULL if not found.
   */
  public function load(int $mid): ?array
  {
    $row = $this->database
      ->select("responsive_video_conversion", "c")
      ->fields("c")
      ->condition("c.mid", $mid)
      ->execute()
      ->fetchAssoc();

    return $row ?: null;
  }

  /**
   * Loads all converted file rows for a media entity.
   *
   * @return array[] Rows keyed by sequential index.
   */
  public function loadFiles(int $mid): array
  {
    return $this->database
      ->select("responsive_video_conversion_file", "f")
      ->fields("f")
      ->condition("f.mid", $mid)
      ->execute()
      ->fetchAll(FetchAs::Associative);
  }

  /**
   * Inserts a converted file row.
   */
  public function insertFile(
    int $mid,
    string $codecId,
    string $styleId,
    int $fid,
  ): void {
    $this->database
      ->insert("responsive_video_conversion_file")
      ->fields([
        "mid" => $mid,
        "codec_id" => $codecId,
        "style_id" => $styleId,
        "fid" => $fid,
      ])
      ->execute();
  }

  /**
   * Deletes all converted file rows for a media entity and returns their FIDs.
   *
   * @return int[] Array of file IDs that were deleted from the table.
   */
  public function deleteFiles(int $mid): array
  {
    $fids = $this->database
      ->select("responsive_video_conversion_file", "f")
      ->fields("f", ["fid"])
      ->condition("f.mid", $mid)
      ->execute()
      ->fetchCol();

    $this->database
      ->delete("responsive_video_conversion_file")
      ->condition("mid", $mid)
      ->execute();

    return array_map("intval", $fids);
  }

  /**
   * Deletes the conversion row for a media entity.
   */
  public function delete(int $mid): void
  {
    $this->database
      ->delete("responsive_video_conversion")
      ->condition("mid", $mid)
      ->execute();
  }

  /**
   * Returns all MIDs with status 'failed' and retry_count below the given max.
   *
   * @param int $maxRetries Only return rows where retry_count < $maxRetries.
   * @return int[] Array of retryable media entity IDs.
   */
  public function loadFailedMids(int $maxRetries): array
  {
    return array_map(
      "intval",
      $this->database
        ->select("responsive_video_conversion", "c")
        ->fields("c", ["mid"])
        ->condition("c.status", "failed")
        ->condition("c.retry_count", $maxRetries, "<")
        ->execute()
        ->fetchCol(),
    );
  }

  /**
   * Increments retry_count by 1 for the given media entity.
   */
  public function incrementRetryCount(int $mid): void
  {
    $this->database->query(
      "UPDATE {responsive_video_conversion} SET retry_count = retry_count + 1 WHERE mid = :mid",
      [":mid" => $mid],
    );
  }

  /**
   * Returns all MIDs with status 'processing' older than the given threshold.
   *
   * @param int $threshold Unix timestamp; rows with changed < threshold are stale.
   * @return int[] Array of stale media entity IDs.
   */
  public function loadStaleMids(int $threshold): array
  {
    return array_map(
      "intval",
      $this->database
        ->select("responsive_video_conversion", "c")
        ->fields("c", ["mid"])
        ->condition("c.status", "processing")
        ->condition("c.changed", $threshold, "<")
        ->execute()
        ->fetchCol(),
    );
  }
}
