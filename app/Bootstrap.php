<?php declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
	public static function boot(): Configurator
	{
		$configurator = new Configurator();
		$rootDir = dirname(__DIR__);

		if (getenv('NETTE_DEVEL') === '1') {
			$configurator->setDebugMode(true);
		}
		$configurator->enableTracy($rootDir . '/var/log');

		setlocale(LC_ALL, 'cs_CZ.utf8');
		$configurator->setTimeZone('Europe/Prague');
		$configurator->setTempDirectory($rootDir . '/var/temp');

		$configurator->addConfig($rootDir . '/config/common.neon');
		$configurator->addConfig($rootDir . '/config/local.neon');

		return $configurator;
	}
}
