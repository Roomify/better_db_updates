<?php

namespace Drupal\better_db_updates\EventSubscriber;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\better_db_updates\BetterDBUpdateKernel;

class BetterDbUpdatesSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    if ($_SERVER['PHP_SELF'] == '/update.php/selection' && !isset($_SESSION['better_db_updates'])) {
      $_SESSION['better_db_updates'] = TRUE;

      $autoloader = require getcwd() . '/autoload.php';

      $kernel = new BetterDbUpdateKernel('prod', $autoloader, FALSE);
      $request = Request::createFromGlobals();

      $response = $kernel->handle($request);
      $response->send();

      $kernel->terminate($request, $response);

      exit();
    }
  }

}
