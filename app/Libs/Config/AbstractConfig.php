<?php declare(strict_types=1);

namespace App\Libs\Config;

use Nette\Utils\ArrayHash;

/**
 * Abstract config for Config object used in facades, services, etc.
 */
abstract class AbstractConfig extends ArrayHash
{
	public function __construct(array $arr)
	{
		foreach ($arr as $key => $value) {
			$this->$key = is_array($value)
				? ArrayHash::from($value, true)
				: $value;
		}
	}
}
