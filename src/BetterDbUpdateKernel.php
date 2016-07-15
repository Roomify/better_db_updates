<?php

namespace Drupal\better_db_updates;

use Drupal\Core\Update\UpdateKernel;
use Symfony\Component\HttpFoundation\Request;

class BetterDbUpdateKernel extends UpdateKernel {

  /**
   * {@inheritdoc}
   */
  protected function handleRaw(Request $request) {
    $container = $this->getContainer();

    $this->handleAccess($request, $container);

    /** @var \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver */
    $controller_resolver = $container->get('controller_resolver');

    /** @var callable $db_update_controller */
    $db_update_controller = $controller_resolver->getControllerFromDefinition('\Drupal\better_db_updates\Controller\BetterDbUpdateController::handle');

    $this->setupRequestMatch($request);

    $arguments = $controller_resolver->getArguments($request, $db_update_controller);
    return call_user_func_array($db_update_controller, $arguments);
  }

}
