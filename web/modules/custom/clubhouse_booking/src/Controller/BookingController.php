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
      $events[] = [
        'id' => $slot->id,
        'title' => $slot->is_booked ? $this->t('Booked') : $this->t('Free Slot'),
        'from' => date('c', $slot->start_time),
        'to' => date('c', $slot->end_time),
        'color' => $slot->is_booked ? '#ff0000' : '#00ff00',
        'is_booked' => (bool) $slot->is_booked,
      ];
    }

    return new JsonResponse($events);
  }

}
