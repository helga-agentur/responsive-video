<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\media\MediaInterface;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Drupal\responsive_video\Hook\HookMedia;
use Drupal\Tests\UnitTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A concrete stub for MediaInterface that declares the `original` property.
 *
 * PHPUnit cannot mock interfaces that extend Traversable as anonymous classes,
 * and setting dynamic properties on mock objects triggers PHP 8.2 deprecations.
 * This named stub is the simplest way to declare `original` cleanly.
 */
abstract class MediaStub implements MediaInterface, \IteratorAggregate
{
  public ?MediaInterface $original = null;

  public function getIterator(): \ArrayIterator
  {
    return new \ArrayIterator([]);
  }
}

/**
 * @coversDefaultClass \Drupal\responsive_video\Hook\HookMedia
 * @group responsive_video
 */
class HookMediaTest extends UnitTestCase
{
  private EventDispatcherInterface $dispatcher;
  private HookMedia $hook;

  protected function setUp(): void
  {
    parent::setUp();
    $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->hook = new HookMedia($this->dispatcher);
  }

  /**
   * Creates a MediaInterface mock with the given bundle, file ID, and original.
   *
   * Uses a named abstract stub class so that `original` is a declared property,
   * avoiding the PHP 8.2 dynamic-property deprecation.
   */
  private function mockMedia(
    string $bundle,
    int $fid = 1,
    ?MediaInterface $original = null,
  ): MediaInterface {
    // stdClass so ->target_id is a declared property (no dynamic-prop warning).
    $fieldList = new \stdClass();
    $fieldList->target_id = $fid;

    /** @var MediaStub&\PHPUnit\Framework\MockObject\MockObject $media */
    $media = $this->getMockBuilder(MediaStub::class)
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();

    $media->method("bundle")->willReturn($bundle);
    $media->method("id")->willReturn("3");
    // No ->with() so the stub fires for any field name.
    $media->method("get")->willReturn($fieldList);
    $media->original = $original;

    return $media;
  }

  // ---------------------------------------------------------------------------
  // onInsert
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onInsert
   */
  public function testInsertDispatchesCreateForCorrectBundle(): void
  {
    $this->dispatcher
      ->expects($this->once())
      ->method("dispatch")
      ->with(
        $this->isInstanceOf(ResponsiveVideoEvent::class),
        ResponsiveVideoEvent::CREATE,
      );

    $this->hook->onInsert($this->mockMedia("responsive_video"));
  }

  /**
   * @covers ::onInsert
   */
  public function testInsertIgnoresOtherBundles(): void
  {
    $this->dispatcher->expects($this->never())->method("dispatch");
    $this->hook->onInsert($this->mockMedia("image"));
  }

  // ---------------------------------------------------------------------------
  // onUpdate
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onUpdate
   */
  public function testUpdateIgnoresOtherBundles(): void
  {
    $this->dispatcher->expects($this->never())->method("dispatch");
    $this->hook->onUpdate($this->mockMedia("image"));
  }

  /**
   * @covers ::onUpdate
   */
  public function testUpdateIgnoresWhenOriginalIsNull(): void
  {
    $this->dispatcher->expects($this->never())->method("dispatch");
    // original defaults to NULL.
    $this->hook->onUpdate($this->mockMedia("responsive_video"));
  }

  /**
   * @covers ::onUpdate
   */
  public function testUpdateIgnoresWhenFileUnchanged(): void
  {
    $this->dispatcher->expects($this->never())->method("dispatch");
    $original = $this->mockMedia("responsive_video", fid: 1);
    $media = $this->mockMedia("responsive_video", fid: 1, original: $original);
    $this->hook->onUpdate($media);
  }

  /**
   * @covers ::onUpdate
   */
  public function testUpdateDispatchesWhenFileChanged(): void
  {
    $this->dispatcher
      ->expects($this->once())
      ->method("dispatch")
      ->with(
        $this->isInstanceOf(ResponsiveVideoEvent::class),
        ResponsiveVideoEvent::UPDATE,
      );

    $original = $this->mockMedia("responsive_video", fid: 1);
    $media = $this->mockMedia("responsive_video", fid: 2, original: $original);
    $this->hook->onUpdate($media);
  }

  // ---------------------------------------------------------------------------
  // onDelete
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onDelete
   */
  public function testDeleteDispatchesDeleteForCorrectBundle(): void
  {
    $this->dispatcher
      ->expects($this->once())
      ->method("dispatch")
      ->with(
        $this->isInstanceOf(ResponsiveVideoEvent::class),
        ResponsiveVideoEvent::DELETE,
      );

    $this->hook->onDelete($this->mockMedia("responsive_video"));
  }

  /**
   * @covers ::onDelete
   */
  public function testDeleteIgnoresOtherBundles(): void
  {
    $this->dispatcher->expects($this->never())->method("dispatch");
    $this->hook->onDelete($this->mockMedia("image"));
  }
}
