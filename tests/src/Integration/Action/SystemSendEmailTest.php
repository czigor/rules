<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\SystemSendEmailTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Tests\rules\Integration\RulesIntegrationTestBase;
use Drupal\Core\Language\LanguageInterface;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\SystemSendEmail
 * @group rules_actions
 */
class SystemSendEmailTest extends RulesIntegrationTestBase {

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
   * @var \Drupal\rules\Plugin\Action\SystemSendEmail
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
      'to' => array('mail@example.com'),
      'from' => 'admin@example.com',
      'result' => TRUE,
    );

    $this->action->setContextValue('to', $params['to'])
      ->setContextValue('subject', 'subject')
      ->setContextValue('message', 'hello');

    $reply = $this->action->getContextValue('reply');
    $language = $this->action->getContextValue('language');
    $mail_params = array(
      'subject' => $this->action->getContextValue('subject'),
      'message' => $this->action->getContextValue('message'),
      'langcode' => isset($language) ? $language->getId() : LanguageInterface::LANGCODE_SITE_DEFAULT,
    );

    $this->mailManager
      ->expects($this->once())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), $params['to'], $mail_params['langcode'], $mail_params, $reply)
      ->will($this->returnValue($params));

    $message = $this->action->execute();

    $this->assertEquals(1, $message['result']);
    $this->assertEquals($params['to'], $message['to']);
    $this->assertEquals($params['from'], $message['from']);
  }

}
