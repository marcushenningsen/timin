<?php

namespace Drupal\timin_timetracking\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Employee edit forms.
 *
 * @ingroup timin_timetracking
 */
class EmployeeEntityForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\timin_timetracking\Entity\EmployeeEntity $entity */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

		$connection = \Drupal::service('database');

		//get all projects
		$project_ids = \Drupal::entityQuery('project')->accessCheck(TRUE)->execute();
		$projects = \Drupal::entityTypeManager()->getStorage('project')->loadMultiple($project_ids);

		//get project assignments
		$project_query = $connection->select('project__field_project_employee', 'pfpe');
		$project_query
			->condition('field_project_employee_target_id', $entity->id())
			->fields('pfpe', ['entity_id']);
		$results = $project_query->execute()->fetchAll();

		// initiate the wrapper
		$build['employee'] = [
			'#type' => 'container',
			'#tree' => TRUE,
			'#attributes' => ['class' => ['form-wrapper--employee'],],
			'#weight' => 5,
		];

		// create tableselect
		$header = [
			['data' => t('Project'), 			'class' => 'project'],
			['data' => t('Abbreviation'), 	'class' => 'project'],
			['data' => t('Status'), 			'class' => 'project'],
		];
		$options = [];
		foreach ($projects as $prj) {
			$options[$prj->id()] = [
				['data' => $prj->toLink(), 'class' => 'project'],
				['data' => $prj->get('field_abbreviation')->getString(), 'class' => 'project'],
				['data' => $prj->get('field_status2')->view(['label' => 'hidden']), 'class' => 'project'],
			];
		}
		$default_value = [];
		foreach ($results as $ass) {
			$default_value[$ass->entity_id] = true;
		}

		$form['employee']['project_assignments'] = [
			'#type' => 'tableselect',
			'#theme' => 'datatable',
      '#caption' => t('Project Assignments'),
			'#header' => $header,
			'#default_value' => $default_value,
			'#options' => $options,
			'#empty' => t('No projects assigned to this employee'),
			'#attributes' => [
				'class' => ['project-assignments-table', 'stripe', 'hover', 'order-column'],
				'datatable_options' => [
					'paging' => false,
					//'searching' => false,
					//'info' => false,
					//'order' => [[2, "asc"]],
					//'order' => [1, 'asc'],
					'aaSorting' => [],					
				],
			],
		];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;


    // save to generate the entity id
		$status = $entity->save();



		// get new project assignments
    $project_assignments_orig = $form_state->getValue('project_assignments');
		//dpm($project_assignments_orig);

		$project_assignments_employee = [];
		foreach ( $project_assignments_orig as $key => $value) {
			if ($value != 0 ) {
				$project_assignments_employee[$key] = 1;
			} else {
				$project_assignments_employee[$key] = 0;
			}
		}
		//dpm($project_assignments_employee);

		//get all project entities
		$project_ids = \Drupal::entityQuery('project')->accessCheck(TRUE)->execute();
		$projects = \Drupal::entityTypeManager()->getStorage('project')->loadMultiple($project_ids);

		$project_assignments = [];
		foreach ($projects as $prj) {
			$ass = $prj->get('field_project_employee')->getString();
			$project_assignments[$prj->id()] = explode(",", $ass);
		}
		//dpm($project_assignments);

		$project_assignments_chg = [];
		foreach ($project_assignments as $key => $value) {
			if ( $project_assignments_employee[$key] == 1 && in_array($entity->id(),$value) == false ) {
				array_push($value, $entity->id());
				$project_assignments_chg[$key] = $value;
			}
			if ( $project_assignments_employee[$key] == 0 && in_array($entity->id(),$value) == true ) {
				$idx = array_search($entity->id(), $value);
				unset($value[$idx]);
				$project_assignments_chg[$key] = $value;
			}
		}
		//dpm($project_assignments_chg);
		
		foreach ($project_assignments_chg as $id => $ass) {
			$projects[$id]->field_project_employee = $ass;
			$projects[$id]->save();		
		}

    //$status = parent::save($form, $form_state);

		switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the Employee %label.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the Employee %label.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.employee.canonical', ['employee' => $entity->id()]);
  }

}
