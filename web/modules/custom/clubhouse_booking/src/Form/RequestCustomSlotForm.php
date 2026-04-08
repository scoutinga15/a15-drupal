<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a form for requesting custom slots.
 */
class RequestCustomSlotForm extends FormBase {

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
   * Constructs a new RequestCustomSlotForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(Connection $database, MailManagerInterface $mail_manager) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_request_custom_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $default_start = $request->query->get('start');
    $default_end = $request->query->get('end');

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

    $form['booking_date'] = [
      '#type' => 'date',
      '#title' => $this->t('From date'),
      '#required' => TRUE,
      '#default_value' => $default_start,
    ];

    $form['to_date'] = [
      '#type' => 'date',
      '#title' => $this->t('To date'),
      '#required' => TRUE,
      '#default_value' => $default_end,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extra information'),
      '#description' => $this->t('Why do you need this date?'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $from = $form_state->getValue('booking_date');
    $to = $form_state->getValue('to_date');
    if ($from && strtotime($from) < strtotime(date('Y-m-d'))) {
      $form_state->setErrorByName('booking_date', $this->t('Requested date cannot be in the past.'));
    }
    if ($from && $to && strtotime($to) < strtotime($from)) {
      $form_state->setErrorByName('to_date', $this->t('End date cannot be before start date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $from = $form_state->getValue('booking_date');
    $to = $form_state->getValue('to_date');
    $message_text = $form_state->getValue('message');

    // Save as a "requested" slot range.
    $this->database->insert('clubhouse_slots')
      ->fields([
        'booking_date' => $from,
        'to_date' => $to,
        'status' => 'requested',
        'user_name' => $name,
        'user_email' => $email,
        'message' => $message_text,
        'created' => time(),
      ])
      ->execute();

    // Notify the admin.
    $admin_email = 'verhuur@scoutinga15.nl';
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $params = [
      'user_name' => $name,
      'user_email' => $email,
      'date' => $from . ($to && $to != $from ? ' - ' . $to : ''),
      'message' => $message_text,
    ];

    $this->mailManager->mail('clubhouse_booking', 'custom_slot_request', $admin_email, $langcode, $params);

    $this->messenger()->addStatus($this->t('Your request for another date has been sent to the administrator.'));
    $form_state->setRedirect('clubhouse_booking.calendar');
  }

}
