<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\SendEmailTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Tests\rules\Integration\RulesIntegrationTestBase;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\SendEmail
 * @group rules_actions
 */
class SendEmailTest extends RulesIntegrationTestBase {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The action to be tested.
   *
   * @var \Drupal\rules\Core\RulesActionInterface
   */
  protected $action;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->mailManager = $this->getMockBuilder('Drupal\Core\Mail\MailManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->container->set('logger.factory', $this->logger);
    $this->container->set('plugin.manager.mail', $this->mailManager);

    $this->action = $this->actionManager->createInstance('rules_send_email');
  }


  /**
   * Tests the summary.
   *
   * @covers ::summary
   */
  public function testSummary() {
    $this->assertEquals('Send email', $this->action->summary());
  }

  /**
   * Tests the action execution.
   *
   * @covers ::execute
   */
  public function testActionExecution() {
    $params = array(
      'to' => "mail@example.com",
      'from' => "admin@example.com",
      'result' => TRUE,
    );

    $this->mailManager
      ->expects($this->any())
      ->method('mail')
      ->will($this->returnValue($params));


    $this->action->setContextValue('to', array('mail@example.com'))
      ->setContextValue('subject', 'subject')
      ->setContextValue('message', 'hello');
    $message = $this->action->execute();

    $this->assertEquals(1, $message["result"]);
    $this->assertEquals('mail@example.com', $message['to']);
    $this->assertEquals('admin@example.com', $message['from']);

  }

}
