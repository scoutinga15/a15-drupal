<?php

namespace Drupal\clubhouse_booking\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Provides a 'Free Slot List' block.
 *
 * @Block(
 *   id = "clubhouse_booking_free_slot_list_block",
 *   admin_label = @Translation("Clubhouse Free Slot List"),
 *   category = @Translation("Custom")
 * )
 */
class FreeSlotListBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new FreeSlotListBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, DateFormatterInterface $date_formatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $today = date('Y-m-d');
    $query = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('status', 'free')
      ->condition('booking_date', $today, '>=')
      ->orderBy('booking_date', 'ASC');

    $results = $query->execute()->fetchAll();

    if (empty($results)) {
      return [
        '#markup' => $this->t('Geen vrije momenten gevonden in de toekomst.'),
      ];
    }

    $grouped_slots = [];
    foreach ($results as $slot) {
      $timestamp = strtotime($slot->booking_date);
      $month = $this->dateFormatter->format($timestamp, 'custom', 'F Y');
      $grouped_slots[$month][] = $slot;
    }

    $items = [];
    foreach ($grouped_slots as $month => $slots) {
      $month_items = [];
      foreach ($slots as $slot) {
        $start_timestamp = strtotime($slot->booking_date);
        $date_string = $this->dateFormatter->format($start_timestamp, 'custom', 'j F Y');

        if ($slot->to_date && $slot->to_date != $slot->booking_date) {
          $end_timestamp = strtotime($slot->to_date);
          $date_string = $this->t('@start t/m @end', [
            '@start' => $this->dateFormatter->format($start_timestamp, 'custom', 'j F Y'),
            '@end' => $this->dateFormatter->format($end_timestamp, 'custom', 'j F Y'),
          ]);
        }
        $month_items[] = [
          '#markup' => $date_string,
        ];
      }
      $items[] = [
        '#markup' => '<strong>' . $month . '</strong>',
        'children' => [
          '#theme' => 'item_list',
          '#items' => $month_items,
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Aankomende beschikbare data (afwijkingen aan te vragen)'),
    ];
  }

}
