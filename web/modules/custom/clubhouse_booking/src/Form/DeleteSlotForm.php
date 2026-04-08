<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides a form for deleting a slot.
 */
class DeleteSlotForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The slot ID.
   *
   * @var int
   */
  protected $slotId;

  /**
   * Constructs a new DeleteSlotForm.
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
    return 'clubhouse_booking_delete_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this date?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('clubhouse_booking.admin_slots');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $slot_id = NULL) {
    $this->slotId = $slot_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->delete('clubhouse_slots')
      ->condition('id', $this->slotId)
      ->execute();

    $this->messenger()->addStatus($this->t('Date deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
