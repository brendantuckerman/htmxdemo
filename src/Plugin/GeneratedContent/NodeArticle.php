<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\GeneratedContent;

use Drupal\Core\Link;
use Drupal\generated_content\Attribute\GeneratedContent;
use Drupal\generated_content\Plugin\GeneratedContent\GeneratedContentPluginBase;
use Drupal\node\Entity\Node;

/**
 * Generates article node entities for the htmx demos.
 */
#[GeneratedContent(id: 'htmxdemo_node_article', entity_type: 'node', bundle: 'article', weight: 10)]
class NodeArticle extends GeneratedContentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): array {
    $total_nodes_count = 50;

    $nodes = [];

    for ($i = 0; $i < $total_nodes_count; $i++) {
      $image = $this->helper::createFile('png', [
        'filename' => 'htmxdemo_article_' . ($i + 1),
        'width' => 800,
        'height' => 450,
      ]);

      $node = Node::create([
        'type' => 'article',
        'title' => sprintf('Generated article %d', $i + 1),
        'status' => 1,
        'body' => [
          'value' => $this->helper::randomRichText(),
          'format' => 'full_html',
        ],
        'field_main_image' => [
          'target_id' => $image->id(),
          'alt' => sprintf('Generated image for article %d', $i + 1),
        ],
      ]);

      $node->save();

      $this->helper::log(
        'Created "%s" node "%s" [ID: %s] %s',
        $node->bundle(),
        $node->toLink()->toString(),
        $node->id(),
        Link::createFromRoute('Edit', 'entity.node.edit_form', ['node' => $node->id()])->toString()
      );

      $nodes[] = $node;
    }

    return $nodes;
  }

}
