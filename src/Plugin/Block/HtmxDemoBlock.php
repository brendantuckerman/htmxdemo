<?php

namespace Drupal\htmxdemo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: 'htmxdemo-block',
  admin_label: new TranslatableMarkup('HtmxDemo Block')
)]
class HtmxDemoBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      'indicator' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Step 1 of 4'),
        '#attributes' => ['id' => 'htmxdemo-page-indicator'],
      ],
    ];
  }
}
