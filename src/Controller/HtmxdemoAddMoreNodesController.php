<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

// @see https://www.hashbangcode.com/article/drupal-11-building-load-more-feature-paginating-nodes-using-htmx

class HtmxdemoAddMoreNodesController extends ControllerBase {

  use HtmxRequestInfoTrait;

  public function __construct(
    protected RequestStack $requestStack,
    protected Connection $database,
  )
  {}

  /**
   * {@inheritdoc}
   */
  protected function getRequest()
  {
    return $this->requestStack->getCurrentRequest();
  }

  public function action() {

    if ($this->isHtmxRequest()) {
      $page = $this->getRequest()->query->get('page');
    }
    else {
      $page = 0;
    }

    $pageLimit = 2;

    $nodeStorage = $this->entityTypeManager()->getStorage('node');

    $cacheTags = ['node_list', 'node_view'];

    $output = [];

    //Db query
    $query = $this->database->select('node', 'n')->fields('n', ['nid']);
    $query->join('node_field_data', 'nfd', '[nfd].[nid] = [n].[nid] AND [nfd].[langcode] = [n].[langcode]');
    $query->orderBy('nfd.created', 'desc');
    $query->where('n.type = :type', [':type' => 'article']);

    //Add pager to the query
    $query = $query->extend(PagerSelectExtender::class);
    $query->limit($pageLimit);

    $query->setCountQuery($query->countQuery());
    $queryResult = $query->execute();

    $results = $queryResult->fetchAll();
    $totalItems = $query->getCountQuery()->execute()->fetchField();

    //Generate output
    foreach ($results as $id => $result) {

      $node = $nodeStorage->load($result->nid);

      //Render teasers
      $entityType = 'node';
      $viewMode = 'teaser';

      $viewBuilder = $this->entityTypeManager()->getViewBuilder($entityType);
      $output['node-' . $node->id()] = $viewBuilder->view($node, $viewMode);

      //Merge cache tags
      $cacheTags = Cache::mergeTags($cacheTags, $node->getCacheTags());

      if ($id == $pageLimit - 1 && $page * $pageLimit < $totalItems) {
        $output['add_more_nodes'] = [
          '#type' => 'link',
          '#url' => Url::fromRoute('<current>'),
          '#title' => 'Load more...',
        ];

        $htmx = new Htmx();
        $htmx->get(Url::fromRoute(route_name: 'htmxdemo.add_more_nodes', options: [
          'query' => [
            'page' => ++$page,
            '_wrapper_format' => 'drupal_htmx',

          ]
        ]))
        ->swap('outerHTML')
        ->trigger('click once')
        ->applyTo($output['add_more_nodes']);
      }

    }

      // Set up the cache for this request.
    $output['#cache'] = [
      'contexts' => [
        'url:path',
        'url:path.query',
      ],
      'tags' => $cacheTags,
    ];

    return $output;

  }


}
