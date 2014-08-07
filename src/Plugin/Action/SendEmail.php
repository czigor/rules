<?php

/**
 * @file
 * Contains Drupal\rules\Plugin\Action\SendEmail.
 */

namespace Drupal\rules\Plugin\Action;

use Drupal\rules\Engine\RulesActionBase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides "Send email" rules action.
 *
 * @Action(
 *   id = "send_email",
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
 *     "from" = @ContextDefinition("email",
 *       label = @Translation("From"),
 *       description = @Translation("The mail's from address. Leave it empty to use the site-wide configured address."),
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
class SendEmail extends RulesActionBase {

  /**
   * @var LoggerInterface $logger
   */
  protected $logger;

  /**
   * Constructs a CreatePathAlias object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param LoggerInterface $logger
   *   The alias storage service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('rules')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $this->t('Send email');
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $send_to = $this->getContextValue('send_to');
    // @todo: Process or remove FROM header according to https://www.drupal.org/node/2164905.
    $from = $this->getContextValue('from');
    $params = array(
      'subject' => $this->getContextValue('subject'),
      'message' => $this->getContextValue('message'),
      'langcode' => $this->getContextValue('language'),
    );
    // Set a unique key for this mail.
    // @todo: Try to fetch rule name here and use it to build $key string.
    $key = 'rules_action_mail_' . $this->getPluginId();

    $message = drupal_mail('rules', $key, $send_to, $params['langcode'], $params, $from);

    if ($message['result']) {
      $this->logger->log(LogLevel::NOTICE, $this->t('Successfully sent email to %recipient', array('%recipient' => $send_to));
    }
  }

}