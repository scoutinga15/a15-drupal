<?php

namespace Drupal\clubhouse_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the clubhouse booking system.
 */
class BookingController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new BookingController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
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
   * Renders the calendar page.
   */
  public function calendar() {
    return [
      '#theme' => 'clubhouse_booking_calendar',
      '#attached' => [
        'library' => [
          'clubhouse_booking/booking_calendar',
        ],
      ],
    ];
  }

  /**
   * API endpoint to get slots.
   */
  public function getSlots() {
    $slots = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->execute()
      ->fetchAll();

    $events = [];
    foreach ($slots as $slot) {
      $color = '#00ff00'; // Default free
      $title = $this->t('Free Slot');

      switch ($slot->status) {
        case 'requested':
          $color = '#ffff00';
          $title = $this->t('Requested');
          break;
        case 'reserved':
          $color = '#ffa500';
          $title = $this->t('Reserved');
          break;
        case 'booked':
          $color = '#ff0000';
          $title = $this->t('Booked');
          break;
      }

      $to_date = $slot->to_date ?: $slot->booking_date;
      // FullCalendar expects 'start'/'end' and allDay events use exclusive end date.
      $end_date = date('Y-m-d', strtotime($to_date . ' +1 day'));
      $events[] = [
        'id' => $slot->id,
        'title' => $title,
        'start' => $slot->booking_date,
        'end' => $end_date,
        'allDay' => TRUE,
        'color' => $color,
        'extendedProps' => [
          'status' => $slot->status,
        ],
      ];
    }

    return new JsonResponse($events);
  }

}
