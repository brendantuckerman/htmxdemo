<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows the latest database log entries.
 *
 * This card shows per-card access control: it only appears for accounts with
 * the 'access site reports' permission, and only when dblog is enabled.
 */
#[DashboardCard(
  id: 'recent_logs',
  label: new TranslatableMarkup('Recent log messages'),
  description: new TranslatableMarkup('The latest entries from the database log.'),
  size: 'wide',
  refreshOnContentChange: TRUE,
)]
class RecentLogs extends DashboardCardBase {

  /**
   * The number of log entries to list.
   */
  protected const int LIMIT = 6;

  /**
   * Constructs a RecentLogs card.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->moduleHandler->moduleExists('dblog'))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access site reports'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    $rows = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'type', 'message', 'variables', 'severity', 'timestamp'])
      ->orderBy('wid', 'DESC')
      ->range(0, self::LIMIT)
      ->execute()
      ->fetchAll();

    // Log entries carry no cache tags, so always read them fresh.
    $cache = ['max-age' => 0];

    if (!$rows) {
      return $this->buildEmpty($this->t('The log is empty.')) + ['#cache' => $cache];
    }

    $levels = RfcLogLevel::getLevels();
    $items = [];
    foreach ($rows as $row) {
      $level = (int) $row->severity;
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-log']],
        'severity' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $levels[$level] ?? $level,
          '#attributes' => [
            'class' => [
              'htmxdemo-pill',
              'htmxdemo-pill--' . ($level <= RfcLogLevel::ERROR ? 'danger' : ($level === RfcLogLevel::WARNING ? 'warning' : 'muted')),
            ],
          ],
        ],
        'message' => Link::fromTextAndUrl(
          $this->formatMessage($row),
          Url::fromRoute('dblog.event', ['event_id' => $row->wid]),
        )->toRenderable() + ['#attributes' => ['class' => ['htmxdemo-log__message']]],
        'meta' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('@type · @ago ago', [
            '@type' => $row->type,
            '@ago' => $this->dateFormatter->formatTimeDiffSince((int) $row->timestamp, ['granularity' => 1]),
          ]),
          '#attributes' => ['class' => ['htmxdemo-list__meta']],
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['htmxdemo-list', 'htmxdemo-list--logs']],
      '#cache' => $cache,
    ];
  }

  /**
   * Formats a log message as a short line of plain text.
   *
   * A simplified version of DbLogController::formatMessage(). Only scalar
   * variables are used, so no objects are unserialized.
   *
   * @param object $row
   *   A watchdog table row.
   *
   * @return string
   *   The message as plain text.
   */
  protected function formatMessage(object $row): string {
    $variables = @unserialize((string) $row->variables, ['allowed_classes' => FALSE]);
    $variables = is_array($variables) ? array_filter($variables, 'is_scalar') : [];
    $message = (string) new FormattableMarkup(Xss::filterAdmin((string) $row->message), $variables);
    return Unicode::truncate(trim(strip_tags($message)), 90, TRUE, TRUE);
  }

}
