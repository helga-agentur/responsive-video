<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\responsive_video\ConversionRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * @coversDefaultClass \Drupal\responsive_video\ConversionRepository
 * @group responsive_video
 */
#[RunTestsInSeparateProcesses]
class ConversionRepositoryTest extends KernelTestBase
{
  protected static $modules = [
    "responsive_video",
    "media",
    "file",
    "user",
    "image",
    "field",
    "system",
  ];

  protected function setUp(): void
  {
    parent::setUp();
    $this->installSchema("responsive_video", [
      "responsive_video_conversion",
      "responsive_video_conversion_file",
    ]);
    $this->installEntitySchema("user");
    $this->installEntitySchema("file");
  }

  private function repo(): ConversionRepository
  {
    return $this->container->get("responsive_video.conversion_repository");
  }

  /**
   * @covers ::insert
   * @covers ::load
   */
  public function testInsertAndLoad(): void
  {
    $this->repo()->insert(1);
    $row = $this->repo()->load(1);

    $this->assertNotNull($row);
    $this->assertSame("1", (string) $row["mid"]);
    $this->assertSame("pending", $row["status"]);
    $this->assertNull($row["remote_id"]);
  }

  /**
   * @covers ::load
   */
  public function testLoadReturnsNullForMissing(): void
  {
    $this->assertNull($this->repo()->load(999));
  }

  /**
   * @covers ::claimJob
   */
  public function testClaimJobTransitionsToProcessing(): void
  {
    $this->repo()->insert(2);
    $claimed = $this->repo()->claimJob(2);

    $this->assertTrue($claimed);
    $this->assertSame("processing", $this->repo()->load(2)["status"]);
  }

  /**
   * @covers ::claimJob
   */
  public function testClaimJobReturnsFalseWhenAlreadyProcessing(): void
  {
    $this->repo()->insert(3);
    $this->repo()->claimJob(3); // First claim succeeds.
    $second = $this->repo()->claimJob(3); // Second must fail.

    $this->assertFalse($second);
  }

  /**
   * @covers ::resetToPending
   */
  public function testResetToPending(): void
  {
    $this->repo()->insert(4);
    $this->repo()->claimJob(4);
    $this->repo()->resetToPending(4);

    $this->assertSame("pending", $this->repo()->load(4)["status"]);
  }

  /**
   * @covers ::markCompleted
   */
  public function testMarkCompleted(): void
  {
    $this->repo()->insert(5);
    $this->repo()->claimJob(5);
    $this->repo()->markCompleted(5, posterFid: 99, width: 1920, height: 1080);

    $row = $this->repo()->load(5);
    $this->assertSame("completed", $row["status"]);
    $this->assertSame("99", (string) $row["poster_fid"]);
    $this->assertSame("1920", (string) $row["width"]);
    $this->assertSame("1080", (string) $row["height"]);
  }

  /**
   * @covers ::markFailed
   */
  public function testMarkFailed(): void
  {
    $this->repo()->insert(6);
    $this->repo()->claimJob(6);
    $this->repo()->setRemoteId(6, "remote-xyz");
    $this->repo()->markFailed(6, clearRemoteId: true);

    $row = $this->repo()->load(6);
    $this->assertSame("failed", $row["status"]);
    $this->assertNull($row["remote_id"]);
  }

  /**
   * @covers ::setRemoteId
   * @covers ::clearRemoteId
   */
  public function testSetAndClearRemoteId(): void
  {
    $this->repo()->insert(7);
    $this->repo()->setRemoteId(7, "abc123", 1920, 1080);

    $row = $this->repo()->load(7);
    $this->assertSame("abc123", $row["remote_id"]);
    $this->assertSame("1920", (string) $row["width"]);

    $this->repo()->clearRemoteId(7);
    $this->assertNull($this->repo()->load(7)["remote_id"]);
  }

  /**
   * @covers ::insertFile
   * @covers ::loadFiles
   * @covers ::deleteFiles
   */
  public function testFileInsertLoadDelete(): void
  {
    $this->repo()->insert(8);
    $this->repo()->insertFile(8, "h264", "720p", 100);
    $this->repo()->insertFile(8, "av1", "720p", 101);

    $files = $this->repo()->loadFiles(8);
    $this->assertCount(2, $files);

    $fids = $this->repo()->deleteFiles(8);
    $this->assertEqualsCanonicalizing([100, 101], $fids);
    $this->assertCount(0, $this->repo()->loadFiles(8));
  }

  /**
   * @covers ::delete
   */
  public function testDeleteRemovesRow(): void
  {
    $this->repo()->insert(9);
    $this->repo()->delete(9);

    $this->assertNull($this->repo()->load(9));
  }

  /**
   * @covers ::loadStaleMids
   */
  public function testLoadStaleMids(): void
  {
    $this->repo()->insert(10);
    $this->repo()->claimJob(10); // status = processing

    // Threshold far in the future: everything is stale.
    $stale = $this->repo()->loadStaleMids(PHP_INT_MAX);
    $this->assertContains(10, $stale);
  }

  /**
   * @covers ::loadStaleMids
   */
  public function testLoadStaleMidsExcludesRecent(): void
  {
    $this->repo()->insert(11);
    $this->repo()->claimJob(11);

    // Threshold in the past: nothing is stale.
    $stale = $this->repo()->loadStaleMids(0);
    $this->assertNotContains(11, $stale);
  }

  /**
   * @covers ::loadFailedMids
   */
  public function testLoadFailedMidsReturnsBelowMaxRetries(): void
  {
    $this->repo()->insert(12);
    $this->repo()->markFailed(12);
    // retry_count starts at 0; max is 10.
    $failed = $this->repo()->loadFailedMids(10);
    $this->assertContains(12, $failed);
  }

  /**
   * @covers ::loadFailedMids
   */
  public function testLoadFailedMidsExcludesAtMaxRetries(): void
  {
    $this->repo()->insert(13);
    $this->repo()->markFailed(13);

    // Increment up to the max.
    for ($i = 0; $i < 10; $i++) {
      $this->repo()->incrementRetryCount(13);
    }

    $failed = $this->repo()->loadFailedMids(10);
    $this->assertNotContains(13, $failed);
  }

  /**
   * @covers ::incrementRetryCount
   */
  public function testIncrementRetryCount(): void
  {
    $this->repo()->insert(14);
    $this->repo()->incrementRetryCount(14);
    $this->repo()->incrementRetryCount(14);

    $row = $this->repo()->load(14);
    $this->assertSame(2, (int) $row["retry_count"]);
  }
}
