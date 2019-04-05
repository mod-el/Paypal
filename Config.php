<?php namespace Model\Paypal;

use Model\Core\Module_Config;

class Config extends Module_Config
{
	/**
	 */
	protected function assetsList()
	{
		$this->addAsset('config', 'config.php', function () {
			return '<?php
$config = [
	\'path\' => \'paypal\',
	\'email\' => \'your@email.com\',
	\'test\' => false,
	\'token\' => \'your-pdt-token\',
	\'template\' => \'paypal-confirm\',
];
';
		});
		if (!file_exists(INCLUDE_PATH . 'app/config/Paypal/PaypalCheck.php'))
			copy(INCLUDE_PATH . 'model/Paypal/PaypalCheckSample.php', INCLUDE_PATH . 'app/config/Paypal/PaypalCheck.php');
	}

	/**
	 * @return array
	 */
	public function getRules(): array
	{
		return [
			'rules' => [
				'paypal' => 'paypal',
			],
			'controllers' => [
				'Paypal',
			],
		];
	}

	public function postUpdate_0_0_2()
	{
		if (file_exists(INCLUDE_PATH . 'app/config/class.php')) {
			$code = file_get_contents(INCLUDE_PATH . 'app/config/class.php');
			$code = str_replace('class Paypal extends PaypalBase', 'class PaypalCheck extends PaypalCheckBase', $code);
			$code = preg_replace('/function verifyOrder.+/', 'function verifyOrder\(string $id, float $tot\): int', $code);
			$code = preg_replace('/function execute.+/', 'function execute\(array $response, string $type\)', $code);
			$code = preg_replace('/function alreadyExecuted.+/', 'function alreadyExecuted\(array $response, string $type\)', $code);
			file_put_contents(INCLUDE_PATH . 'app/config/Paypal/PaypalCheck.php', $code);
			unlink(INCLUDE_PATH . 'app/config/class.php');
		}
	}
}
