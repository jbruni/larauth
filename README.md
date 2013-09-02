<a id="requirements"></a>Requirements
============

**(1) User Model Class**

Larauth **does not provide** or enforce any specific **user model class** implementation or interface of its own!
 
No migrations, no interface, no abstract class. It's **your** user model class.
 
However, **your user model class must extend Eloquent**. 
 
This is an important requirement, which we may remove at a later time, or not.
 
Read in the [Configuration](#configuration) section how to specify your user model class name (by default, "User"), and the credential field names (by default, "email" and "password").

**(2) User Password**

You need to store your user's passwords using Larauth hashing mechanism.
 
Apply the provided `passwordHash` function before saving the user password:

    $user->password = Larauth::passwordHash(Input::get('password'));

Hopefully, you are choosing your user management strategy before having a user database!

<a id="installation"></a>
Installation
============

Use [composer](http://getcomposer.org), at the root of your laravel project:

    composer require jbruni/larauth

And then add the service provider to the `providers` array at `app/config/app.php`:

    'Jbruni\Larauth\AuthServiceProvider',

It is recommended to add an alias to the `aliases` array at the same file:

    'Larauth' => 'Jbruni\Larauth\Larauth',

<a id="helper"></a>
Helper Functions
================

Generate a **random password** with `randomPassword` (uses [Wordpress](http://wordpress.org)-based code):

    // A simple helper to generate random passwords
    $password = Larauth::randomPassword();

Generate a **password hash** with `passwordHash` (uses Solar Designer's [Portable PHP password hashing framework](http://www.openwall.com/phpass/)):

    // With Larauth, you'll need to use this hasher to store your user's password
    $user->password = Larauth::passwordHash('plain');

Validate an **email address** with `validateEmail` (uses Dominic Sayer's [is_email function](http://isemail.info/about)):

    // Best email validator ever
    $isValidEmail = Larauth::validateEmail($email);

Note: These are not static methods. We are using Laravel's Facade feature.

<a id="login"></a>
Login
=====

To perform current user login, just provide the credentials to the `login` method:

    $logged = Larauth::login('contato@jbruni.com.br', 'supersecretpassword');
    if (!$logged) { echo Larauth::getLoginError(); }

To perform current user login without providing password, use `loginUser` method:

    Larauth::loginUser('jbruni@example.com');

A successfull login also fires the `larauth.login` event, which you can listen to:

    Event::listen('larauth.login', function($user) { echo $user->name . ' logged in!'; });

<a id="logout"></a>
Logout
======

To perform current user logout, just call `logout`:

    Larauth::logout();

To logout any user, call `logoutUser`:

    Larauth::logoutUser('jbruni@example.com');

A successfull logout fires the `larauth.logout` event, which you can listen to:

    Event::listen('larauth.logout', function($user) { echo $user->name . ' logged out!'; });

<a id="user"></a>
Logged In User
==============

To get the currently logged in user requesting your application, just call `user`:

    $user = Larauth::user();
    if (empty($user)) { echo 'Nobody logged in: ' . Larauth::authError(); }

This is the most important and useful function.

<a id="security"></a>
Security
========

Larauth authentication implementation is **as secure and safe as the Wordpress** one, because it is based on Wordpress authentication implementation. Just like Wordpress, it does NOT use Session (this surprised me when I first acknowledged it).

It uses strong hash, temporary random salt which depends on login time, and you simply does not need to bother researching on how you will deal with this stuff. If worldwide heavily used Wordpress uses this authentication strategy, it is certainly well tested.

(By the way, I don't like Wordpress code in general. I like Laravel code. I just extracted this good part from there and made it available for myself and everyone else through this package I'm sharing.)

<a id="configuration"></a>
Configuration
=============

The most important configuration options are `user_class`, `db_login` and `db_password`:

    'user_class'  => 'Subscriber',   // defaults to "User"
    'db_login'    => 'nickname',     // defaults to "email"
    'db_password' => 'hash',         // defaults to "password"

Larauth needs to know your **user model class name** at `user_class`.

Generally, the email field is used as the user identifier at authentication credentials, but it can be "username", "userId", or any other field of your model. Just configure `db_login` to Larauth know it.

The password field - stored as hash - is usually named "password", but your database may be in another language, or by whatever other reason have other name. So, configure `db_password` informing your user model password field.

---

> If your model table already follow the defaults ("User", "email", and "password"), you don't need to configure anything. Authentication just works out of the box.

---

Below, all the other currently available configuration options, with their respective defaults. Explanation follows.

	'login' => 'email',
	'username_regex' => '/^[A-Za-z0-9._-]{5,60}$/',
	'password_regex' => '/^[A-Za-z0-9!@#$%^*()._-]{5,60}$/',
	'logtime_regex' => '/[0-9]{10}/',
    'hash_getter' => '',
	'user_getter' => '',
	'cookie' => array(
		'name'     => 'larauth',
		'expire'   => 0,
		'path'     => '/',
		'domain'   => '',
		'secure'   => false,
		'httponly' => true
    ),

`login` - This configuration accepts only two values: `email` and `username`; it is used internally for validation of both the cookie and login credentials; if your login is done using email, don't change it. If it is done through *anything else*, change it to `username`.

`username_regex` - This configuration is used to validate the "username" field (at cookie and at login). If you want to accept anything, just change it to `//` (an empty regular expression), or modify it according to your own needs and rules.

`password_regex` - Same thing as "username_regex" (read above), but used to validate the plain text password provided at the authentication credentials.

`logtime_regex` - Same as the other "regex" options, used to validate the timestamp in the authentication cookie. Don't change this one.

`cookie` - This is the "template" for the authentication cookie. You can change the cookie attributes at your will. We do not recommend to change anything, except the name if you like, and the **expire** attribute, at runtime, to provide an expiration time for the user session:

    $cookie = Config::get('larauth::cookie');
    $cookie['expire'] =  time() + (3600 * 24); // 24 hours from now
    Config::set('larauth::cookie', $cookie);

(As I am personally using "forever" type of cookies - cookies without expiration - ... so, there is no easier way to change the cookie expiration time in the current version; but it is certainly an easy thing to implement.)

Finally, `hash_getter` and `user_getter` configuration settings need to be [callable](http://php.net/manual/en/language.types.callable.php) methods or functions. Usually, you will not use them. Read below for more information.

<a id="authentication"></a>
Authentication
==============

Larauth, as usual, authenticates a user through a *cookie*.

In order to perform its duty, Larauth needs to **get the password hash** having already the **user identification** provided through either the authentication cookie or the login credentials.

How does Larauth obtain the hash? It performs a query in the model, using `user_class`, `db_login` and `db_password`. Using SQL, it would be something like:

    SELECT db_password FROM user_class WHERE db_login = "provided login";

But it is possible to **provide the hash** for the given user identification through two other ways:

**(1)** Providing a callable function for the `hash_getter` configuration. For example:

    Config::set('larauth::hash_getter', function ($email) {
        $result = User::where('email', $email)->first(array('password'));
        return (is_object($result) ? $result->password : '');
    });

Of course, you can set a string or array value in the configuration file, pointing to the callable getter function or method.

(The example above does exactly what Larauth would do internally, on its own.)

**(2)** Implement a `getHash` static function in your **user model class**. 

    class User extends Eloquent {
        static public function getHash($email) {
            $result = static::where('email', $email)->first(array('password'));
            return (is_object($result) ? $result->password : '');
        }
    }

(Again, the specific example is useless, anyway it shows how you provide a "**getHash**" hash getter function with no configuration.)

-----

Similarly, Larauth provides the authenticated User instance basically through a call like this:

    $user_class::where($db_login, 'user identification')->first();

But, if you need or want a more complex way to obtain the user instance, you have the same alternatives as above:

**(1)** Provide a callable function for the `user_getter` configuration. The function will receive the user identification as parameter (email, username, or whatever has been configured), and needs to return the instantiated user object. Check the `hash_getter` example above.

**(2)** Implement a `getUser` static function in your **user model class**, working as just described in the previous item.

<a id="authorization"></a>
Authorization
=============

Coming soon.

<a id="thanks"></a>
Thank You
=========

I am always extremely busy doing lots of stuff, as many others in these days. I expect the bankers change their hearts and minds so we don't need to sacrifice so much of our lifes in order to earn a few crumbs of their huge criminal wealth. While this does not happen, I really do not have much spare time as it may seem, to keep improving my open source initiatives as I would like.

So I thank you for your comprehension.

Anyway, I would enjoy a lot your feedback, and I would enjoy a lot to improve Larauth based on it, if I can!

Thank you, again.

[NOTE: I want to share the lines of my application code showing how I am myself actually using Larauth. Minimal code, and my authentication needs satisfied by Larauth! CYO!]
