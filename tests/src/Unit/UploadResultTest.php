<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\responsive_video\UploadResult;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\UploadResult
 * @group responsive_video
 */
class UploadResultTest extends UnitTestCase {

  /**
   * @covers ::__construct
   */
  public function testProperties(): void {
    $result = new UploadResult('my-remote-id', 1920, 1080);
    $this->assertSame('my-remote-id', $result->remoteId);
    $this->assertSame(1920, $result->width);
    $this->assertSame(1080, $result->height);
  }

  /**
   * @covers ::__construct
   */
  public function testZeroDimensions(): void {
    $result = new UploadResult('id', 0, 0);
    $this->assertSame(0, $result->width);
    $this->assertSame(0, $result->height);
  }

}
