<?php

namespace Drupal\twitter_connect\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\twitter_connect\TwitterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a twitter block.
 *
 * @Block(
 *  id = "twitter_connect_block",
 *  admin_label = @Translation("Twitter Connect"),
 * )
 */
class TwitterConnectBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Twitter manager definition.
   *
   * @var \Drupal\twitter_connect\TwitterManager
   */
  protected $twitterManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a LocalActionDefault object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\twitter_connect\TwitterManager $twitter_manager
   *   The configuration factory.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, TwitterManager $twitter_manager, Renderer $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configFactory = $config_factory;
    $this->twitterManager = $twitter_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('twitter_connect.manager'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config_block = $this->getConfiguration();
    $twitter_data_access = $this->configFactory->getEditable('twitter_connect.settings');
    $tweets = [];

    if (empty($twitter_data_access->get('twitter_access_token'))) {
      return [
        '#markup' => $this->t("It's necessary apply twitter data tokens credencias to connect with the twitter API."),
      ];
    }

    $tweets_account = $this->twitterManager->getTweets('?screen_name=@' . $config_block['twitter_account'] . '&tweet_mode=extended&count=20');
    $tweets_account = array_slice($tweets_account, 0, $config_block['twitter_amount']);

    foreach ($tweets_account as $tweet) {
      $tweets[] = $this->normalizeTweets($tweet);
    }

    return [
      '#markup' => 'Globant twitter',
      '#tweets' => $tweets,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['twitter_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config['twitter_title'] ?? '',
      '#size' => 20,
      '#require' => TRUE,
    ];

    $form['twitter_cta_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CTA Text'),
      '#default_value' => $config['twitter_cta_text'] ?? '',
      '#size' => 32,
      '#require' => TRUE,
    ];

    $form['twitter_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account'),
      '#description' => $this->t('Add the account to get its tweets.'),
      '#default_value' => $config['twitter_account'] ?? '',
      '#size' => 20,
      '#require' => TRUE,
    ];

    $form['twitter_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Add the number of tweet to show of twitter account.'),
      '#default_value' => $config['twitter_amount'] ?? 4,
      '#size' => 30,
      '#require' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['twitter_title'] = $form_state->getValue('twitter_title');
    $this->configuration['twitter_cta_text'] = $form_state->getValue('twitter_cta_text');
    $this->configuration['twitter_account'] = $form_state->getValue('twitter_account');
    $this->configuration['twitter_amount'] = $form_state->getValue('twitter_amount');
  }

  /**
   * Normalize tweets.
   *
   * @param mixed $tweet
   *   Current tweet.
   *
   * @return array
   *   Tweet normalized.
   */
  public function normalizeTweets($tweet) {
    return [
      'date' => date('M d', strtotime($tweet['created_at'])),
      'description' => ['#markup' => $this->textBuild($tweet['full_text'], $tweet['entities'])],
      'postUrl' => sprintf('https://twitter.com/%s/status/%s', $tweet['user']['screen_name'], $tweet['id_str']),
      'account' => [
        'avatar' => $tweet['user']['profile_image_url_https'],
        'accountName' => $tweet['user']['name'],
        'accountUrl' => sprintf('https://twitter.com/%s', $tweet['user']['screen_name']),
      ],
      'media' => [
        'type' => $tweet['extended_entities']['media'][0]['type'] ?? NULL,
        'image' => $tweet['extended_entities']['media'][0]['media_url_https'] ?? NULL,
        'video' => $tweet['extended_entities']['media'][0]['video_info']['variants'][0]['url'] ?? NULL,
      ],
    ];
  }

  /**
   * Returns text built.
   *
   * @param mixed $text
   *   Text to send.
   * @param mixed $tweet_entities
   *   Tweet entities.
   */
  public function textBuild($text, $tweet_entities) {
    $pattern = '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@';
    $hashtags = $tweet_entities['hashtags'] ?? [];
    $accounts = $tweet_entities['user_mentions'] ?? [];
    $text = explode(' ', $text);

    foreach ($text as $key => $word) {
      if (preg_match($pattern, $word)) {
        $text[$key] = preg_replace($pattern, '<a href="$0" target="_blank">$0</a>', $word);
      }
    }

    $text = implode(' ', $text);

    foreach ($hashtags as $hash) {
      $link = [
        '#title' => '#' . $hash['text'],
        '#type' => 'link',
        '#url' => Url::fromUri('https://twitter.com/hashtag/' . $hash['text']),
        '#attributes' => [
          'target' => '_blank',
        ],
      ];

      $text = str_replace('#' . $hash['text'], $this->renderer->render($link)->__toString(), $text);
    }

    foreach ($accounts as $account) {
      $link = [
        '#title' => '@' . $account['screen_name'],
        '#type' => 'link',
        '#url' => Url::fromUri('https://twitter.com/' . $account['screen_name']),
        '#attributes' => [
          'target' => '_blank',
        ],
      ];

      $text = str_replace('@' . $account['screen_name'], $this->renderer->render($link)->__toString(), $text);
    }

    return $text;
  }

}
