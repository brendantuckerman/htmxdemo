<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows the server time and site status, polled by htmx.
 */
#[DashboardCard(
  id: 'site_status',
  label: new TranslatableMarkup('Site status'),
  description: new TranslatableMarkup('Server time and cron status, refreshed every 15 seconds.'),
  poll: '1s',
)]
class SiteStatus extends DashboardCardBase {

  /**
   * Constructs a SiteStatus card.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TimeInterface $time,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly StateInterface $state,
    protected readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    $now = $this->time->getCurrentTime();
    $cron_last = (int) $this->state->get('system.cron_last', 0);

    $rows = [
      [$this->t('Cron last ran'), $cron_last
        ? $this->t('@ago ago', ['@ago' => $this->dateFormatter->formatDiff($cron_last, $now, ['granularity' => 1])])
        : $this->t('Never'),
      ],
    ];
    // Version numbers help attackers, so only show them to site admins.
    if ($this->currentUser->hasPermission('access site reports')) {
      $rows[] = [$this->t('Drupal'), \Drupal::VERSION];
      $rows[] = [$this->t('PHP'), PHP_VERSION];
    }

    $details = [];
    foreach ($rows as $i => [$term, $value]) {
      $details[$i] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-details__row']],
        'term' => ['#type' => 'html_tag', '#tag' => 'dt', '#value' => $term],
        'value' => ['#type' => 'html_tag', '#tag' => 'dd', '#value' => $value],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-status']],
      'time' => [
        '#type' => 'html_tag',
        '#tag' => 'time',
        '#value' => $this->dateFormatter->format($now, 'custom', 'H:i:s'),
        '#attributes' => [
          'class' => ['htmxdemo-stat__value'],
          'datetime' => $this->dateFormatter->format($now, 'custom', 'c'),
        ],
      ],
      'live' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Server time · live'),
        '#attributes' => ['class' => ['htmxdemo-stat__label', 'htmxdemo-live']],
      ],
      'details' => [
        '#type' => 'html_tag',
        '#tag' => 'dl',
        '#attributes' => ['class' => ['htmxdemo-details']],
        'rows' => $details,
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
