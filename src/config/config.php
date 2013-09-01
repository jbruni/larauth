<?php

return array(

	/**
	 * string   User model class
	 */
	'user_class' => 'User',

	/**
	 * string   Authenticate using "username" or "email"
	 */
	'login' => 'email',

	/**
	 * string   User login field in your database
	 */
	'db_login' => 'email',

	/**
	 * string   User password hash field in your database
	 */
	'db_password' => 'password',

	/**
	 * string   Hash getter function
	 */
	'hash_getter' => '',

	/**
	 * string   User getter function
	 */
	'user_getter' => '',

	/**
	 * string   Regular expression to validate user names
	 */
	'username_regex' => '/^[A-Za-z0-9._-]{5,60}$/',

	/**
	 * string   Regular expression to validate passwords
	 */
	'password_regex' => '/^[A-Za-z0-9!@#$%^*()._-]{5,60}$/',

	/**
	 * string   Regular expression to validate login time
	 */
	'logtime_regex' => '/[0-9]{10}/',

	/**
	 * array   Authentication cookie settings
	 */
	'cookie' => array(
		'name'     => 'larauth',
		'value'    => '',
		'expire'   => 0,
		'path'     => '/',
		'domain'   => '',
		'secure'   => false,
		'httponly' => true
	),

);
