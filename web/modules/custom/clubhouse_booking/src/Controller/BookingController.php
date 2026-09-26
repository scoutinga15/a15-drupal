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

  /**
   * API endpoint to get Dutch holidays.
   */
  public function getHolidays() {
    $year = \Drupal::request()->query->get('year') ?: date('Y');
    $holidays = $this->calculateDutchHolidays($year);

    $events = [];
    foreach ($holidays as $date => $name) {
      $events[] = [
        'title' => $name,
        'start' => $date,
        'allDay' => TRUE,
        'display' => 'background',
        'color' => 'orange',
        'textColor' => '#fff',
        'classNames' => ['dutch-holiday'],
      ];
    }

    return new JsonResponse($events);
  }

  /**
   * Calculates Dutch holidays for a given year.
   */
  protected function calculateDutchHolidays($year) {
    $holidays = [];

    // Fixed dates
    $holidays["$year-01-01"] = $this->t('Nieuwjaarsdag');
    $holidays["$year-04-27"] = $this->t('Koningsdag');
    $holidays["$year-05-05"] = $this->t('Bevrijdingsdag');
    $holidays["$year-12-25"] = $this->t('Eerste Kerstdag');
    $holidays["$year-12-26"] = $this->t('Tweede Kerstdag');

    // Easter-related dates
    $easter_timestamp = $this->getEasterDate($year);

    $format = 'Y-m-d';
    $good_friday = date($format, strtotime('-2 days', $easter_timestamp));
    $easter_sunday = date($format, $easter_timestamp);
    $easter_monday = date($format, strtotime('+1 day', $easter_timestamp));
    $ascension_day = date($format, strtotime('+39 days', $easter_timestamp));
    $whit_sunday = date($format, strtotime('+49 days', $easter_timestamp));
    $whit_monday = date($format, strtotime('+50 days', $easter_timestamp));

    $holidays[$good_friday] = $this->t('Goede Vrijdag');
    $holidays[$easter_sunday] = $this->t('Eerste Paasdag');
    $holidays[$easter_monday] = $this->t('Tweede Paasdag');
    $holidays[$ascension_day] = $this->t('Hemelvaartsdag');
    $holidays[$whit_sunday] = $this->t('Eerste Pinksterdag');
    $holidays[$whit_monday] = $this->t('Tweede Pinksterdag');

    ksort($holidays);
    return $holidays;
  }

  /**
   * Calculates the Unix timestamp for Easter Sunday for a given year.
   *
   * Fallback for easter_date() which requires the 'calendar' PHP extension.
   */
  protected function getEasterDate($year) {
    if (function_exists('easter_date')) {
      return easter_date($year);
    }

    // Gauss's algorithm for calculating Easter.
    $a = $year % 19;
    $b = floor($year / 100);
    $c = $year % 100;
    $d = floor($b / 4);
    $e = $b % 4;
    $f = floor(($b + 8) / 25);
    $g = floor(($b - $f + 1) / 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = floor($c / 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = floor(($a + 11 * $h + 22 * $l) / 451);
    $month = floor(($h + $l - 7 * $m + 114) / 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;

    return mktime(0, 0, 0, $month, $day, $year);
  }

}
