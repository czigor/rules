<?php

/**
 * @file
 * Contains \Drupal\rules\Plugin\Action\SystemMailToUsersOfRole.
 */

namespace Drupal\rules\Plugin\Action;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\rules\Core\RulesActionBase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageInterface;
/**
 * Provides a 'Mail to users of a role' action.
 *
 * @Action(
 *   id = "rules_mail_to_users_of_role",
 *   label = @Translation("Mail to users of a role"),
 *   category = @Translation("System"),
 *   context = {
 *     "roles" = @ContextDefinition("entity:role",
 *       label = @Translation("Roles"),
 *       description = @Translation("The roles to which to send the e-mail."),
 *       multiple = TRUE
 *     ),
 *     "subject" = @ContextDefinition("string",
 *       label = @Translation("Subject"),
 *       description = @Translation("The subject of the e-mail."),
 *     ),
 *     "body" = @ContextDefinition("string",
 *       label = @Translation("Body"),
 *       description = @Translation("The body of the e-mail."),
 *     ),
 *     "from" = @ContextDefinition("string",
 *       label = @Translation("From"),
 *       description = @Translation("The from e-mail address."),
 *       required = FALSE
 *     )
 *   }
 * )
 *
 * @todo: Add access callback information from Drupal 7.
 */
class SystemMailToUsersOfRole extends RulesActionBase {

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a SendMailToUsersOfRole object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The alias storage service.
   * @param $mail_manager
   *   The alias mail manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('Sends an e-mail to the users of a role');
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $roles = $this->getContextValue('roles');
    if (empty($roles)) {
      return;
    }
    // Get the role ids and remove the empty ones (in case for example the role
    // has been removed in the meantime).
    $rids = array_filter(array_map(function ($role) {
      return $role->id();
    }, $roles));
    if (empty($rids)) {
      return;
    }

    // Get now all the users that match the roles (at least one of the role).
    $accounts = entity_load_multiple_by_properties('user', ['roles' => $rids]);
    // @todo: Should we implement support for tokens in subject and body? in the
    // Drupal 7 version it is not implemented for each user.
    $params = array(
      'subject' => $this->getContextValue('subject'),
      'body' => $this->getContextValue('body'),
    );
    $from = $this->getContextValue('from');
    if (empty($from)) {
      $from = \Drupal::config('system.site')->get('mail');
    }
    foreach ($accounts as $account) {
      $message = $this->mailManager->mail('rules', '', $account->getEmail(), $account->getPreferredLangcode(), $params, $from);
      // If $message['result'] is FALSE, then it's likely that email sending is
      // failing at the moment, and we should just abort sending any more. If
      // however, $mesage['result'] is NULL, then it's likely that a module has
      // aborted sending this particular email to this particular user, and we
      // should just keep on sending emails to the other users.
      if ($message['result'] === FALSE) {
        break;
      }

    }
    if ($message['result'] !== FALSE) {
      $role_names = array_intersect_key(user_roles(TRUE), array_flip($roles));
      $this->logger->notice($this->t('Successfully sent email to the role(s) %roles.', array('%roles' => implode(', ', $role_names))));
    }

  }
}
