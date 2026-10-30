<?php

declare(strict_types=1);

namespace Drupal\htmxdemo;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Stores each user's dashboard layout in the private tempstore.
 *
 * A layout is an ordered array of card plugin IDs keyed by card UUID. The
 * UUID identifies a card instance, so the same card type can be added twice.
 */
class DashboardLayoutStorage {

  /**
   * The tempstore collection name.
   */
  protected const string COLLECTION = 'htmxdemo_dashboard';

  /**
   * The tempstore key holding the layout.
   */
  protected const string KEY = 'layout';

  /**
   * Constructs a DashboardLayoutStorage object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private tempstore factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   */
  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly UuidInterface $uuid,
  ) {}

  /**
   * Loads the current user's layout.
   *
   * @return array<string, string>|null
   *   Plugin IDs keyed by card UUID, or NULL if no layout was ever saved. An
   *   empty array means the user removed every card.
   */
  public function load(): ?array {
    return $this->store()->get(self::KEY);
  }

  /**
   * Saves the current user's layout.
   *
   * @param array<string, string> $layout
   *   Plugin IDs keyed by card UUID, in display order.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be written.
   */
  public function save(array $layout): void {
    $this->store()->set(self::KEY, $layout);
  }

  /**
   * Returns the plugin ID of a card on the current user's dashboard.
   *
   * @param string $uuid
   *   The card UUID.
   *
   * @return string|null
   *   The plugin ID, or NULL if the card is not on the dashboard.
   */
  public function getPluginId(string $uuid): ?string {
    return $this->load()[$uuid] ?? NULL;
  }

  /**
   * Adds a card to the end of the current user's dashboard.
   *
   * @param string $plugin_id
   *   The card plugin ID.
   *
   * @return string
   *   The UUID of the new card.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be written.
   */
  public function add(string $plugin_id): string {
    $uuid = $this->uuid->generate();
    $layout = $this->load() ?? [];
    $layout[$uuid] = $plugin_id;
    $this->save($layout);
    return $uuid;
  }

  /**
   * Removes a card from the current user's dashboard.
   *
   * @param string $uuid
   *   The card UUID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be written.
   */
  public function remove(string $uuid): void {
    $layout = $this->load() ?? [];
    unset($layout[$uuid]);
    $this->save($layout);
  }

  /**
   * Reorders the current user's dashboard.
   *
   * UUIDs not on the dashboard are ignored. Cards missing from the list keep
   * their place at the end, so a stale request cannot drop cards.
   *
   * @param string[] $uuids
   *   Card UUIDs in their new display order.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   Thrown when the layout cannot be written.
   */
  public function reorder(array $uuids): void {
    $layout = $this->load() ?? [];
    $sorted = array_intersect_key(array_flip($uuids), $layout);
    $this->save(array_replace($sorted, $layout));
  }

  /**
   * Returns the tempstore for this collection.
   */
  protected function store(): PrivateTempStore {
    return $this->tempStoreFactory->get(self::COLLECTION);
  }

}
