<?php namespace Jbruni\Larauth;

/**
 * Manage User Authentication (Login)
 * 
 * @author J. Bruni - based on Wordpress 3 authentication
 */
class AuthManager {

	/**
	 * Random number generation seed
	 * 
	 * @var float
	 */
	public $seed;

	/**
	 * The application instance.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * Authentication error message.
	 *
	 * @var string
	 */
	protected $authError = '';

	/**
	 * Login error message.
	 *
	 * @var string
	 */
	protected $loginError = '';

	/**
	 * Login time
	 *
	 * @var integer
	 */
	protected $loginTime = 0;

	/**
	 * Check password on login
	 *
	 * @var boolean
	 */
	protected $checkPassword = true;

	/**
	 * Authenticated user
	 *
	 * @var string
	 */
	protected $authenticatedUser;


	/**
	 * Initialize the random number generation seed
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 */
	public function __construct($app)
	{
		$this->app = $app;
		$this->seed = mt_rand();
	}

	/**
	 * Get a password hash
	 * 
	 * @param string $password   Password
	 * @return string|boolean   Hashed password
	 */
	public function passwordHash( $password )
	{
		$hasher = new PasswordHash( 8, false );
		$passHash = $hasher->HashPassword( $password );

		if ( strlen( $passHash ) < 20 )
		{
			throw new AuthHashFailureException;
		}

		return $passHash;
	}

	/**
	 * Validate a username
	 * 
	 * @param string $username   Username
	 * @return boolean   True if the username name is valid, according to "username_regex" configuration
	 */
	public function validateUsername( $username )
	{
		return ( preg_match( $this->getConfig( 'username_regex' ), $username ) === 1 );
	}

	/**
	 * Validate an email address
	 * 
	 * @param string $email   Email
	 * @return boolean   True if the email is valid
	 */
	public function validateEmail( $email )
	{
		return IsEmail::check( $email );
	}

	/**
	 * Validate a password
	 * 
	 * @param string $password   Password
	 * @return boolean   True if the password is valid, according to "password_regex" configuration
	 */
	public function validatePassword( $password )
	{
		return ( preg_match( $this->getConfig( 'password_regex' ), $password ) === 1 );
	}

	/**
	 * Validate a login time
	 * 
	 * @param string $logtime   Login time
	 * @return boolean   True if the time is valid, according to "logtime_regex" configuration
	 */
	public function validateLoginTime( $logtime )
	{
		return ( preg_match( $this->getConfig( 'logtime_regex' ), $logtime ) === 1 );
	}

	/**
	 * Authenticate the user, based on the authentication cookie
	 * 
	 * @return string|boolean   User identification (usually username or email); False on failure
	 */
	public function authenticate()
	{
		$cookie = $this->getConfig( 'cookie' );

		$this->loginTime = 0;

		if ( empty( $_COOKIE[$cookie['name']] ) )
		{
			$this->authError = 'Not logged in.';
			return false;
		}

		$authCookie = \Cookie::get($cookie['name']);

		$parts = explode( '|', $authCookie );

		if ( count( $parts ) != 3 )
		{
			$this->authError = 'Malformed authentication token.';
			return false;
		}

		list( $login, $logtime, $cookieHash ) = $parts;

		$loginType = $this->getLoginType();

		if ( !call_user_func( array( $this, 'validate' . ucfirst( $loginType ) ), $login ) )
		{
			$this->authError = 'Invalid login.';
			return false;
		}

		if ( !$this->validateLoginTime( $logtime ) )
		{
			$this->authError = 'Invalid login time.';
			return false;
		}

		$hash = call_user_func( $this->getCallableHashGetter(), $login );

		if ( !is_string( $hash ) )
		{
			$this->authError = 'User account not found.';
			return false;
		}

		if ( strlen( $hash ) < 20 )
		{
			$this->authError = 'Unable to verify user credentials.';
			return false;
		}

		if ( $authCookie != $this->getCookieValue( $logtime, $login, $hash ) )
		{
			$this->authError = 'Invalid authentication token.';
			return false;
		}

		$this->authError = '';
		$this->loginTime = $logtime;

		return $login;
	}

	/**
	 * Set the authentication cookie to be sent
	 * 
	 * @param string $value   Authentication token
	 */
	public function setAuthCookie( $value = null )
	{
		$cookie = $this->getConfig( 'cookie' );

		if ( !empty( $value ) )
		{
			$cookie['value'] = $value;
		}

		$this->app['larauth.cookie'] = $cookie;
	}

	/**
	 * Set the authentication coookie to be cleared
	 * 
	 */
	public function clearAuthCookie()
	{
		$cookie = $this->getConfig( 'cookie' );

		$cookie['value'] = '';
		$cookie['expire'] = 281714100;

		$this->app['larauth.cookie'] = $cookie;
	}

