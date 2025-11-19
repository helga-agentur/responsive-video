<?php

namespace Drupal\responsive_video\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\media\MediaInterface;

class ResponsiveVideoEvent extends Event {

  const CREATE = 'responsive_video.create';
  const UPDATE = 'responsive_video.update';
  const DELETE = 'responsive_video.delete';

  protected MediaInterface $medium;

  public function __construct(MediaInterface $medium) {
    $this->medium = $medium;
  }

  public function getMedium(): MediaInterface {
    return $this->medium;
  }
}
