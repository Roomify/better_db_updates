<?php

/**
 * Implements hook_drush_command().
 */
function better_db_updates_drush_command() {

  $items['better-updatedb'] = array(
    'description' => 'Better db update Drush command.',
    'aliases' => array('ddc'),
  );

  return $items;
}

/**
 * Callback for the better-updatedb command.
 */
function drush_core_better_updatedb() {
	require_once realpath(__DIR__) . '/includes/update.inc';

	update_main();
}

/**
 * Implements hook_drush_command_alter().
 */
function better_db_updates_drush_command_alter(&$command) {
  if ($command['command'] == 'updatedb') {
  	$command['command'] = 'better-updatedb';
    $command['command-hook'] = 'better-updatedb';
    drush_set_command($command);
  }
}
