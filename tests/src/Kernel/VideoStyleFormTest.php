<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Kernel;

use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\responsive_video\Entity\VideoStyle;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for VideoStyleForm validation and save logic.
 *
 * @coversDefaultClass \Drupal\responsive_video\Form\VideoStyleForm
 * @group responsive_video
 */
#[RunTestsInSeparateProcesses]
class VideoStyleFormTest extends KernelTestBase
{
  // Only the modules needed for config entity storage — no media, no views.
  protected static $modules = [
    "responsive_video",
    "file",
    "user",
    "image",
    "field",
    "system",
  ];

  protected function setUp(): void
  {
    parent::setUp();
    $this->installEntitySchema("user");
    $this->installSchema("responsive_video", [
      "responsive_video_conversion",
      "responsive_video_conversion_file",
    ]);
  }

  /**
   * @covers \Drupal\responsive_video\Form\VideoStyleForm::validateForm
   *
   * Tests that both width and height being empty triggers a validation error.
   */
  public function testVideoStyleRequiresWidthOrHeight(): void
  {
    $style = VideoStyle::create([
      "id" => "no_dims",
      "label" => "No dims",
      "width" => null,
      "height" => null,
    ]);

    $this->assertInstanceOf(VideoStyle::class, $style);

    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState
      ->method("getValue")
      ->willReturnMap([["width", null, null], ["height", null, null]]);
    $formState->expects($this->atLeastOnce())->method("setErrorByName");

    $entityForm = \Drupal::service("entity_type.manager")->getFormObject(
      "video_style",
      "edit",
    );
    $entityForm->setEntity($style);
    $entityForm->validateForm($form, $formState);
  }

  /**
   * @covers \Drupal\responsive_video\Form\VideoStyleForm::validateForm
   *
   * Tests that providing width alone passes validation.
   */
  public function testVideoStyleWithWidthPassesValidation(): void
  {
    $style = VideoStyle::create([
      "id" => "width_only",
      "label" => "Width only",
      "width" => "1280",
      "height" => null,
    ]);

    $form = [];
    $formState = $this->createMock(FormStateInterface::class);
    $formState
      ->method("getValue")
      ->willReturnMap([["width", null, "1280"], ["height", null, null]]);
    $formState->expects($this->never())->method("setErrorByName");

    $entityForm = \Drupal::service("entity_type.manager")->getFormObject(
      "video_style",
      "edit",
    );
    $entityForm->setEntity($style);
    $entityForm->validateForm($form, $formState);
  }

  /**
   * Tests that a VideoStyle config entity can be saved and reloaded.
   */
  public function testVideoStyleEntitySaveAndLoad(): void
  {
    $style = VideoStyle::create([
      "id" => "test_1080p",
      "label" => "Test 1080p",
      "width" => "1920",
      "height" => null,
    ]);
    $style->save();

    $loaded = VideoStyle::load("test_1080p");
    $this->assertInstanceOf(VideoStyle::class, $loaded);
    $this->assertSame("1920", $loaded->getWidth());
    $this->assertSame("Test 1080p", $loaded->label());
  }
}
