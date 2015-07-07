<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\SystemMailToUsersOfRoleTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Tests\rules\Integration\RulesEntityIntegrationTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Tests\rules\Integration\RulesUserIntegrationTestTrait;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\RulesAction\SystemMailToUsersOfRole
 * @group rules_actions
 */
class SystemMailToUsersOfRoleTest extends RulesEntityIntegrationTestBase {

  use RulesUserIntegrationTestTrait;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * @var \Drupal\user\UserStorage
   */
  protected $userStorage;

  /**
   * @var \Drupal\user\Entity\Role[]
   */
  protected $roles;

  /**
   * @var \Drupal\user\Entity\User[]
   */
  protected $users;

  /**
   * The action to be tested.
   *
   * @var \Drupal\rules\Plugin\Action\SystemMailToUsersOfRole
   */
  protected $action;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->enableModule('user');
    $this->namespaces[] = '';

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->mailManager = $this->getMockBuilder('Drupal\Core\Mail\MailManagerInterface')
      ->getMock();

    // Create two user roles and one user for each.
    foreach (['administrator', 'editor'] as $role_name) {
      // Mock the role entity.
      $this->roles[] = $this->getMockedUserRole($role_name);
      // Mock the user entity.
      $user = $this->getMockedUser();
      $id = $user->id();
      $this->users[$id] = $user;
      $this->userEntityType = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeInterface')
        ->getMock();
      $this->users[$id]->expects($this->any())
        ->method('getEntityType')
        ->willReturn($this->userEntityType);

      $this->users[$id]->expects($this->any())
        ->method('getPreferredLangcode')
        ->willReturn(LanguageInterface::LANGCODE_SITE_DEFAULT);
    }

    $this->userStorage = $this->getMockBuilder('Drupal\user\UserStorage')
      ->disableOriginalConstructor()
      ->getMock();

    // Prepare mocked entity manager.
    $this->entityManager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->setMethods(['getBundleInfo', 'getStorage', 'getDefinitions', 'getBaseFieldDefinitions', 'createInstance'])
      ->setConstructorArgs([
        $this->namespaces,
        $this->moduleHandler,
        $this->cacheBackend,
        $this->languageManager,
        $this->getStringTranslationStub(),
        $this->getClassResolverStub(),
        $this->typedDataManager,
        $this->getMock('Drupal\Core\KeyValueStore\KeyValueFactoryInterface'),
        $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
      ])
      ->getMock();

    // Prepare a user entity type instance.
    $this->entityType = reset($this->users)->getEntityType();

    // Prepare mocked entity storage.
    $this->entityTypeStorage = $this->getMockBuilder('Drupal\Core\Entity\EntityStorageBase')
      ->setMethods(['create'])
      ->setConstructorArgs([$this->entityType])
      ->getMockForAbstractClass();

    // Return the mocked storage controller.
    $this->entityManager
      ->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->userStorage);

    $this->entityManager
      ->expects($this->any())
      ->method('createInstance')
      ->willReturn($this->userStorage);

    $this->entityManager
      ->expects($this->any())
      ->method('getDefinitions')
      ->willReturn($this->entityType);

    $this->container->set('logger.factory', $this->logger);
    $this->container->set('plugin.manager.mail', $this->mailManager);
    $config = [
      'site.config' => [
        'mail' => 'admin@example.com',
      ],
    ];
    $this->container->set('config.factory', $this->getConfigFactoryStub($config));
    $this->container->set('entity.manager', $this->entityManager);

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
   * Tests sending a mail to one or two roles.
   *
   * @dataProvider providerSendMailToRole
   *
   * @covers ::execute
   */
  public function testSendMailToRole($call_number, $role_number) {
    // Unfortunately providerSendMailToRole() runs before setUp() so we can't
    // set these things up there.
    // Sending mail to one role.
    if ($role_number == 1) {
      $user = reset($this->users);
      $roles = [$this->roles[0]];
      $users = [$user->id() => $user];
      $this->_testSendMailToRole($call_number, $roles, $users);
    }
    // Sending mail to two roles.
    elseif ($role_number == 2) {
      $roles = $this->roles;
      $users = $this->users;
      $this->_testSendMailToRole($call_number, $roles, $users);
    }
  }

  /**
   * Helper function for testSendMailToRole().
   *
   * @param string $call_number
   *   The number of emails that should be sent.
   * @param \Drupal\user\Entity\Role[] $roles
   *   The array of Role objects to send the email to.
   * @param \Drupal\user\Entity\User[] $users
   *   The array of users that should get this email.
   *
   */
  private function _testSendMailToRole($call_number, $roles, $users) {
    $rids = [];
    foreach ($roles as $role) {
      $rids[] = $role->id();
    }
    $this->action->setContextValue('roles', $roles)
      ->setContextValue('subject', 'subject')
      ->setContextValue('body', 'hello');

    $langcode = reset($users)->getPreferredLangcode();
    $params = [
      'subject' => $this->action->getContextValue('subject'),
      'body' => $this->action->getContextValue('body'),
    ];

    $this->userStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->with(['roles' => $roles])
      ->willReturn($users);
    foreach ($users as $user) {
      $this->mailManager
        ->expects($this->once())
        ->method('mail')
        ->with('rules', $this->action->getPluginId(), $user->getEmail(), $langcode, $params)
        ->willReturn(['result' => ($call_number == 'once') ? TRUE : FALSE]);


      $this->logger
        ->expects($this->{$call_number}())
        ->method('notice')
        ->with(SafeMarkup::format('Successfully sent email to the role(s) %roles.', ['%roles' => implode(', ', $rids)]));
    }
    $this->action->execute();
  }

  /**
   * Data provider for self::testSendMailToRole().
   */
  public function providerSendMailToRole() {
    // Test sendings one or zero email to one or two roles.
    return [
      ['once', 1],
      ['never', 1],
      ['once', 2],
      ['never', 2],
    ];
  }
}
