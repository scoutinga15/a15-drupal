<?php

namespace Drupal\clubhouse_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new BookingController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $database, LanguageManagerInterface $language_manager) {
    $this->database = $database;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('language_manager')
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
        'drupalSettings' => [
          'clubhouseBooking' => [
            'language' => $this->languageManager->getCurrentLanguage()->getId(),
          ],
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
