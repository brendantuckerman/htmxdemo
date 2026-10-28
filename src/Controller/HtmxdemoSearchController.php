<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the search response for the query search demo.
 */
class HtmxdemoSearchController extends ControllerBase {

  /**
   * Shows how the search request arrived.
   *
   * GET sends the search term in the query string. QUERY sends it in the
   * request body, which Symfony does not parse for this method.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array.
   */
  public function search(Request $request): array {
    parse_str($request->getContent(), $body);

    if ($request->query->has('search')) {
      $term = (string) $request->query->get('search');
      $foundIn = $this->t('the query string');
    }
    else {
      $term = (string) ($body['search'] ?? '');
      $foundIn = $this->t('the request body');
    }

    $rows = [
      [$this->t('Method'), $request->getMethod()],
      [$this->t('URL'), $request->getRequestUri()],
      [$this->t('Query string'), $request->getQueryString() ?: '(none)'],
      [$this->t('Body'), $request->getContent() ?: '(empty)'],
    ];
    foreach ($rows as &$row) {
      $row = [$row[0], ['data' => ['#plain_text' => (string) $row[1]]]];
    }

    return [
      // The output depends on the method, query string and body, which are not
      // cache contexts, so a cached copy would be served for every request.
      '#cache' => ['max-age' => 0],
      'summary' => [
        '#markup' => '<p>' . $this->t('Searched for "@term" using @method, found in @found.', [
          '@term' => $term,
          '@method' => $request->getMethod(),
          '@found' => $foundIn,
        ]) . '</p>',
      ],
      'request' => [
        '#type' => 'table',
        '#rows' => $rows,
      ],
    ];
  }

}
