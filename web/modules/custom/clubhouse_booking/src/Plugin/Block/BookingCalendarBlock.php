<?php

namespace Drupal\clubhouse_booking\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Clubhouse Booking Calendar' block.
 *
 * @Block(
 *   id = "clubhouse_booking_calendar_block",
 *   admin_label = @Translation("Clubhouse Booking Calendar"),
 *   category = @Translation("Custom")
 * )
 */
class BookingCalendarBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'clubhouse_booking_calendar',
      '#attached' => [
        'library' => [
          'clubhouse_booking/booking_calendar',
        ],
        'drupalSettings' => [
          'clubhouseBooking' => [
            'language' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
          ],
        ],
      ],
    ];
  }

}
