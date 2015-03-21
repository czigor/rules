<?php

/**
 * @file
 * Contains Drupal\rules\Plugin\Action\SendEmail.
 */

namespace Drupal\rules\Plugin\Action;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\MailManager;
use Drupal\Core\MailManagerInterface;
use Drupal\rules\Core\RulesActionBase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Render\Element;

/**
 * Provides "Send email" rules action.
 *
 * @Action(
 *   id = "rules_send_email",
 *   label = @Translation("Send email"),
 *   context = {
 *     "send_to" = @ContextDefinition("email",
 *       label = @Translation("Send to"),
 *       description = @Translation("Email address(es) drupal will send an email to."),
 *       multiple = TRUE,
 *     ),
 *     "subject" = @ContextDefinition("string",
 *       label = @Translation("Subject"),
 *       description = @Translation("The email's subject."),
 *     ),
 *     "message" = @ContextDefinition("string",
 *       label = @Translation("Message"),
 *       description = @Translation("The email's message body."),
 *     ),
 *     "reply" = @ContextDefinition("email",
 *       label = @Translation("Reply to"),
 *       description = @Translation("The mail's reply-to address. Leave it empty to use the site-wide configured address."),
 *       required = FALSE,
 *     ),
 *     "language" = @ContextDefinition("language",
 *       label = @Translation("Language"),
 *       description = @Translation("If specified, the language used for getting the mail message and subject."),
 *       required = FALSE,
 *     ),
 *   }
 * )
 *
 * @todo: Define that message Context should be textarea comparing with textfield Subject
 * @todo: Add access callback information from Drupal 7.
 * @todo: Add group information from Drupal 7.
 */
class SendEmail extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * @var LoggerInterface $logger
   */
  protected $logger;

  /**
   * @var MailManagerInterface $mailManager
   */
  protected $mailManager;

  /**
   * Constructs a SendEmail object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param LoggerInterface $logger
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
    return $this->t('Send email');
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $send_to = $this->getContextValue('send_to');
    // @todo: Implement hook_mail_alter() in order to modify the FROM header according to https://www.drupal.org/node/2164905.
    $reply = $this->getContextValue('reply');
    $params = array(
      'subject' => $this->getContextValue('subject'),
      'message' => $this->getContextValue('message'),
      'langcode' => "en", //$this->getContextValue('language'),
    );
    // Set a unique key for this mail.
    $key = 'rules_action_mail_' . $this->getPluginId();

    $message = $this->mailManager->mail('rules', $key, $send_to, $params['langcode'], $params, $reply);

    if ($message['result']) {
      $recipient = implode(", ", $send_to);
      $this->logger->log(LogLevel::NOTICE, $this->t('Successfully sent email to %recipient', array('%recipient' => $recipient)));

    }
    return $message;
  }

}