	/**
	 * Get the authentication cookie value
	 * 
	 * @param integer $time   Login time
	 * @param string $login   User login
	 * @param string $hash   User hashed password
	 * @return string   Authentication cookie value
	 */
	public function getCookieValue( $time, $login, $hash )
	{
		$frag   = substr( $hash, 7, 6 );
		$salt   = $this->salt( $login );
		$key    = hash_hmac( 'md5', $login . $frag . '|' . $time, $salt );
		$secret = hash_hmac( 'md5', $login . '|' . $time, $key );

		return $login . '|' . $time . '|' . $secret;
	}

	/**
	 * Get salt string (returns the same value for the same name while logged in)
	 * 
	 * @param string $name   Salt "name" (user login)
	 * @return string   Salt string
	 */
	public function salt( $name = '' )
	{
		$saltKey = 'larauth.salt_' . $name;
		$salt    = $this->app->make( 'cache' )->get( $saltKey );

		if ( empty( $salt ) )
		{
			$salt = $this->randomPassword( 64, true, true );
			$this->app->make( 'cache' )->forever( $saltKey, $salt );
		}

		return $this->app->make( 'config' )->get( 'app.key' ) . $salt;
	}

	/**
	 * Get a random generated password
	 * 
	 * @param integer $length   Length of the password
	 * @param boolean $specialChars   Allow inclusion of special characters or not 
	 * @param boolean $extraSpecialChars   Allow inclusion of extra special characters or not
	 * @return string   Generated password
	 */
	public function randomPassword( $length = 12, $specialChars = true, $extraSpecialChars = false )
	{
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
			. ( $specialChars ? '!@#$%^*()' : '' )
			. ( $extraSpecialChars ? '&-_ []{}<>~`+=,.;:/?|' : '' );

		$max = strlen( $chars ) - 1;

		$password = '';
		for ( $i = 0; $i < $length; $i++ )
		{
			$password .= $chars[$this->randomInteger( 0, $max )];
		}

		return $password;
	}

	/**
	 * Generate a random number
	 * 
	 * @param integer $min   Minimum value
	 * @param integer $max   Maximum value
	 * @return integer   Random number
	 */
	public function randomInteger( $min = 0, $max = 108 )
	{
		$rndValue = md5( uniqid( microtime() . mt_rand(), true ) . $this->seed );
		$rndValue .= sha1( $rndValue );
		$rndValue .= sha1( $rndValue . $this->seed );
		$this->seed = md5( $this->seed . $rndValue );

		$value = substr( $rndValue, 0, 8 );
		$value = abs( hexdec( $value ) );
		$value = $min + ( ($max - $min + 1 ) * ( $value / ( 4294967295 + 1 ) ) );
		return abs( intval( $value ) );
	}

	/**
	 * Get the "login" configuration
	 * 
	 * @return string   Either "username" or "email"
	 */
	public function getLoginType()
	{
		return ( $this->getConfig( 'login' ) == 'email' ? 'email' : 'username' );
	}

	/**
	 * Get a callable function to obtain the Password Hash from a user
	 * Either defined by "hash_getter" configuration, 
	 * or "getHash" method at the user model class,
	 * or finally the "getHash" method of this class.
	 * 
	 * @return callable   Hash getter function
	 */
	public function getCallableHashGetter()
	{
		return $this->getCallable( 'hash_getter', 'getHash' );
	}

	/**
	 * Get a callable function to obtain the Password Hash from a user
	 * Either defined by "hash_getter" configuration, 
	 * or "getHash" method at the user model class,
	 * or finally the "getHash" method of this class.
	 * 
	 * @return callable   Hash getter function
	 */
	public function getCallableUserGetter()
	{
		return $this->getCallable( 'user_getter', 'getUser' );
	}

	/**
	 * Get a callable function
	 * Either defined by a specific configuration, 
	 * or a specific method at the user model class,
	 * or finally the same named method of this class.
	 * 
	 * @return callable   Callable function
	 */
	protected function getCallable( $config_name, $method_name )
	{
		$function = $this->getConfig( $config_name );

		if ( is_callable( $function ) )
		{
			return $function;
		}

		$function = array( $this->getConfig( 'user_class' ), $method_name );

		if ( is_callable( $function ) && method_exists( $this->getConfig( 'user_class' ), $method_name ) )
		{
			return $function;
		}

		return array( $this, $method_name );
	}

	/**
	 * Get the Password Hash of a user
	 * Configured "user_class" must extend Eloquent
	 * 
	 * @param string $login   Login data (corresponds to configured "db_login" database field)
	 * @return boolean|string   Password Hash string or False on failure (corresponds to configured "db_password" database field)
	 */
	public function getHash( $login )
	{
		$function = array( $this->getConfig( 'user_class' ), 'where' );

		if ( !is_callable( $function ) )
		{
			return false;
		}

		$loginField = $this->getConfig( 'db_login' );
		$passwordField = $this->getConfig( 'db_password' );

		$result = call_user_func( $function, $loginField, '=', $login )->first( array( $passwordField ) );

		if ( !is_object( $result ) )
		{
			return false;
		}

		return $result->$passwordField;
	}

