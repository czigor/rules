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
use Drupal\Tests\rules\Integration\RulesUserIntegrationTestTrait;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\SystemMailToUsersOfRole
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
   * @var \Drupal\user\Entity\Role
   */
  protected $role;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $user;

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

    $this->logger = $this->getMock('Psr\Log\LoggerInterface');

    $this->mailManager = $this->getMockBuilder('Drupal\Core\Mail\MailManagerInterface')
      ->getMock();

    // Mock the role entity.
    $role_name = 'administrator';
    $this->role = $this->getMockedUserRole($role_name);
    $this->role->expects($this->any())
      ->method('id')
      ->willReturn($role_name);

    // Mock the user entity.
    $this->user = $this->getMockedUser();
    $this->userEntityType = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeInterface')
      ->getMock();
    $this->user->expects($this->any())
      ->method('getEntityType')
      ->willReturn($this->userEntityType);

    $this->user->expects($this->any())
      ->method('getPreferredLangcode')
      ->willReturn(LanguageInterface::LANGCODE_SITE_DEFAULT);

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

    // Prepare an content entity type instance.
    $this->entityType = $this->user->getEntityType();

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
   * Tests sending a mail to one role.
   *
   * @dataProvider providerSendMailToOneRole
   *
   * @covers ::execute
   */
  public function testSendMailToOneRole($call_number) {
    $roles = [$this->role];
    $this->action->setContextValue('roles', $roles)
      ->setContextValue('subject', 'subject')
      ->setContextValue('body', 'hello');

    $langcode = $this->user->getPreferredLangcode();
    $params = [
      'subject' => $this->action->getContextValue('subject'),
      'body' => $this->action->getContextValue('body'),
    ];

    $this->userStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->with(['roles' => [$this->role->id()]])
      ->willReturn([$this->user->id() => $this->user]);
    $this->mailManager
      ->expects($this->once())
      ->method('mail')
      ->with('rules', 'rules_mail_to_users_of_role_' . $this->action->getPluginId(), $this->user->getEmail(), $langcode, $params)
      ->willReturn(['result' => ($call_number == 'once') ? TRUE : FALSE]);

    $role_names = ($call_number == 'once') ? $this->role->id() : '';
    $this->logger
      ->expects($this->{$call_number}())
      ->method('notice')
      ->with(SafeMarkup::format('Successfully sent email to the role(s) %roles.', ['%roles' => $role_names]));

    $this->action->execute();
  }

  /**
   * Data provider for self::testSendMailToOneRole().
   */
  public function providerSendMailToOneRole() {
    // Testing for sendings one and zero email.
    return [
      ['once'],
      ['never'],
    ];
  }
}
