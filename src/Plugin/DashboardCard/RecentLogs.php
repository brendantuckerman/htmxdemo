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
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Link;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shows the latest database log entries, filterable by severity.
 *
 * This card shows per-card access control: it only appears for accounts with
 * the 'access site reports' permission, and only when dblog is enabled.
 *
 * The filter chips request the whole card body but hx-select swaps only the
 * list. The URL in the address bar never changes: htmx does not push URLs
 * unless asked to with hx-push-url.
 */
#[DashboardCard(
  id: 'recent_logs',
  label: new TranslatableMarkup('Recent log messages'),
  description: new TranslatableMarkup('The latest entries from the database log, by severity.'),
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
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Connection $database,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly RequestStack $requestStack,
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
    $filters = $this->getFilters();
    $filter = (string) $this->requestStack->getCurrentRequest()?->query->get('severity', '');
    $filter = isset($filters[$filter]) ? $filter : '';
    $list_id = self::cardElementId($uuid) . '-logs';

    $build = [
      // Alpine tracks the pressed chip in the browser, as only the list is
      // swapped and the chips are never re-rendered.
      'chips' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['htmxdemo-chips'],
          'role' => 'group',
          'aria-label' => $this->t('Filter by severity'),
          'x-data' => '{ active: ' . json_encode($filter) . ' }',
        ],
      ],
      'list' => [
        '#type' => 'container',
        '#attributes' => ['id' => $list_id, 'class' => ['htmxdemo-logs']],
        'items' => $this->buildList($filters[$filter]['levels']),
      ],
      // Log entries carry no cache tags, so always read them fresh.
      '#cache' => ['max-age' => 0],
    ];

    foreach ($filters as $key => $definition) {
      $chip = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $definition['label'],
        '#attributes' => [
          'type' => 'button',
          'class' => ['htmxdemo-chip'],
          'x-on:click' => 'active = ' . json_encode($key),
          'x-bind:aria-pressed' => 'active === ' . json_encode($key),
          'aria-pressed' => $key === $filter ? 'true' : 'false',
        ],
      ];
      (new Htmx())
        ->get($this->bodyUrl($uuid))
        ->vals(['severity' => $key])
        ->target('#' . $list_id)
        ->select('#' . $list_id)
        ->swap('outerHTML')
        ->applyTo($chip);
      $build['chips'][$key ?: 'all'] = $chip;
    }

    return $build;
  }

  /**
   * Returns the severity filters.
   *
   * @return array<string, array{label: \Stringable, levels: int[]}>
   *   Filters keyed by query value. The empty key shows every level.
   */
  protected function getFilters(): array {
    return [
      '' => [
        'label' => $this->t('All'),
        'levels' => [],
      ],
      'error' => [
        'label' => $this->t('Errors'),
        'levels' => [RfcLogLevel::EMERGENCY, RfcLogLevel::ALERT, RfcLogLevel::CRITICAL, RfcLogLevel::ERROR],
      ],
      'warning' => [
        'label' => $this->t('Warnings'),
        'levels' => [RfcLogLevel::WARNING],
      ],
      'notice' => [
        'label' => $this->t('Notices'),
        'levels' => [RfcLogLevel::NOTICE, RfcLogLevel::INFO],
      ],
    ];
  }

  /**
   * Builds the list of log entries.
   *
   * @param int[] $levels
   *   The severity levels to show, or an empty array for every level.
   *
   * @return array
   *   A render array.
   */
  protected function buildList(array $levels): array {
    $query = $this->database->select('watchdog', 'w')
      ->fields('w', ['wid', 'type', 'message', 'variables', 'severity', 'timestamp'])
      ->orderBy('wid', 'DESC')
      ->range(0, self::LIMIT);
    if ($levels) {
      $query->condition('severity', $levels, 'IN');
    }
    $rows = $query->execute()->fetchAll();

    if (!$rows) {
      return $this->buildEmpty($this->t('No matching log messages.'));
    }

    $level_labels = RfcLogLevel::getLevels();
    $items = [];
    foreach ($rows as $row) {
      $level = (int) $row->severity;
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-log']],
        'severity' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $level_labels[$level] ?? $level,
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