	/**
	 * Get a user
	 * Configured "user_class" must extend Eloquent
	 * 
	 * @param string $login   Login data (corresponds to configured "db_login" database field)
	 * @return boolean|object   User instance; false on failure
	 */
	public function getUser( $login )
	{
		$function = array( $this->getConfig( 'user_class' ), 'where' );

		if ( !is_callable( $function ) )
		{
			return false;
		}

		$loginField = $this->getConfig( 'db_login' );

		$result = call_user_func( $function, $loginField, '=', $login )->first();

		if ( !is_object( $result ) )
		{
			return false;
		}

		return $result;
	}

	/**
	 * Get a specific Larauth configuration
	 * 
	 * @param string $config   Configuration key
	 * @return string   Configuration value
	 */
	public function getConfig( $config )
	{
		return $this->app->make( 'config' )->get( 'larauth::' . $config );
	}

	/**
	 * Get authentication error message
	 * 
	 * @return string   Authentication error message
	 */
	public function getAuthError()
	{
		return $this->authError;
	}

	/**
	 * Get login error message
	 * 
	 * @return string   Login error message
	 */
	public function getLoginError()
	{
		return $this->loginError;
	}

	/**
	 * Get login time
	 * 
	 * @return integer   Login time
	 */
	public function getLoginTime()
	{
		return $this->loginTime;
	}

	/**
	 * Get authenticated user
	 * 
	 * @return boolean|object   Authenticated user instance; false on failure
	 */
	public function user()
	{
		if ( !empty( $this->authenticatedUser ) )
		{
			return $this->authenticatedUser;
		}

		$login = $this->authenticate();

		if ( empty( $login ) )
		{
			return false;
		}

		$user = call_user_func( $this->getCallableUserGetter(), $login );

		if ( empty( $user ) )
		{
			return false;
		}

		$this->authenticatedUser = $user;

		return $this->authenticatedUser;
	}

	/**
	 * Perform current user login by checking credentials
	 *
	 * @param string $login   User login
	 * @param string $password   User password
	 * @return boolean   True on success; False on failure
	 */
	public function login( $login, $password )
	{
		$loginType = $this->getLoginType();

		if ( !call_user_func( array( $this, 'validate' . ucfirst( $loginType ) ), $login ) )
		{
			$this->loginError = 'Invalid login.';
			return false;
		}

		if ( $this->checkPassword && !$this->validatePassword( $password ) )
		{
			$this->loginError = 'Invalid password.';
			return false;
		}

		$hash = call_user_func( $this->getCallableHashGetter(), $login );

		if ( !is_string( $hash ) )
		{
			$this->loginError = 'User account not found.';
			return false;
		}

		if ( strlen( $hash ) < 20 )
		{
			$this->loginError = 'Unable to verify user credentials.';
			return false;
		}

		$hasher = new PasswordHash( 8, false );

		if ( $this->checkPassword && !$hasher->CheckPassword( $password, $hash ) )
		{
			$this->loginError = 'Wrong password.';
			return false;
		}

		$user = call_user_func( $this->getCallableUserGetter(), $login );

		if ( empty ( $user ) )
		{
			$this->loginError = 'User account not found.';
			return false;
		}

		$time  = time();
		$token = $this->getCookieValue( $time, $login, $hash );
		$this->setAuthCookie( $token );

		$this->loginError = '';
		$this->loginTime = $time;

		$this->authenticatedUser = $user;

		$this->app->make('events')->fire('larauth.login', array($user));

		return true;
	}

	/**
	 * Perform current user login without checking password
	 *
	 * @param string $login   User login
	 * @return boolean   True on success; False on failure
	 */
	public function loginUser( $login )
	{
		$this->checkPassword = false;

		$result = $this->login( $login, null );

		$this->checkPassword = true;

		return $result;
	}

	/**
	 * Perform logout of current user
	 */
	public function logout()
	{
		$loginField = $this->getConfig( 'db_login' );

		if ( empty( $login ) && empty( $this->authenticatedUser->$loginField ) )
		{
			return;
		}

		$user    = $this->authenticatedUser;
		$name    = $user->$loginField;
		$saltKey = 'larauth.salt_' . $name;

		// Logout = remove random salt + clear cookie
		// One being successfull is enough
		$this->app->make( 'cache' )->forget( $saltKey );
		$this->clearAuthCookie();

		$this->authenticatedUser = null;

		$this->app->make('events')->fire('larauth.logout', array($user));
	}

	/**
	 * Perform logout of specified user
	 * 
	 * @param string $login   Login data (corresponds to configured "db_login" database field)
	 */
	public function logoutUser( $login )
	{
		// By removing the random salt,
		// it is impossible to authenticate the user.
		$saltKey = 'larauth.salt_' . $login;
		$this->app->make( 'cache' )->forget( $saltKey );
	}

	public function getHasher()
	{
			return new PasswordHash( 8, false );
	}
}

?>
