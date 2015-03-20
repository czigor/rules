<?php

/**
 * @file
 * Contains Drupal\rules\Tests\Action\SendMailTest.
 */

namespace Drupal\rules\Tests\Action;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\rules\Tests\RulesTestBase;
use Drupal\rules\Plugin\Action\SendEmail;


class SendMailTest extends RulesTestBase {

  protected $logger;

  protected $action;

  public function setUp() {
    parent::setUp();

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->action = new SendEmail([], '', ['context' => [
      'send_to' => new ContextDefinition('email', NULL, TRUE, TRUE),
      'subject' => new ContextDefinition('string'),
      'message' => new ContextDefinition('string'),
      'from' => new ContextDefinition('email', NULL, FALSE),
      'language' => new ContextDefinition('language', NULL, FALSE),
    ]], $this->logger);

    $this->action->setStringTranslation($this->getMockStringTranslation());
    $this->action->setTypedDataManager($this->getMockTypedDataManager());
  }

  public function testSummary() {
    $this->assertEquals('Send email', $this->action->getSummary());
  }

  public function testExecute() {

  }

}

// @todo: inject proper service once https://www.drupal.org/node/2301393 will be pushed.
namespace {
  if (!function_exists('drupal_mail')) {
    function drupal_mail($module, $key, $to, $langcode, $params = array(), $reply = NULL, $send = TRUE) {
      return array('results' => TRUE);
    }
  }
}