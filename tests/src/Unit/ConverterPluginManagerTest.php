<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\responsive_video\ConverterPluginManager;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginInterface;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginPluginManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\ConverterPluginManager
 * @group responsive_video
 */
class ConverterPluginManagerTest extends UnitTestCase {

  private ImmutableConfig $config;
  private ConfigFactoryInterface $configFactory;
  private ResponsiveVideoConverterApiPluginPluginManager $pluginManager;

  protected function setUp(): void {
    parent::setUp();

    $this->config = $this->createMock(ImmutableConfig::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->method('get')
      ->with('responsive_video.settings')
      ->willReturn($this->config);

    $this->pluginManager = $this->createMock(ResponsiveVideoConverterApiPluginPluginManager::class);
  }

  /**
   * @covers ::getActivePlugin
   */
  public function testGetActivePluginReturnsConfiguredPlugin(): void {
    $this->config->method('get')->willReturnMap([
      ['converter_plugin', 'cloudinary'],
      ['converter_plugin_configuration', ['apiKey' => 'key']],
    ]);

    $plugin = $this->createMock(ResponsiveVideoConverterApiPluginInterface::class);
    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with('cloudinary', ['apiKey' => 'key'])
      ->willReturn($plugin);

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $result = $manager->getActivePlugin();

    $this->assertSame($plugin, $result);
  }

  /**
   * @covers ::getActivePlugin
   */
  public function testGetActivePluginReturnsNullWhenNotConfigured(): void {
    $this->config->method('get')->willReturnMap([
      ['converter_plugin', null],
      ['converter_plugin_configuration', []],
    ]);

    $this->pluginManager->expects($this->never())->method('createInstance');

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $this->assertNull($manager->getActivePlugin());
  }

  /**
   * @covers ::getActivePlugin
   */
  public function testGetActivePluginUsesEmptyArrayWhenNoPluginConfig(): void {
    $this->config->method('get')->willReturnMap([
      ['converter_plugin', 'cloudinary'],
      ['converter_plugin_configuration', null],
    ]);

    $plugin = $this->createMock(ResponsiveVideoConverterApiPluginInterface::class);
    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with('cloudinary', [])
      ->willReturn($plugin);

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $manager->getActivePlugin();
  }

  /**
   * @covers ::hasActivePlugin
   */
  public function testHasActivePluginReturnsTrueWhenConfigured(): void {
    $this->config->method('get')->with('converter_plugin')->willReturn('cloudinary');

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $this->assertTrue($manager->hasActivePlugin());
  }

  /**
   * @covers ::hasActivePlugin
   */
  public function testHasActivePluginReturnsFalseWhenEmpty(): void {
    $this->config->method('get')->with('converter_plugin')->willReturn('');

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $this->assertFalse($manager->hasActivePlugin());
  }

  /**
   * @covers ::getActivePluginId
   */
  public function testGetActivePluginIdReturnsId(): void {
    $this->config->method('get')->with('converter_plugin')->willReturn('cloudinary');

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $this->assertSame('cloudinary', $manager->getActivePluginId());
  }

  /**
   * @covers ::getActivePluginId
   */
  public function testGetActivePluginIdReturnsNullWhenNotSet(): void {
    $this->config->method('get')->with('converter_plugin')->willReturn(null);

    $manager = new ConverterPluginManager($this->configFactory, $this->pluginManager);
    $this->assertNull($manager->getActivePluginId());
  }

}
