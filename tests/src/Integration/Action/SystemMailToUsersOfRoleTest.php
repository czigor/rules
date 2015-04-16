<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\SystemMailToUsersOfRoleTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Tests\rules\Integration\RulesEntityIntegrationTestBase;
use Drupal\Core\Language\LanguageInterface;
use Psr\Log\LogLevel;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\SystemMailToUsersOfRole
 * @group rules_actions
 */
class SystemMailToUsersOfRoleTest extends RulesEntityIntegrationTestBase {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * @var \Drupal\user\Entity\Role
   */
  protected $role1;

  /**
   * @var \Drupal\user\Entity\Role
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
    $this->enableModule('user');

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->mailManager = $this->getMockBuilder('Drupal\Core\Mail\MailManagerInterface')
      ->getMock();

    $this->role1 = $this->getMockBuilder('\Drupal\user\Entity\Role')
      ->disableOriginalConstructor()
      ->getMock();
    $this->role2 = $this->getMockBuilder('\Drupal\user\Entity\Role')
      ->disableOriginalConstructor()
      ->getMock();

    $this->user1 = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock()
      ->addRole($this->role1->id());
    $this->user2 = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock()
      ->addRole($this->role1->id());
    $this->user3 = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock()
      ->addRole($this->role1->id());
    $this->user4 = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock()
      ->addRole($this->role2->id());
    $this->user5 = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock()
      ->addRole($this->role2->id());

    $this->container->set('logger.factory', $this->logger);
    $this->container->set('plugin.manager.mail', $this->mailManager);

    $this->action = $this->actionManager->createInstance('rules_mail_to_users_of_role');
  }

  /**
   * Tests the summary.
   *
   * @covers ::summary
   */
  public function testSummary() {
    $this->assertEquals('Sends an e-mail to the users of a role', $this->action->summary());
  }

  /**
   * Tests sending a mail to one role.
   *
   * @covers ::execute
   */
  public function testSendMailToOneRole() {
    $roles = [$this->role1];
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
