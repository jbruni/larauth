<?php namespace Jbruni\Larauth;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Cookie;

class AuthServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('jbruni/larauth');
		$this->registerAuthEvents();
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['larauth'] = $this->app->share(function($app)
		{
			return new AuthManager($app);
		});
	}

	/**
	 * Register the events needed for authentication.
	 *
	 * @return void
	 */
	protected function registerAuthEvents()
	{
		$app = $this->app;

		$app->after(function($request, $response) use ($app)
		{
			if (!empty($app['larauth.cookie']))
			{
				$cookie = $app['larauth.cookie'];
				$symfony_cookie = new Cookie($cookie['name'], $cookie['value'], $cookie['expire'], $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
				$response->headers->setCookie($symfony_cookie);
			}
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('larauth');
	}

}
