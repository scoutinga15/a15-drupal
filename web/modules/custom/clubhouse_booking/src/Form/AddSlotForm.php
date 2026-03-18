<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides an admin form to add slots.
 */
class AddSlotForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new AddSlotForm.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_add_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['start_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start Time'),
      '#required' => TRUE,
    ];

    $form['end_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Time'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Slot'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('start_time');
    $end = $form_state->getValue('end_time');

    if ($start instanceof DrupalDateTime && $end instanceof DrupalDateTime) {
      if ($start->getTimestamp() >= $end->getTimestamp()) {
        $form_state->setErrorByName('end_time', $this->t('End time must be after start time.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('start_time')->getTimestamp();
    $end = $form_state->getValue('end_time')->getTimestamp();

    $this->database->insert('clubhouse_slots')
      ->fields([
        'start_time' => $start,
        'end_time' => $end,
        'is_booked' => 0,
      ])
      ->execute();

    $this->messenger()->addStatus($this->t('Free slot added.'));
  }

}
