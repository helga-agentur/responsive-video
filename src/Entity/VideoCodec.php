<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\Form\VideoCodecForm;
use Drupal\responsive_video\VideoCodecInterface;
use Drupal\responsive_video\VideoCodecListBuilder;

/**
 * Defines the video codec entity type.
 */
#[
  ConfigEntityType(
    id: "video_codec",
    label: new TranslatableMarkup("Video Codec"),
    label_collection: new TranslatableMarkup("Video Codecs"),
    label_singular: new TranslatableMarkup("video codec"),
    label_plural: new TranslatableMarkup("video codecs"),
    config_prefix: "video_codec",
    entity_keys: [
      "id" => "id",
      "label" => "label",
      "uuid" => "uuid",
      "status" => "status",
    ],
    handlers: [
      "list_builder" => VideoCodecListBuilder::class,
      "form" => [
        "add" => VideoCodecForm::class,
        "edit" => VideoCodecForm::class,
        "delete" => EntityDeleteForm::class,
      ],
    ],
    links: [
      "collection" => "/admin/structure/video-codec",
      "add-form" => "/admin/structure/video-codec/add",
      "edit-form" => "/admin/structure/video-codec/{video_codec}",
      "delete-form" => "/admin/structure/video-codec/{video_codec}/delete",
    ],
    admin_permission: "administer video_codec",
    label_count: [
      "singular" => "@count video codec",
      "plural" => "@count video codecs",
    ],
    config_export: [
      "id",
      "label",
      "fileEnding",
      "mimeType",
      "weight",
      "status",
    ],
  ),
]
class VideoCodec extends ConfigEntityBase implements VideoCodecInterface
{
  protected string $id;

  protected string $label;

  protected string $fileEnding;

  protected string $mimeType;

  protected int $weight = 0;

  public function getFileEnding(): string
  {
    return $this->fileEnding;
  }

  public function getMimeType(): string
  {
    return $this->mimeType;
  }

  public function getWeight(): int
  {
    return $this->weight;
  }
}
