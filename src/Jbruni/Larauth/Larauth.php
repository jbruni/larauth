<?php namespace Jbruni\Larauth;

use Illuminate\Support\Facades\Facade;

class Larauth extends Facade {

	protected static function getFacadeAccessor() { return 'larauth'; }

}
