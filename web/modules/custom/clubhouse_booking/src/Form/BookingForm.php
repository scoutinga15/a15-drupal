<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Provides a booking form.
 */
class BookingForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new BookingForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $database, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_booking_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $slot_id = NULL) {
    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $slot_id)
      ->execute()
      ->fetchObject();

    if (!$slot || $slot->status !== 'free') {
      throw new NotFoundHttpException();
    }

    $form['slot_id'] = [
      '#type' => 'value',
      '#value' => $slot_id,
    ];

    $date_string = $slot->booking_date;
    if ($slot->to_date && $slot->to_date != $slot->booking_date) {
      $date_string .= ' - ' . $slot->to_date;
    }

    $form['slot_info'] = [
      '#markup' => '<p>' . $this->t('Booking for: @date', [
        '@date' => $date_string,
      ]) . '</p>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm booking'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $slot_id = $form_state->getValue('slot_id');
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');

    $this->database->update('clubhouse_slots')
      ->fields([
        'user_name' => $name,
        'user_email' => $email,
        'status' => 'reserved',
        'created' => time(),
      ])
      ->condition('id', $slot_id)
      ->execute();

    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $slot_id)
      ->execute()
      ->fetchObject();

    // Send email notification to user.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $date_string = $slot->booking_date;
    if ($slot->to_date && $slot->to_date != $slot->booking_date) {
      $date_string .= ' - ' . $slot->to_date;
    }

    $params = [
      'user_name' => $name,
      'date' => $date_string,
      'cancel_url' => Url::fromRoute('clubhouse_booking.cancel', ['slot_id' => $slot_id], ['absolute' => TRUE])->toString(),
    ];
    $this->mailManager->mail('clubhouse_booking', 'booking_confirmation', $email, $langcode, $params);

    // Notify the admin.
    $admin_email = 'verhuur@scoutinga15.nl';
    $this->mailManager->mail('clubhouse_booking', 'admin_booking_notification', $admin_email, $langcode, $params);

    $this->messenger()->addStatus($this->t('Your booking has been confirmed!'));
    $form_state->setRedirect('clubhouse_booking.calendar');
  }

}
