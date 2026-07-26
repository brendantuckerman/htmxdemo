<?php

namespace Drupal\htmxdemo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: 'htmxdemo-shipping-block',
  admin_label: new TranslatableMarkup('HtmxDemo Shipping Block')
)]
class HtmxdemoShippingBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      'shippingCost' => [
        '#theme' => 'htmxdemo_shipping_calculator',
        '#total' => NULL,
        '#service' => NULL,
      ],
    ];
  }
}
