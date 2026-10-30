<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\htmxdemo\DashboardCardBase;
use Drupal\htmxdemo\DashboardCardInterface;
use Drupal\htmxdemo\DashboardCardManager;
use Drupal\htmxdemo\DashboardLayoutStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard of draggable cards, built with htmx and Alpine.js.
 *
 * - htmx adds, loads, refreshes and dismisses cards, and saves their order.
 * - Alpine.js handles client-only state: dragging and collapsing cards.
 *
 * Alpine reports a finished drag by dispatching a 'dashboard-sorted' event,
 * which htmx uses as the trigger for saving the new order.
 */
class HtmxdemoDashboardController extends ControllerBase {

  /**
   * The cards a user sees the first time they open the dashboard.
   */
  protected const array DEFAULT_CARDS = [
    'node_count',
    'recent_content',
    'site_status',
    'latest_node',
  ];

  /**
   * The ID of the grid element that holds the cards.
   */
  protected const string GRID_ID = 'htmxdemo-dashboard-grid';

  /**
   * The ID of the card type select, swapped out-of-band when it changes.
   */
  protected const string SELECT_ID = 'htmxdemo-dashboard-card-type';

  /**
   * Constructs a HtmxdemoDashboardController object.
   *
   * @param \Drupal\htmxdemo\DashboardCardManager $cardManager
   *   The dashboard card plugin manager.
   * @param \Drupal\htmxdemo\DashboardLayoutStorage $layoutStorage
   *   The dashboard layout storage.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator.
   */
  public function __construct(
    protected readonly DashboardCardManager $cardManager,
    protected readonly DashboardLayoutStorage $layoutStorage,
    protected readonly CsrfTokenGenerator $csrfToken,
  ) {}

  /**
   * Builds the dashboard page.
   *
   * @return array
   *   A render array.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the default layout cannot be saved.
   */
  public function page(): array {
    $layout = $this->layoutStorage->load();
    if ($layout === NULL) {
      $layout = $this->seedLayout();
    }

    $cards = [];
    foreach ($layout as $uuid => $plugin_id) {
      $card = $this->loadCard($plugin_id);
      if ($card) {
        $cards[$uuid] = $this->buildCard($uuid, $card);
      }
    }

    $build = [
      '#theme' => 'htmxdemo_dashboard',
      '#cards' => $cards,
      '#card_type_select' => $this->buildCardTypeSelect($layout),
      '#attributes' => new Attribute(['class' => ['htmxdemo-dashboard']]),
      '#add_attributes' => new Attribute(['class' => ['htmxdemo-dashboard__add']]),
      '#grid_attributes' => new Attribute([
        'id' => self::GRID_ID,
        'class' => ['htmxdemo-dashboard__grid'],
      ]),
      '#attached' => ['library' => ['htmxdemo/dashboard']],
      // The layout is per user and changes with every request, so the page
      // must never be cached.
      '#cache' => ['max-age' => 0],
    ];

    // Every htmx request inside the dashboard inherits this header, which
    // the _csrf_request_header_token route requirement checks. Core's REST
    // module protects cookie sessions in the same way.
    $this->applyHtmx($build, '#attributes', (new Htmx())
      ->headers(['X-CSRF-Token' => $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY)], Htmx::INHERITED));

    // Adding a card appends the returned card shell to the grid.
    $this->applyHtmx($build, '#add_attributes', (new Htmx())
      ->post(Url::fromRoute('htmxdemo.dashboard.card_add'))
      ->target('#' . self::GRID_ID)
      ->swap('beforeend'));

    // Alpine dispatches 'dashboard-sorted' from the grid after a drag. A POST
    // from a non-form element sends the inputs inside it, which includes the
    // hidden order[] input in every card, in their new order.
    $this->applyHtmx($build, '#grid_attributes', (new Htmx())
      ->post(Url::fromRoute('htmxdemo.dashboard.sort'))
      ->trigger('dashboard-sorted')
      ->swap('none'));

