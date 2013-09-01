<?php namespace Jbruni\Larauth;

class AuthException extends \Exception {}

class AuthHashFailureException extends AuthException {

	protected $message = 'Failed to generate password hash.'; 

};

