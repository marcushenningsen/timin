<?php

namespace Drupal\timin_timetracking\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Timesheet entities.
 *
 * @ingroup timin_timetracking
 */
class TimesheetEntityDeleteForm extends ContentEntityDeleteForm {

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $form_state->setRedirect('<front>');
    \Drupal::messenger()->addMessage($this->getDeletionMessage());
  }

  public function getQuestion() {
    $entity = $this->getEntity();

    return $this->t('Delete %label?', [
			'%label' => $entity->label(),
    ]);
  }

  protected function getDeletionMessage() {
    $entity = $this->getEntity();

    return $this->t('The @entity-type %label has been deleted.', [
			'@entity-type' => $entity
        ->getEntityType()
        ->getSingularLabel(),
      '%label' => $entity->label(),
   	]);
  }

}
