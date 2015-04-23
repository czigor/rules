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
use Drupal\Core\Entity\EntityManager;

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

    $this->mailManager = $this->getMockBuilder('\Drupal\Core\Mail\MailManagerInterface')
      ->getMock();

    $this->role = $this->getMockBuilder('\Drupal\user\Entity\Role')
      ->disableOriginalConstructor()
      ->getMock();

    $this->user = $this->getMockBuilder('\Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();

    // Prepare mocked bundle field definition. This is needed because
    // EntityCreateDeriver adds required contexts for required fields, and
    // assumes that the bundle field is required.
    $this->bundleFieldDefinition = $this->getMockBuilder('Drupal\Core\Field\BaseFieldDefinition')
      ->disableOriginalConstructor()
      ->getMock();

    // The next methods are mocked because EntityCreateDeriver executes them,
    // and the mocked field definition is instantiated without the necessary
    // information.
    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getCardinality')
      ->willReturn(1);

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getType')
      ->willReturn('string');

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getLabel')
      ->willReturn('Bundle');

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getDescription')
      ->willReturn('Bundle mock description');

    // Prepare mocked entity manager.
    $this->entityManager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->setMethods(['getBundleInfo', 'getStorage', 'getDefinitions', 'getBaseFieldDefinitions'])
      ->setConstructorArgs([
        $this->namespaces,
        $this->moduleHandler,
        $this->cacheBackend,
        $this->languageManager,
        $this->getStringTranslationStub(),
        $this->getClassResolverStub(),
        $this->typedDataManager,
        $this->getMock('Drupal\Core\KeyValueStore\KeyValueStoreInterface'),
        $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
      ])
      ->getMock();

    // Return the mocked storage controller.
    $this->entityManager
      ->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->entityTypeStorage);

          // Prepare mocked entity storage.
    /*$this->entityTypeStorage = $this->getMockBuilder('Drupal\user\UserStorage')
      ->setMethods(['create'])
      ->setConstructorArgs([

      ])
      ->getMock();*/

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
   * @dataProvider providerTestSendMailToOneRole
   *
   * @covers ::execute
   */
  public function testSendMailToOneRole($call_number) {
    $roles = [$this->role];
    $this->action->setContextValue('roles', $roles)
      ->setContextValue('subject', 'subject')
      ->setContextValue('body', 'hello');

    $langcode =  LanguageInterface::LANGCODE_SITE_DEFAULT;
    $params = [
      'subject' => $this->action->getContextValue('subject'),
      'message' => $this->action->getContextValue('body'),
    ];

    $this->userStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->with(['roles' => $this->role->id()])
      ->willReturn([$this->user->id() => $this->user]);
    $this->mailManager
      ->expects($this->never())
      ->method('mail')
      ->with('rules', 'rules_action_mail_' . $this->action->getPluginId(), $this->user->getEmail(), $langcode, $params)
      ->willReturn(['result' => ($call_number == 'once') ? TRUE : FALSE]);

    $role_names = ($call_number == 'once') ? $this->role->label() : '';
    $this->logger
      ->expects($this->never())
      ->method('notice')
      ->with(SafeMarkup::format('Successfully sent email to the role(s) %roles.', ['%roles' => $role_names]));

    $this->action->execute();
  }

  /**
   * Data provider for self::testSendMailToOneRole().
   */
  public function providerTestSendMailToOneRole() {
    return [
      ['once'],
      ['never'],
    ];
  }
}
