<?php

namespace Drupal\twitter_connect;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\State;

/**
 * TwitterManager service class.
 */
class TwitterManager {

  /**
   * Endpoint url to get tweets by user account.
   */
  const URL_ACCOUNTS = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

  /**
   * Endpoint url to get tweets by hashtag.
   */
  const URL_HASHTAG = 'https://api.twitter.com/1.1/search/tweets.json';

  /**
   * Request method for request.
   */
  const REQUEST_METHOD = 'GET';

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Formatter serialization.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $json;

  /**
   * State system definition.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Twitter API definition.
   *
   * @var \TwitterAPIExchange
   */
  protected $twitterExchange;

  /**
   * Constructor class.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   It's an instance of config factory service.
   * @param \Drupal\Component\Serialization\Json $json
   *   It's an Instance of config serialization json service.
   * @param \Drupal\Core\State\State $state
   *   It's an Instance of state service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Json $json, State $state) {
    $this->configFactory = $config_factory;
    $this->json = $json;
    $this->state = $state;
    $this->twitterExchange = new \TwitterAPIExchange($this->getAccess());
  }

  /**
   * Gets access tokens.
   *
   * @return array
   *   A collection of access token.
   */
  public function getAccess() {
    $config = $this->configFactory->get('twitter_connect.settings');

    $settings = [
      'oauth_access_token' => $config->get('twitter_access_token') ?? '',
      'oauth_access_token_secret' => $config->get('twitter_access_token_secret') ?? '',
      'consumer_key' => $config->get('twitter_consumer_key') ?? '',
      'consumer_secret' => $config->get('twitter_consumer_secret') ?? '',
    ];

    return $settings;
  }

  /**
   * Gets all tweets of account of a searching by hashtag.
   *
   * @param mixed $query
   *   Data collection to run the request such as account or hashtag.
   * @param string $type
   *   String variable with information to identify the endpoint.
   *
   * @return json
   *   Json data with information of tweets.
   */
  public function getTweets($query, string $type = 'account') {
    $url = ($type == 'account') ? self::URL_ACCOUNTS : self::URL_HASHTAG;

    $this->twitterExchange->setGetfield($query);
    $this->twitterExchange->buildOauth($url, self::REQUEST_METHOD);

    $response = $this->json
      ->decode(
        $this->twitterExchange
          ->performRequest(
            TRUE, [
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => 0,
            ]
          )
      );

    if (!empty($response["errors"][0]["message"])) {
      $smf_errors = $this->state->get('social_media_errors');
      $smf_errors['twitter'][$response['errors'][0]['code']] = sprintf('Twitter error: %s - %s', $response['errors'][0]['code'], $response['errors'][0]['message']);
      $this->state->set('social_media_errors', $smf_errors);
      $response = [];
    }

    return $response;
  }

}
