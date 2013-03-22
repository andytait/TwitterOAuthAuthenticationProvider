<?php
namespace CentralApps\Authentication\Providers;

class TwitterProvider implements OAuthProviderInterface
{
	protected $request;
	protected $userFactory;
	protected $userGateway;
	protected $get = array();
	protected $session = array();
	protected $externalId;
	protected $consumerKey;
	protected $consumerSecret;
	protected $callbackPage;
	protected $oAuthToken;
	protected $oAuthTokenSecret;
	protected $persistantTokensLookedUp = false;
	
	public function __construct(array $request, \CentralApps\Authentication\UserFactoryInterface $user_factory, \CentralApps\Authentication\UserGateway $user_gateway)
	{
		$this->request = $request;
		$this->userFactory = $user_factory;
		$this->userGateway = $user_gateway;
		
		if(is_array($request) && array_key_exists('get') && is_array($request['get'])) {
			$this->get = $request['get'];
		}
		if(is_array($request) && array_key_exists('session') && is_array($request['session'])) {
			$this->session = $request['session'];
		}
		
		$this->lookupPersistantOAuthTokenDetails();
	}
	
	protected function lookupPersistantOAuthTokenDetails()
	{
		if(!$this->persistantTokensLookedUp) {
			$this->oAuthToken = (isset($this->session['oauth_token'])) ? $this->session['oauth_token'] : null;
			$this->oAuthTokenSecret = (isset($this->session['oauth_token_secret'])) ? $this->session['oauth_token_secret'] : null;
		}
		$this->persistantTokensLookedUp = true;
	}
	
	protected function persistOAuthToken($token)
	{
		$_SESSION['oauth_token'] = $token;
	}
	
	protected function clearPersistedTokens()
	{
		unset($_SESSION['oauth_token']);
		unset($_SESSION['oauth_token_secret']);
	}
	
	protected function persistOAuthTokenSecret($secret)
	{
		$_SESSION['oauth_token_secret'] = $secret;
	}
	
	public function hasAttemptedToLoginWithProvider()
	{
		return $this->hasAttemptedToPerformActionWithState('twitter-login');
	}
	
	public function isAttemptingToAttach()
	{
		return $this->hasAttemptedToPerformActionWithState('twitter-attach');
	}
	
	public function isAttemptingToRegister()
	{
		return $this->hasAttemptedToPerformActionWithState('twitter-register');
	}
	
	protected function hasAttemptedToPerformActionWithState($state)
	{
		$this->lookupPersistantOAuthTokenDetails();
		if(isset($this->get['oauth_verifier']) && isset($this->get['state']) && $this->get['state'] == $state && !is_null($this->oAuthToken) && ! is_null($this->oAuthTokenSecret)) {
			return true;
		}
		return false;
	}
	
	public function processLoginAttempt()
	{
		$connection = $this->getTwitterAPIConnection();
		$this->clearPersistedTokens();
		$token_credentials = $connection->getAccessToken($this->get['oauth_verifier']);
		$this->oAuthToken = $token_credentials['oauth_token'];
		$this->oAuthTokenSecret = $token_credentials['oauth_token_secret'];
		$content = $connection->get('account/verify_credentials');
		if($connection->http_code != 200) {
			return null;
		}
		$this->externalId = $content['id'];
		try {
 			 return $this->userFactory->getFromProvider($this);
		} catch (\Exception $e) {
			return null;
		}
		return null;
	}
	
	public function logout()
	{
		return true;
	}
	
	public function userWantsToBeRemembered()
	{
		return false;
	}
	
	public function shouldPersist()
	{
		return true;
	}
	public function getProviderName()
	{
		return 'twitter';	
	}
	
	public function getTokens()
	{
		return array('oauth_token' => $this->oAuthToken, 'oauth_token_secret' => $this->oAuthTokenSecret);
	}
	
	public function getExternalId()
	{
		return $this->externalId;
	}
	
	protected function getTwitterAPIConnection()
	{
		if(!is_null($this->oAuthToken) && !is_null($this->oAuthTokenSecret)) {
			$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret, $this->oAuthToken, $this->oAuthTokenSecret);
		} else {
			$connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret);
		}
		$connection->host = "https://api.twitter.com/1.1/";
		return $connection;
	}
	
	public function getLoginUrl()
	{
		return $this->buildRedirectUrl() . "state=twitter-login";	
	}
	
	public function getRegisterUrl()
	{
		return $this->buildRedirectUrl() . "state=twitter-register";	
	}
	
	public function getAttachUrl()
	{
		return $this->buildRedirectUrl() . "state=twitter-attach";	
	}
	
	protected function buildRedirectUrl()
	{
		$connection = $this->getTwitterAPIConnection();
		$callback = $this->callbackPage . parse_url($this->callbackPage, PHP_URL_QUERY) ? "?" : "";
		$request_token = $connection->getRequestToken($callback);
		$this->persistOAuthToken($request_token['oauth_token']);
		$this->persistOAuthTokenSecret($request_token['oauth_token_secret']);
		$redirect_url = $connection->getAuthorizeURL($request_token['oauth_token']);
		$glue = parse_url($this->callbackPage, PHP_URL_QUERY) ? "?" : "&";
		return $redirect_url . $glue;
	}

}
