<?php

namespace Drupal\better_db_updates\Controller;

use Drupal\Core\Url;
use Drupal\system\Controller\DbUpdateController;
use Symfony\Component\HttpFoundation\Request;

class BetterDbUpdateController extends DbUpdateController {

  /**
   * {@inheritdoc}
   */
  protected function selection(Request $request) {
    // Make sure there is no stale theme registry.
    $this->cache->deleteAll();

    $count = 0;
    $incompatible_count = 0;
    $build['start'] = array(
      '#tree' => TRUE,
      '#type' => 'details',
    );

    // Ensure system.module's updates appear first.
    $build['start']['system'] = array();

    $starting_updates = array();
    $incompatible_updates_exist = FALSE;
    $updates_per_module = [];
    foreach (['update', 'post_update'] as $update_type) {
      switch ($update_type) {
        case 'update':
          $updates = update_get_update_list();
          break;
        case 'post_update':
          $updates = $this->postUpdateRegistry->getPendingUpdateInformation();
          break;
      }
      foreach ($updates as $module => $update) {
        if (!isset($update['start'])) {
          $build['start'][$module] = array(
            '#type' => 'item',
            '#title' => $module . ' module',
            '#markup' => $update['warning'],
            '#prefix' => '<div class="messages messages--warning">',
            '#suffix' => '</div>',
          );
          $incompatible_updates_exist = TRUE;
          continue;
        }
        if (!empty($update['pending'])) {
          $updates_per_module += [$module => []];
          $updates_per_module[$module] = array_merge($updates_per_module[$module], $update['pending']);
          $build['start'][$module] = array(
            '#type' => 'hidden',
            '#value' => $update['start'],
          );
          // Store the previous items in order to merge normal updates and
          // post_update functions together.
          $build['start'][$module] = array(
            '#theme' => 'item_list',
            '#items' => $updates_per_module[$module],
            '#title' => $module . ' module',
          );

          if ($update_type === 'update') {
            $starting_updates[$module] = $update['start'];
          }
        }
        if (isset($update['pending'])) {
          $count = $count + count($update['pending']);
        }
      }
    }

    // Find and label any incompatible updates.
    foreach (update_resolve_dependencies($starting_updates) as $data) {
      if (!$data['allowed']) {
        $incompatible_updates_exist = TRUE;
        $incompatible_count++;
        $module_update_key = $data['module'] . '_updates';
        if (isset($build['start'][$module_update_key]['#items'][$data['number']])) {
          if ($data['missing_dependencies']) {
            $text = $this->t('This update will been skipped due to the following missing dependencies:') . '<em>' . implode(', ', $data['missing_dependencies']) . '</em>';
          }
          else {
            $text = $this->t("This update will be skipped due to an error in the module's code.");
          }
          $build['start'][$module_update_key]['#items'][$data['number']] .= '<div class="warning">' . $text . '</div>';
        }
        // Move the module containing this update to the top of the list.
        $build['start'] = array($module_update_key => $build['start'][$module_update_key]) + $build['start'];
      }
    }

    // Warn the user if any updates were incompatible.
    if ($incompatible_updates_exist) {
      drupal_set_message($this->t('Some of the pending updates cannot be applied because their dependencies were not met.'), 'warning');
    }

    unset($build['start']['better_db_updates']);
    $count -= 1;

    $all_files = better_db_updates_get_updates();

    foreach ($all_files as $module => $updates) {
      $items = array();

      foreach ($updates as $key => $info) {
        if (empty(db_select('better_db_updates', 'b')->fields('b', array())->condition('module_name', $module)->condition('update_filename', $info->name)->execute()->fetchAll())) {

          $docblock = better_db_updates_get_file_doc_block($info->uri);
          $update_description = $key . ' - ' . better_db_updates_parse_block($docblock);

          $items[$key] = $update_description;
          $count++;
        }
      }

      if (!empty($items)) {
        if (isset($build['start'][$module])) {
          $build['start'][$module]['#items'] = array_merge($build['start'][$module]['#items'], $items);
        }
        else {
          $build['start'][$module] = array(
            '#theme' => 'item_list',
            '#items' => $items,
            '#title' => $module . ' module',
          );
        }
      }
    }

    $build['help'] = array(
      '#markup' => '<p>' . $this->t('The version of Drupal you are updating from has been automatically detected.') . '</p>',
      '#weight' => -5,
    );
    if ($incompatible_count) {
      $build['start']['#title'] = $this->formatPlural(
        $count,
        '1 pending update (@number_applied to be applied, @number_incompatible skipped)',
        '@count pending updates (@number_applied to be applied, @number_incompatible skipped)',
        array('@number_applied' => $count - $incompatible_count, '@number_incompatible' => $incompatible_count)
      );
    }
    else {
      $build['start']['#title'] = $this->formatPlural($count, '1 pending update', '@count pending updates');
    }
    // @todo Simplify with https://www.drupal.org/node/2548095
    $base_url = str_replace('/update.php', '', $request->getBaseUrl());
    $url = (new Url('system.db_update', array('op' => 'run')))->setOption('base_url', $base_url);
    $build['link'] = array(
      '#type' => 'link',
      '#title' => $this->t('Apply pending updates'),
      '#attributes' => array('class' => array('button', 'button--primary')),
      '#weight' => 5,
      '#url' => $url,
      '#access' => $url->access($this->currentUser()),
    );

    if ($count == 0) {
      drupal_set_message($this->t('No pending updates.'));
      unset($build);
      $build['links'] = array(
        '#theme' => 'links',
        '#links' => $this->helpfulLinks($request),
      );
    }

    return $build;
  }

}
