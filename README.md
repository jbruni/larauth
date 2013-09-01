Installation
============

Use [composer], at the root of your laravel project:

    composer require jbruni/larauth

And then add the service provider to the `providers` array at `app/config/app.php`:

    'Jbruni\Larauth\AuthServiceProvider',

It is recommended to add an alias to the `aliases` array at the same file:

    'Larauth' => 'Jbruni\Larauth\Larauth',

Helper Functions
================

Generate a **random password** (uses [Wordpress]-based code):

    $password = Larauth::randomPassword();

Generate a **password hash** (uses Solar Designer's [Portable PHP password hashing framework]):

    $hashed = Larauth::passwordHash('plain');

Validate an **email address** (uses Dominic Sayer's [is_email function]):

    $isValidEmail = Larauth::validateEmail($email);

Note: These are not static methods. We are using Laravel's Facade feature.

Authentication
==============

Larauth authentication implementation is as secure and safe as the Wordpress one, because it is based on Wordpress authentication implementation. It does NOT make usage of Sessions of any kind (just like Wordpress).

Larauth, as usual, authenticates a user through a *cookie*.

The **authenticate** method checks the authentication cookie and returns **false** if it does not exist or is invalid, or a **string** containing the user identification (email, by default) if it is valid.

    $loggedInUserEmail = Larauth::authenticate();

In order to Larauth perform its duty, you need to provide a callable function which **receives the user identification** as parameter (email, username, or what you prefer) and **returns the stored hashed password** for it. There are three ways to provide this function:

**(1)** Provide a callable function for the **hash_getter** configuration.

    Config::set('larauth::hash_getter', function ($email) {
        $result = User::where('email', $email)->first(array('password'));
        return (is_object($result) ? $result->password : '');
    });

Of course, you can set a string or array value in the configuration file, pointing to the callable getter function or method.

**(2)** Implement a **getHash** function in your **user model class**. 

    class User extends Eloquent {
        static public function getHash($email) {
            $result = static::where('email', $email)->first(array('password'));
            return (is_object($result) ? $result->password : '');
        }
    }

**(3)** Larauth alone will be able to get the password hash, as long as your model class extends Eloquent, and you provide **user_class**, **db_login** and **db_password** configurations.

    'user_class'  => 'Subscriber',   // defaults to "User"
    'db_login'    => 'nickname',     // defaults to "email"
    'db_password' => 'hash',         // defaults to "password"

So, if your user model class is named "Subscriber", the login field is "nickname" and the hashed password field is "hash", Larauth will be able to get the hash from the nickname on its own.

---

> If your model table already follow the defaults ("**User**", "**email**", and "**password**"), you don't need to configure anything. Authentication just works out of the box.

---

`>>> Larauth does not provide or enforce any specific **user model class** implementation or interface of its own!  <<<`

Login
=====

So, you already know how to check the **logged in user**. Just call `Larauth::authenticate()` to get the user "email" (or "username" or any other field specified at `db_login` configuration).

But how do you log in a user?



  [composer]: http://getcomposer.org
  [Wordpress]: http://wordpress.org
  [Portable PHP password hashing framework]: http://www.openwall.com/phpass/
  [is_email function]: http://isemail.info/about
