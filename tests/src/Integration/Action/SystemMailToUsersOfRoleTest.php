<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\SystemMailToUsersOfRoleTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Tests\rules\Integration\RulesIntegrationTestBase;
use Drupal\Core\Language\LanguageInterface;
use Psr\Log\LogLevel;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\SystemMailToUsersOfRole
 * @group rules_actions
 */
class SystemMailToUsersOfRoleTest extends RulesIntegrationTestBase {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * @var \Drupal\user\RoleInterface
   */
  protected $role1;

  /**
   * @var \Drupal\user\RoleInterface
   */
  protected $role2;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user1;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user2;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user3;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user4;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user5;

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
      ->getMock();

    $this->role1 = $this->getMock('\Drupal\user\RoleInterface');
    $this->role2 = $this->getMock('\Drupal\user\RoleInterface');

    $this->user1 = $this->getMock('\Drupal\user\Entity\User')->addRole($this->role1->id());
    $this->user2 = $this->getMock('\Drupal\user\Entity\User')->addRole($this->role1->id());
    $this->user3 = $this->getMock('\Drupal\user\Entity\User')->addRole($this->role1->id());
    $this->user4 = $this->getMock('\Drupal\user\Entity\User')->addRole($this->role2->id());
    $this->user5 = $this->getMock('\Drupal\user\Entity\User')->addRole($this->role2->id());

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
   * Tests sending a mail to one role.
   *
   * @covers ::execute
   */
  public function testSendMailToOneRole() {
    $roles = [$this->role1->id];
    $this->action->setContextValue('roles', $roles)
      ->setContextValue('subject', 'subject')
      ->setContextValue('body', 'hello');

    $langcode =  LanguageInterface::LANGCODE_SITE_DEFAULT;
    $params = [
      'subject' => $this->action->getContextValue('subject'),
      'message' => $this->action->getContextValue('body'),
    ];

    $this->mailManager
      ->expects($this->once())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), implode(', ', $this->user1->getEmail()), $langcode, $params)
      ->willReturn(['result' => TRUE]);

    $this->mailManager
      ->expects($this->once())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), implode(', ', $this->user2->getEmail()), $langcode, $params)
      ->willReturn(['result' => TRUE]);

    $this->mailManager
      ->expects($this->once())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), implode(', ', $this->user3->getEmail()), $langcode, $params)
      ->willReturn(['result' => TRUE]);

    $this->mailManager
      ->expects($this->never())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), implode(', ', $this->user4->getEmail()), $langcode, $params)
      ->willReturn(['result' => TRUE]);

    $this->mailManager
      ->expects($this->never())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), implode(', ', $this->user5->getEmail()), $langcode, $params)
      ->willReturn(['result' => TRUE]);

    $this->logger
      ->expects($this->once())
      ->method('notice')
      ->with(SafeMarkup::format('Successfully sent email to %to', ['%to' => implode(', ', $roles)]));

    $this->action->execute();
  }
}