    return $build;
  }

  /**
   * Adds a card to the dashboard and returns its shell.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array holding the new card and the refreshed card type select.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be saved.
   */
  public function addCard(Request $request): array {
    $plugin_id = (string) $request->request->get('card_type', '');
    $layout = $this->layoutStorage->load() ?? [];

    $build = ['#cache' => ['max-age' => 0]];

    // Only card types still offered in the select may be added. Anything
    // else, such as a single-instance card added from a second tab, gets the
    // select refreshed and nothing swapped into the grid.
    if (!array_key_exists($plugin_id, $this->cardManager->getAddableOptions($this->currentUser(), $layout))) {
      $build['select'] = $this->buildCardTypeSelect($layout, TRUE);
      $this->applyHtmx($build, '#attached', (new Htmx())->reswapHeader('none'));
      return $build;
    }

    $uuid = $this->layoutStorage->add($plugin_id);
    $layout[$uuid] = $plugin_id;

    $build['card'] = $this->buildCard($uuid, $this->loadCard($plugin_id));
    $build['select'] = $this->buildCardTypeSelect($layout, TRUE);
    return $build;
  }

  /**
   * Builds the body of a card.
   *
   * Accepts POST as well as GET, so forms inside a card can submit back to
   * the card that rendered them.
   *
   * @param string $uuid
   *   The card UUID.
   *
   * @return array
   *   A render array for the card body.
   */
  public function cardBody(string $uuid): array {
    $plugin_id = $this->layoutStorage->getPluginId($uuid);
    $card = $plugin_id ? $this->loadCard($plugin_id) : NULL;

    if (!$card) {
      // A stale card, such as one dismissed in another tab. Say so inside
      // the card instead of swapping an error page into it.
      return [
        '#markup' => '<p class="htmxdemo-card__empty">' . $this->t('This card is no longer available. Dismiss it to tidy up.') . '</p>',
        '#cache' => ['max-age' => 0],
      ];
    }
    return $card->build($uuid);
  }

  /**
   * Removes a card from the dashboard.
   *
   * @param string $uuid
   *   The card UUID.
   *
   * @return array
   *   A render array holding only the refreshed card type select. The card
   *   itself is swapped with the empty main content, which removes it.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be saved.
   */
  public function deleteCard(string $uuid): array {
    $this->layoutStorage->remove($uuid);
    return [
      'select' => $this->buildCardTypeSelect($this->layoutStorage->load() ?? [], TRUE),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Saves the order of the cards after a drag.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An empty response, as nothing on the page needs to change.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be saved.
   */
  public function sort(Request $request): Response {
    $order = $request->request->all('order');
    $this->layoutStorage->reorder(array_filter($order, 'is_string'));
    return new Response('', Response::HTTP_NO_CONTENT);
  }

  /**
   * Saves the default cards as the user's first layout.
   *
   * Saving straight away also starts a session for anonymous users, so the
   * layout survives a reload.
   *
   * @return array<string, string>
   *   The new layout.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be saved.
   */
  protected function seedLayout(): array {
    $this->layoutStorage->save([]);
    foreach (self::DEFAULT_CARDS as $plugin_id) {
      if ($this->loadCard($plugin_id)) {
        $this->layoutStorage->add($plugin_id);
      }
    }
    return $this->layoutStorage->load() ?? [];
  }

  /**
   * Creates a card plugin, if it exists and the current user may see it.
   *
   * @param string $plugin_id
   *   The card plugin ID.
   *
   * @return \Drupal\htmxdemo\DashboardCardInterface|null
   *   The card, or NULL if it is missing or not accessible.
   */
  protected function loadCard(string $plugin_id): ?DashboardCardInterface {
    if (!$this->cardManager->hasDefinition($plugin_id)) {
      return NULL;
    }
    /** @var \Drupal\htmxdemo\DashboardCardInterface $card */
    $card = $this->cardManager->createInstance($plugin_id);
    return $card->access($this->currentUser())->isAllowed() ? $card : NULL;
  }

  /**
   * Builds the shell of a card: its header, buttons and lazy-loaded body.
   *
   * @param string $uuid
   *   The card UUID.
   * @param \Drupal\htmxdemo\DashboardCardInterface $card
   *   The card plugin.
   *
   * @return array
   *   A render array.
   */
  protected function buildCard(string $uuid, DashboardCardInterface $card): array {
    $card_id = DashboardCardBase::cardElementId($uuid);
    $body_id = DashboardCardBase::bodyElementId($uuid);
    $body_url = Url::fromRoute('htmxdemo.dashboard.card_body', ['uuid' => $uuid]);

    $build = [
      '#theme' => 'htmxdemo_dashboard_card',
      '#uuid' => $uuid,
      '#label' => $card->label(),
      '#description' => $card->description(),
      '#attributes' => new Attribute([
        'id' => $card_id,
        'class' => ['htmxdemo-card', 'htmxdemo-card--' . $card->getSize()],
        'data-card-type' => $card->getPluginId(),
      ]),
      '#body_attributes' => new Attribute([
        'id' => $body_id,
        'class' => ['htmxdemo-card__body'],
      ]),
      '#refresh_attributes' => new Attribute(),
      '#dismiss_attributes' => new Attribute(),
    ];

    // The body loads itself, and may also poll or listen for events. While
    // a request is in flight htmx adds .htmx-request to the card, which the
    // CSS uses to show a loading state.
    $this->applyHtmx($build, '#body_attributes', (new Htmx())
      ->get($body_url)
      ->trigger($card->getTrigger())
      ->indicator('#' . $card_id)
      ->swap('innerHTML'));

    $this->applyHtmx($build, '#refresh_attributes', (new Htmx())
      ->get($body_url)
      ->target('#' . $body_id)
      ->indicator('#' . $card_id)
      ->swap('innerHTML'));

    // The empty response replaces the card, removing it. The swap delay
    // gives the CSS fade-out on .htmx-swapping time to run.
    $this->applyHtmx($build, '#dismiss_attributes', (new Htmx())
      ->delete(Url::fromRoute('htmxdemo.dashboard.card_delete', ['uuid' => $uuid]))
      ->confirm((string) $this->t('Remove the “@label” card from your dashboard?', ['@label' => $card->label()]))
      ->target('#' . $card_id)
      ->swap('outerHTML', 'swap:250ms'));

    return $build;
  }

  /**
   * Builds the select listing the card types that can be added.
   *
   * @param array<string, string> $layout
   *   The current layout.
   * @param bool $oob
   *   (optional) Whether to mark the select for an out-of-band swap.
   *
   * @return array
   *   A render array.
   */
  protected function buildCardTypeSelect(array $layout, bool $oob = FALSE): array {
    $options = $this->cardManager->getAddableOptions($this->currentUser(), $layout);

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'select',
      '#attributes' => [
        'id' => self::SELECT_ID,
        'name' => 'card_type',
        'required' => 'required',
        'class' => ['htmxdemo-input', 'htmxdemo-select'],
      ],
      'placeholder' => [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#value' => $options ? $this->t('Choose a card…') : $this->t('Every card is on the dashboard'),
        '#attributes' => ['value' => ''],
      ],
    ];
    foreach ($options as $plugin_id => $label) {
      $build[$plugin_id] = [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#value' => $label,
        '#attributes' => ['value' => $plugin_id],
      ];
    }

    if (!$options) {
      $build['#attributes']['disabled'] = 'disabled';
    }
    if ($oob) {
      (new Htmx())->swapOob(TRUE)->applyTo($build);
    }
    return $build;
  }

  /**
   * Applies htmx attributes or headers to part of a render array.
   *
   * Htmx::applyTo() writes to one attribute key, but the dashboard templates
   * take several Attribute objects. This applies the htmx values to the given
   * key and keeps the cache metadata and attachments on the element itself.
   *
   * @param array $build
   *   The render array.
   * @param string $key
   *   The key holding the Attribute object to update, or '#attached' when
   *   only response headers are being set.
   * @param \Drupal\Core\Htmx\Htmx $htmx
   *   The htmx values to apply.
   */
  protected function applyHtmx(array &$build, string $key, Htmx $htmx): void {
    $element = $key === '#attached' ? [] : ['#attributes' => $build[$key]];
    $htmx->applyTo($element);
    if ($key !== '#attached') {
      $build[$key] = $element['#attributes'];
    }
    BubbleableMetadata::createFromRenderArray($build)
      ->merge(BubbleableMetadata::createFromRenderArray($element))
      ->applyTo($build);
  }

}
