<?php namespace Model\Paypal;

use Model\Core\Module_Config;
use Model\Payments\PaymentsOrderInterface;

class Config extends Module_Config
{
	/**
	 */
	protected function assetsList(): void
	{
		$this->addAsset('config', 'config.php', function () {
			return '<?php
$config = [
	\'email\' => \'your@email.com\',
	\'test\' => false,
	\'token\' => \'your-pdt-token\',
];
';
		});
	}

	public function getConfigData(): ?array
	{
		return [];
	}
}
