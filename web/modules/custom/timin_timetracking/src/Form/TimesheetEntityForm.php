<?php

namespace Drupal\timin_timetracking\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Timesheet edit forms.
 *
 * @ingroup timin_timetracking
 */
class TimesheetEntityForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var \Drupal\timin_timetracking\Entity\TimesheetEntity $entity */
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;
    $build_info = $form_state->getBuildInfo('form_id');
    $storage = $form_state->getStorage();

    // get selected project
    if ( empty($entity->id()) ) {
      if ( array_key_exists('picked_project',$storage) ) {
        $selectedProject = $storage['picked_project'];
      } else {
        $selectedProject = 0;
      }
    } else {
      $selectedProject = $form['field_project']['widget']['#default_value'][0];
    }

    // get selected date
    if ( empty($entity->id()) ) {
      if ( array_key_exists('picked_date_value',$storage) ) {
        $selectedDate = $storage['picked_date_value'];
      } else {
        $selectedDate = $form['field_date']['widget'][0]['value']['#default_value'];
      }
    } else
    if ( $build_info['form_id'] == 'timesheet_clone_form' ) {
      if ( array_key_exists('picked_date_value',$storage) ) {
        $selectedDate = $storage['picked_date_value'];
      } else {
        $selectedDate = 0;
      }
    } else {
      $selectedDate = $form['field_date']['widget'][0]['value']['#default_value'];
    }

    // all connections
    $connection = $this->database;

    $service_type_results = [];
    $service_type_query =  $connection->select('paragraphs_item_field_data', 'fd');
    $service_type_query->join('paragraph__field_name', 'fn', 'fd.id = fn.entity_id');
    $service_type_query->innerJoin('project__field_works', 'fw', 'fd.id = fw.field_works_target_id');
    $service_type_query
      ->condition('fd.parent_id', $selectedProject)
      ->condition('fd.status', 1)
      ->fields('fd',['id'])
      ->fields('fn',['field_name_value'])
      ->orderBy('fw.delta');
    $service_type_results = $service_type_query->execute()->fetchAll();

    $employee_results = [];
    $employee_query =  $connection->select('project__field_project_employee', 'pe');
    $employee_query->join('employee', 'e', 'pe.field_project_employee_target_id = e.id');
    $employee_query->join('employee__field_count_to_personnel', 'ecp', 'e.id = ecp.entity_id');
    $employee_query
      ->condition('pe.entity_id', $selectedProject)
      ->condition('e.status', 1, '>=')
      ->fields('pe',['field_project_employee_target_id'])
      ->fields('e',['name'])
      ->fields('ecp',['field_count_to_personnel_value'])
      ->orderBy('name');
    $employee_results = $employee_query->execute()->fetchAll();

    $timesheet_works_results = [];
    $timesheet_works_query =  $connection->select('timesheet_works', 'tw');
    $timesheet_works_query->leftJoin('paragraph__field_name', 'fn', 'tw.service_type_id = fn.entity_id');
    $timesheet_works_query
      ->condition('tw.timesheet_id', $entity->id())
      ->fields('tw')
      ->fields('fn',['field_name_value']);
    $timesheet_works_results = $timesheet_works_query->execute()->fetchAll();

    $timesheet_data_results = [];
    $timesheet_data_query =  $connection->select('timesheet_data', 'td');
    $timesheet_data_query->join('employee', 'e', 'td.employee_id = e.id');
    $timesheet_data_query->join('employee__field_count_to_personnel', 'ecp', 'e.id = ecp.entity_id');
    $timesheet_data_query
      ->condition('td.timesheet_id', $entity->id())
      ->fields('td')
      ->fields('e',['name'])
      ->fields('ecp',['field_count_to_personnel_value'])
      ->orderBy('name');
    $timesheet_data_results = $timesheet_data_query->execute()->fetchAll();

    // build the employees array
    $employees = [];
    foreach ($employee_results as $employee) {
      $employees[$employee->field_project_employee_target_id] = [
        'id' => $employee->field_project_employee_target_id,
        'name' => $employee->name,
        'count_to_personnel' => $employee->field_count_to_personnel_value,
      ];
    }

    foreach ($timesheet_data_results as $data) {
      $employees[$data->employee_id] = [
        'id' => $data->employee_id,
        'name' => $data->name,
        'count_to_personnel' => $data->field_count_to_personnel_value,
      ];
    }

    uasort($employees, function($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    // build the added works array

    if ( empty($entity->id()) ) {
      $added_works_initial[0] = [
        'id' => '0',
        'name' => '',
        'description' => '',
        'ignore_print' => 0,
      ];
    } else {
      foreach ($timesheet_works_results as $timesheet_works) {
        $added_works_initial[$timesheet_works->service_type_id] = [
          'id' => $timesheet_works->service_type_id,
          'name' => $timesheet_works->field_name_value,
          'description' => $timesheet_works->works_description,
          'ignore_print' => $timesheet_works->ignore_print,
        ];
      }

    }
    // $form_state wird erst nach einem callback gefüllt. Ist was da (z.B. nach einem "Preview" oder einem "Add Works"), dann im Variabel damit, sonst die initiale Werte.
    $added_works = $form_state->get('added_works');
    if (empty($added_works)) {
      $added_works = $added_works_initial;
      $form_state->set('added_works', $added_works);
    }

    // build the service_types arrays
    $service_types_available = [];
    $service_types_added = [];
    foreach ($service_type_results as $service_type) {
      if ( array_key_exists($service_type->id, $added_works) ) {
        $service_types_added[$service_type->id] = $service_type->field_name_value;
      } else {
        $service_types_available[$service_type->id] = $service_type->field_name_value;
      }
    }

    // build the form elements

    // wrapper for the header elements
    $form['timesheet_header'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['form-wrapper'],],
      '#weight' => 0,
    ];

    // manipulate the field_project, remove inactive projects
    $pids = $this->entityTypeManager->getStorage('project')->getQuery()->condition('field_status2', 0)->accessCheck(TRUE)->execute();
    foreach ( $pids as $key ) {
      if ( $key != $entity->field_project->target_id ) { // ... unless they are already picked!
        unset($form['field_project']['widget']['#options'][$key]);
      }
    }

    // put the fields into the wrapper
    $form['timesheet_header']['field_project'] = $form['field_project'];
    $form['timesheet_header']['field_date'] = $form['field_date'];
    $form['timesheet_header']['field_events'] = $form['field_events'];
    $form['timesheet_header']['field_internal_note'] = $form['field_internal_note'];

    // and unset the original elements
    unset($form['field_project']);
    unset($form['field_date']);
    unset($form['field_events']);
    unset($form['field_internal_note']);

/*
    $form['timesheet_header']['field_date']['#date_date_format'] = 'd/m/Y';
    $form['timesheet_header']['field_date']['#attributes']['data-drupal-date-format'] = ['d/m/Y'];
    $form['timesheet_header']['field_date']['#attributes']['placeholder'] = ["DD/MM/YYYY"];
    dpm($form);
*/

    // set values in header fields
    $form['timesheet_header']['field_project']['widget']['#default_value'] = $selectedProject;
    $form['timesheet_header']['field_date']['widget'][0]['value']['#default_value'] = $selectedDate;

    // button for "set project"
    $form['timesheet_header']['submit_field_project'] = [
      '#type' => 'submit',
      '#value' => t('Set Project and Date'),
      '#submit' => ['::setProjectCallback'],
      '#attributes' => [
        'class' => ['button--action','button--primary'],
        'id' => 'set-project',
      ],
    ];

    // ordering
    $form['timesheet_header']['field_project']['#weight'] = 0;
    $form['timesheet_header']['field_date']['#weight'] = 1;
    $form['timesheet_header']['submit_field_project']['#weight'] = 2;
    $form['timesheet_header']['field_events']['#weight'] = 3;

    // and block-inline
    $form['timesheet_header']['field_project']['#attributes']['class'] = ['form-item--inline'];
    $form['timesheet_header']['field_date']['#attributes']['class'] = ['form-item--inline'];
    $form['timesheet_header']['submit_field_project']['#attributes']['class'] = ['form-item--inline'];

    // initiate the works wrapper
    $form['works'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['form-wrapper--works'],],
      '#weight' => 9,
    ];

    // button for more works to the bottom of the form
    $form['service_type'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['form-wrapper'],],
      '#weight' => 15,
    ];

    // the select field and submit button for extra services
    $form['service_type']['select_service_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Add Works'),
      '#options' => $service_types_available,
      '#wrapper_attributes' => ['class' => ['form-item--inline']],
    ];

    $form['service_type']['submit_service_type'] = [
      '#type' => 'submit',
      '#value' => t('+ Works'),
      '#submit' => ['::addWorksCallback'],
      '#attributes' => [
        'class' => ['button--action','button--primary'],
        'id' => 'set-service-type',
      ],
    ];

    if ( empty($service_types_available) ) {
      $form['service_type']['select_service_type']['#attributes']['disabled'] = TRUE;
      $form['service_type']['select_service_type']['#empty_option'] = t('All services added');
      $form['service_type']['submit_service_type']['#attributes']['disabled'] = TRUE;
    }

    // wrapper for the images
    $form['timesheet_images'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => ['form-wrapper--works'],],
      '#weight' => 20,
    ];

    $form['timesheet_images']['field_image'] = $form['field_image'];
    unset($form['field_image']);
    $form['timesheet_images']['#disabled'] = TRUE;

    // preview button
    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => t('Preview'),
      '#submit' => ['::previewCallback'],
      '#attributes' => [
        'class' => ['button--action','button--primary'],
        'id' => 'preview',
      ],
    ];

    // disable action buttons
    $form['actions']['submit']['#disabled'] = TRUE;
    $form['actions']['preview']['#disabled'] = TRUE;

    // ordering
    $form['actions']['submit']['#weight'] = 0;
    $form['actions']['preview']['#weight'] = 1;
    $form['actions']['delete']['#weight'] = 2;

    // add elements depending on the chosen project
    if ( $selectedProject ) {

      // disable header fields
      $form['timesheet_header']['field_project']['widget']['#attributes']['disabled'] = TRUE;
      $form['timesheet_header']['field_date']['#disabled'] = TRUE;
      $form['timesheet_header']['submit_field_project']['#attributes']['disabled'] = TRUE;
      // and enable the images wrapper
      $form['timesheet_images']['#disabled'] = false;
      // and enable the save button
      $form['actions']['submit']['#disabled'] = false;
      $form['actions']['preview']['#disabled'] = false;

      foreach ($added_works as $service_type_id => $works) {

        $form['works'][$service_type_id] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-wrapper--works-item'],],
        ];

        //if ( $service_type_id > 0 ) {

          if ( $service_type_id == 0 ) {
            $works['name'] = t('Main Service');
          }

          $form['works'][$service_type_id]['details'] = [
            '#type' => 'details',
            '#title' => $works['name'],
            '#attributes' => [
              'class' => [
                'service-type-id-' . $service_type_id,
              ],
            ],
          ];

          $form['works'][$service_type_id]['details']['service_type'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Service Type'),
            '#default_value' => $works['name'],
            '#attributes' => [
              'disabled' => 'disabled'
            ],
          ];

        //}

        $form['works'][$service_type_id]['details']['service_type_id'] = [
          '#type' => 'textfield',
          '#default_value' => $service_type_id,
          '#attributes' => [
            'hidden' => 'true'
          ],
        ];

        if ( $works['description'] != '' ) {
          $works_description = $works['description'];
        } else {
          $works_description =  t('- Ort, Bauteil, Tätigkeit');
        };

        $form['works'][$service_type_id]['details']['works_description'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Works Description'),
          '#default_value' => $works_description,
        ];

        if ( $service_type_id > 0 ) {
          $form['works'][$service_type_id]['details']['works_description']['#title'] = $this->t('Extra Works Description');
        }

        $form['works'][$service_type_id]['details']['time_trackings'] = [
          '#type' => 'table',
          '#header' => [
            ['data' => t('Employee'), 'class' => 'employee'],
            ['data' => t('Time'), 'class' => 'time'],
            ['data' => t('Count'), 'class' => 'hidden'],
          ],
          '#empty' => t('No employees assigned to this project'),
          '#attributes' => [
            'class' => ['timesheet-data-table'],
          ],
        ];

        if ( $service_type_id > 0 ) {
          $form['works'][$service_type_id]['details']['remove_works'] = [
            '#type' => 'submit',
            '#value' => t('- Works'),
            '#name' => 'remove_works-' . $service_type_id,
            '#submit' => ['::removeWorksCallback'],
            '#attributes' => [
              'class' => ['button--action','button--primary'],
            ],
          ];
        }

        // fill the table
        foreach ($employees as $employee_id => $employee) {
          $form['works'][$service_type_id]['details']['time_trackings'][$employee_id]['employee'] = [
            '#type' => 'textfield',
            '#default_value' => $employee['name'],
            '#attributes' => [
              'placeholder' => $employee['name'],
              'disabled' => 'disabled'
            ],
            '#required' => TRUE,
          ];
          $form['works'][$service_type_id]['details']['time_trackings'][$employee_id]['time'] = [
            '#type' => 'number',
            //'#min' => 0,
            //'#max' => 24,
            '#step' => 0.25,
            '#default_value' => 0,
            '#required' => TRUE,
          ];
          $form['works'][$service_type_id]['details']['time_trackings'][$employee_id]['count_to_personnel'] = [
            '#type' => 'value',
            '#value' => $employee['count_to_personnel'],

          ];

          foreach ($timesheet_data_results as $timesheet_data) {
            if ( $timesheet_data->service_type_id == $service_type_id && $timesheet_data->employee_id == $employee_id ) {
              $form['works'][$service_type_id]['details']['time_trackings'][$employee_id]['time']['#default_value'] = $timesheet_data->time;
            }
          }
        }

        if ( array_key_exists('ignore_print', $works) ) {
          $ignore_print = $works['ignore_print'];
        } else {
          $ignore_print = null;
        }

        $form['works'][$service_type_id]['details']['ignore_print'] = [
          '#type' => 'checkbox',
          '#title' => t('Don\'t print service type'),
          '#default_value' => $ignore_print,
        ];

      }
    }

    // when no date (clone)
    if ( $selectedDate == '0' ) {

      $form['timesheet_header']['field_date']['#disabled'] = false;
      $form['timesheet_header']['submit_field_project']['#attributes']['disabled'] = false;
      $form['timesheet_header']['field_events']['#disabled'] = TRUE;
      $form['timesheet_header']['field_internal_note']['#disabled'] = TRUE;
      $form['works']['#disabled'] = TRUE;
      $form['service_type']['#disabled'] = TRUE;
      $form['actions']['submit']['#disabled'] = TRUE;
      $form['actions']['preview']['#disabled'] = TRUE;
    }

    if ( $build_info['form_id'] == 'timesheet_clone_form' ) {
      unset($form['actions']['delete']);
    }

    //
    //
    // preview
    //
    //
    if ( $form_state->get('preview') ) {

      // hide form elements
      $form['timesheet_header']['#attributes']['class'][] = 'hidden';
      $form['works']['#attributes']['class'][] = 'hidden';
      $form['service_type']['#attributes']['class'][] = 'hidden';
      $form['timesheet_images']['#attributes']['class'][] = 'hidden';

      // wrapper for the preview
      $form['preview'] = [
        '#type' => 'container',
        '#tree' => TRUE,
        '#attributes' => ['class' => ['form-wrapper'],],
        '#weight' => -1,
      ];

      // table
      $form['preview']['time_trackings'] = [
        '#type' => 'table',
        '#header' => [
          ['data' => t('Employee'), 'class' => 'employee'],
          ['data' => t('Time'), 'class' => 'time'],
          ['data' => t('Extra Works'), 'class' => 'works_sum'],
          ['data' => t('Total'), 'class' => 'sum'],
        ],
        '#empty' => 'No employees assigned to this project',
        '#attributes' => [
          'class' => ['timesheet-data-table'],
        ],
      ];

      foreach ($employees as $employee_id => $employee) {
        $form['preview']['time_trackings'][$employee_id]['employee'] = [
          '#type' => 'textfield',
          '#default_value' => $employee['name'],
          '#attributes' => [
            'placeholder' => $employee['name'],
            'disabled' => 'true'
          ],
        ];
        $form['preview']['time_trackings'][$employee_id]['time'] = [
          '#type' => 'textfield',
          //'#default_value' => $form_state->getValue('works')[0]['time_trackings'][$employee_id]['time'],
          '#default_value' => number_format($form_state->getValue('works')[0]['details']['time_trackings'][$employee_id]['time'], 2, ',', '.'),

          '#attributes' => [
            'disabled' => 'true',
            'class' => ['time'],
          ],
        ];

        $works_sum = 0;
        foreach ( $form_state->getValue('works') as $service_type_id => $service_type) {
          if ( $service_type_id > 0 ) {
            $works_sum += $form_state->getValue('works')[$service_type_id]['details']['time_trackings'][$employee_id]['time'];
          }
        };

        $form['preview']['time_trackings'][$employee_id]['works_sum'] = [
          '#type' => 'textfield',
          '#default_value' => number_format($works_sum, 2, ',', '.'),
          '#attributes' => [
            'disabled' => 'true',
            'class' => ['time'],
          ],
        ];

        $sum = 0;
        foreach ( $form_state->getValue('works') as $service_type_id => $service_type) {
          $sum += $form_state->getValue('works')[$service_type_id]['details']['time_trackings'][$employee_id]['time'];
        };
        $form['preview']['time_trackings'][$employee_id]['sum'] = [
          '#type' => 'textfield',
          '#default_value' => number_format($sum, 2, ',', '.'),
          '#attributes' => [
            'disabled' => 'true',
            'class' => ['time','sum'],
          ],
        ];

      }

      // Sum line
      $form['preview']['time_trackings']['sum']['employee'] = [
        '#type' => 'textfield',
        '#default_value' => t('Sum'),
        '#attributes' => [
          'class' => ['sum','sum_label'],
          'disabled' => 'true',
        ],
      ];

      $time_total = 0;
      foreach ( $form_state->getValue('works') as $service_type_id => $service_type) {
        if ( $service_type_id == 0 ) {
          foreach ( $service_type['details']['time_trackings'] as $key => $time_tracking ) {
            $time_total += $time_tracking['time'];
          }
        }
      };

      $form['preview']['time_trackings']['sum']['time'] = [
        '#type' => 'textfield',
        '#default_value' => number_format($time_total, 2, ',', '.'),
        '#attributes' => [
          'class' => ['sum'],
          'disabled' => 'true',
        ],
      ];

      $works_total = 0;
      foreach ( $form_state->getValue('works') as $service_type_id => $service_type) {
        if ( $service_type_id > 0 ) {
          foreach ( $service_type['details']['time_trackings'] as $key => $time_tracking ) {
            $works_total += $time_tracking['time'];
          }
        }
      };

      $form['preview']['time_trackings']['sum']['works_sum'] = [
        '#type' => 'textfield',
        '#default_value' => number_format($works_total, 2, ',', '.'),
        '#attributes' => [
          'class' => ['sum'],
          'disabled' => 'true',
        ],
      ];

      $sum_total = 0;
      foreach ( $form_state->getValue('works') as $service_type_id => $service_type) {
        foreach ( $service_type['details']['time_trackings'] as $key => $time_tracking ) {
          $sum_total += $time_tracking['time'];
        }
      };

      $form['preview']['time_trackings']['sum']['sum'] = [
        '#type' => 'textfield',
        '#default_value' => number_format($sum_total, 2, ',', '.'),
        '#attributes' => [
          'class' => ['sum'],
          'disabled' => 'true',
        ],
      ];

      // back to form button
      $form['preview']['back'] = [
        '#type' => 'submit',
        '#value' => t('Back to form'),
        '#submit' => ['::backCallback'],
        '#attributes' => [
          'class' => ['button--action','button--primary'],
          'id' => 'back',
        ],
      ];

      // save button
      $form['preview']['submit'] = $form['actions']['submit'];
      unset($form['actions']);

    }

    //dpm($form);

    return $form;
  }

 /**
   * Callbacks
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   */

  public function setProjectCallback(array &$form, FormStateInterface $form_state) {

    $picked_project = $form_state->getValue('field_project')[0]['target_id'];
    $picked_date_value = $form_state->getValue('field_date')[0]['value'];
    $picked_date = $picked_date_value->format('Y-m-d');

    $connection = $this->database;
    $timesheet_results = [];
    $timesheet_query =  $connection->select('timesheet', 't');
    $timesheet_query->leftJoin('timesheet__field_project', 'tp', 't.id = tp.entity_id');
    $timesheet_query->leftJoin('timesheet__field_date', 'td', 't.id = td.entity_id');
    $timesheet_query
      ->condition('field_project_target_id', $picked_project)
      ->condition('field_date_value', $picked_date)
      ->fields('t',['id'])
      ->fields('t',['name'])
      ->fields('tp',['field_project_target_id'])
      ->fields('td',['field_date_value']);
    $timesheet_results = $timesheet_query->execute()->fetchAll();

    if ( !empty($timesheet_results) ) {
      $timesheet_id = $timesheet_results[0]->id;
      $timesheet_entity = $this->entityTypeManager->getStorage('timesheet')->load($timesheet_id);

      $this->messenger()->addError(t('A report for this project and date already exists: %link',[
        '%link' => $timesheet_entity->toLink()->toString(),
      ]));

      $form_state->unsetValue('field_project');
      $form_state->unsetValue('field_date');

    } else {

      $form_state->setStorage([
        'picked_project' => $picked_project,
        'picked_date_value' => $picked_date_value,
        'picked_date' => $picked_date
      ]);
    }

    $form_state->setRebuild();
  }

  public function addWorksCallback(array &$form, FormStateInterface $form_state) {
    $selectedServiceType = $form_state->getValue(['service_type','select_service_type']);

    $connection = $this->database;
/*
    $service_type_results = [];
    $service_type_query =  $connection->select('taxonomy_term_field_data', 'tt');
    $service_type_query
      ->condition('tt.tid', $selectedServiceType)
      ->fields('tt',['tid'])
      ->fields('tt',['name']);
    $service_type_results = $service_type_query->execute()->fetchAll();
*/

    $service_type_results = [];
    $service_type_query =  $connection->select('paragraphs_item_field_data', 'fd');
    $service_type_query->join('paragraph__field_name', 'fn', 'fd.id = fn.entity_id');
    $service_type_query
      ->condition('fd.id', $selectedServiceType)
      ->fields('fd',['id'])
      ->fields('fn',['field_name_value']);
    $service_type_results = $service_type_query->execute()->fetchAll();

    $added_works = $form_state->get('added_works');

    $added_works[$selectedServiceType] = [
      'id' => $selectedServiceType,
      'name' => $service_type_results[0]->field_name_value,
      'description' => '',
    ];

    $form_state->set('added_works', $added_works);
    $form_state->setRebuild();
  }

  public function removeWorksCallback(array &$form, FormStateInterface $form_state) {
    $added_works = $form_state->get('added_works');

    $name = $form_state->getTriggeringElement()['#name'];
    $id = preg_replace('/remove_works-/', '', $name);

    unset($added_works[$id]);

    $form_state->set('added_works', $added_works);
    $form_state->setRebuild();
  }

  public function previewCallback(array &$form, FormStateInterface $form_state) {
    $form_state->set('preview', true);
    $form_state->setRebuild();
  }

  public function backCallback(array &$form, FormStateInterface $form_state) {
    $form_state->set('preview', null);
    $form_state->setRebuild();
  }

 /**
   * Validate the form
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   */

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
/*
    $has_time_trackings_table = 0;
    $time_entered = 0;
    if ( is_array($form_state->getValue('works')) ) {
      foreach ($form_state->getValue('works') as $works) {
        foreach ($works['time_trackings'] as $time_trackings) {
          $has_time_trackings_table = 1;
          if ( $time_trackings['time'] > 0 ) {
            $time_entered = 1;
          }
        }
      }
    }

    if ( $has_time_trackings_table == 1 && $time_entered == 0 ) {
      // Set an error for the form element with a key of "title".
      $form_state->setErrorByName('time_trackings', $this->t('At least one line needs a time.'));
    }
*/
  }

  /**
   * Save form
   *
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $build_info = $form_state->getBuildInfo('form_id');
    if ( $build_info['form_id'] == 'timesheet_clone_form' ) {
      $entity0 = $this->entity;
      $entity = $entity0->createDuplicate();
      $entity->set('created', time());
      $entity->setOwnerId($this->account->id());
    }

    // setName
    $project_id = $form_state->getValue('field_project')[0]['target_id'];
    $project_entity = $this->entityTypeManager->getStorage('project')->load($project_id);
    $project_abbr = $project_entity->get('field_abbreviation')->getString();
    $date_value = $form_state->getValue('field_date')[0]['value'];
    $formatted_date = $date_value->format('ymd');

    switch ( $date_value->format('N') ) {
      case 1:
        $weekday = 'Mo';
        break;
      case 2:
        $weekday = 'Di';
        break;
      case 3:
        $weekday = 'Mi';
        break;
      case 4:
        $weekday = 'Do';
        break;
      case 5:
        $weekday = 'Fr';
        break;
      case 6:
        $weekday = 'Sa';
        break;
      case 7:
        $weekday = 'So';
        break;
    }
    $entity->setName($project_abbr . ' ' . $formatted_date . ' ' . $weekday);

    $time_trackings_works = [];
    $time_trackings_time = [];
    $time_trackings_employees = [];
    $time_trackings_employees_real = [];
    foreach ( $form_state->getValue('works') as $service_type_id => $works ) {
      $time_trackings_works[] = $works;
      foreach ($form_state->getValue(['works',$service_type_id,'details','time_trackings']) as $employee_id => $time_tracking) {
        if ( $time_tracking['time'] != 0 ) {
          $time_trackings_time[] = $time_tracking['time'];
          $time_trackings_employees[] = $employee_id;
          if ( $time_tracking['count_to_personnel'] == 1 ) {
            $time_trackings_employees_real[] = $employee_id;
          }
        }
      }
    }

    /*
    $extra_works_list = '<ul>';
    $items = [];
    foreach ( $time_trackings_works as $key => $works ) {
      if ( $key > 0 ) {
        $items[] = $works['service_type'];
        $extra_works_list .= '<li>' . $works['service_type'] . '</li>';
      }
    }
    $extra_works_list .= '</ul>';
    */

    $entity->set('field_line_count', count($time_trackings_time));
    $entity->set('field_time_sum', array_sum($time_trackings_time));
    $entity->set('field_personnel_count', count(array_count_values($time_trackings_employees_real)));
    //$entity->set('field_extra_works', $extra_works_list);

    // Save the entity and its time entries atomically: if any insert
    // fails, the entity save is rolled back as well.
    $transaction = $this->database->startTransaction();

    try {
      // Save to generate the entity id.
      $status = $entity->save();

      // Replace all works and data entries of this timesheet.
      $this->database->delete('timesheet_works')
        ->condition('timesheet_id', $entity->id())
        ->execute();
      $this->database->delete('timesheet_data')
        ->condition('timesheet_id', $entity->id())
        ->execute();

      // Loop through the added works.
      foreach ($form_state->getValue('works') as $service_type_id => $works) {
        $time_sum_works = 0;

        // And through the time trackings within the works, inserting the data.
        foreach ($form_state->getValue(['works', $service_type_id, 'details', 'time_trackings']) as $employee_id => $time_tracking) {
          if ($time_tracking['time'] != 0) {
            $this->database->insert('timesheet_data')
              ->fields([
                'timesheet_id' => $entity->id(),
                'service_type_id' => $service_type_id,
                'employee_id' => $employee_id,
                'date' => $date_value->format('Y-m-d'),
                'project_id' => $project_id,
                'time' => $time_tracking['time'],
              ])
              ->execute();
            $time_sum_works += $time_tracking['time'];
          }
        }

        // Insert the works.
        if ($works['details']['service_type_id'] == 0 || !empty($works['details']['works_description']) || $time_sum_works != 0) {
          $this->database->insert('timesheet_works')
            ->fields([
              'timesheet_id' => $entity->id(),
              'service_type_id' => $service_type_id,
              'works_description' => $works['details']['works_description'],
              'ignore_print' => $works['details']['ignore_print'],
            ])
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      Error::logException($this->logger('timin_timetracking'), $e);
      $this->messenger()->addError($this->t('The timesheet could not be saved. No changes were stored.'));
      $form_state->setRebuild();
      return;
    }

    // Commit.
    unset($transaction);

    // print messages
    switch ($status) {
      case SAVED_NEW:

        $this->messenger()->addMessage($this->t('Submitted the timesheet %label.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:

        $this->messenger()->addMessage($this->t('Updated the timesheet %label.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.timesheet.canonical', ['timesheet' => $entity->id()]);

  }
}
