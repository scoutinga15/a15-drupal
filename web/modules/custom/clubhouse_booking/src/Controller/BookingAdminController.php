<?php

namespace Drupal\clubhouse_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller for the clubhouse booking admin interface.
 */
class BookingAdminController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new BookingAdminController.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Lists all slots for administration.
   */
  public function slotsList() {
    $header = [
      ['data' => $this->t('From Date'), 'field' => 'booking_date', 'sort' => 'asc'],
      ['data' => $this->t('To Date'), 'field' => 'to_date'],
      ['data' => $this->t('Status'), 'field' => 'status'],
      ['data' => $this->t('User Name')],
      ['data' => $this->t('Email')],
      ['data' => $this->t('Operations')],
    ];

    $query = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->extend('\Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header);

    // Filter by status if provided in the query string.
    $status_filter = \Drupal::request()->query->get('status');
    if ($status_filter !== NULL && $status_filter !== '') {
      $query->condition('status', $status_filter);
    }
    // Default filter by booked slots (booked and reserved) if no status filter is provided.
    elseif (!\Drupal::request()->query->has('status')) {
      $query->condition('status', ['booked', 'reserved'], 'IN');
    }

    // Default sort by upcoming dates ascending if no sort is specified.
    if (!\Drupal::request()->query->has('sort')) {
      $query->orderBy('booking_date', 'ASC');
    }

    $results = $query->execute()->fetchAll();

    $rows = [];
    foreach ($results as $row) {
      $links = [];
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('clubhouse_booking.edit_slot', ['slot_id' => $row->id]),
      ];
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('clubhouse_booking.delete_slot', ['slot_id' => $row->id]),
      ];

      $rows[] = [
        $row->booking_date,
        $row->to_date ?: '-',
        $this->t(ucfirst($row->status)),
        $row->user_name ?: '-',
        $row->user_email ?: '-',
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ],
      ];
    }

    $build['filter_form'] = $this->formBuilder()->getForm('\Drupal\clubhouse_booking\Form\BookingFilterForm');

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No slots found.'),
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Slot'),
      '#url' => Url::fromRoute('clubhouse_booking.add_slot'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];

    return $build;
  }

}
