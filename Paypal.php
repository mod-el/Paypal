<?php namespace Model\Paypal;

use Model\Core\Module;

class Paypal extends Module
{
	/** @var array */
	public $formData;
	/** @var */
	public $returnTemplate = false;
	/** @var string */
	protected $email;
	/** @var string */
	protected $path;
	/** @var string */
	protected $token;

	/** @var bool */
	protected $test = false;

	/** @param array $data */
	public function init(array $data)
	{
		require(INCLUDE_PATH . 'app/config/Paypal/config.php');

		$this->formData = [
			'cmd' => '_xclick',
			'business' => $config['email'],
			'item_name' => '',
			'item_number' => '',
			'quantity' => false,
			'currency_code' => 'EUR',
			'amount' => 0,
			'shipping' => false,
			'no_shipping' => 1,
			'no_note' => 1,
		];
		$this->email = $config['email'];
		$this->path = $config['path'];
		$this->test = $config['test'];
		$this->token = $config['token'] ?? false;
		$this->returnTemplate = $config['template'] ?? false;

		foreach ($data as $k => $v)
			$this->formData[$k] = $v;

		if (!isset($this->formData['cancel_return']))
			$this->formData['cancel_return'] = BASE_HOST . PATH . $this->path . '/return';
	}

	/**
	 * @return array
	 */
	public function getFormData(): array
	{
		$response = [];

		foreach ($this->formData as $k => $v) {
			if ($v === false)
				continue;
			if ($k === 'amount')
				$v = round($v, 2);

			$response[$k] = $v;
		}

		return $response;
	}

	/**
	 *
	 */
	public function buy()
	{
		if ($this->test) $url = 'https://www.sandbox.paypal.com/it/cgi-bin/webscr';
		else $url = 'https://www.paypal.com/it/cgi-bin/webscr';
		echo '<form action="' . $url . '" name="PayPalForm" method="post">';

		$formData = $this->getFormData();
		foreach ($formData as $k => $v)
			echo '<input type="hidden" name="' . $k . '" value="' . htmlentities($v, ENT_QUOTES, 'utf-8') . '" />';

		echo '<noscript><input type="image" src="http://www.paypal.com/it_IT/i/btn/x-click-but01.gif" name="submit" alt="Effettua i tuoi pagamenti con PayPal. &Egrave; un sistema rapido, gratuito e sicuro." /><br /><br /></noscript>';
		echo '</form>';
		echo '<script type="text/javascript">document.PayPalForm.submit();</script>';
		die();
	}

	/**
	 * @param string $message
	 * @param bool $die
	 */
	private function log(string $message, bool $die = true)
	{
		$f = fopen(INCLUDE_PATH . 'model/Paypal/data/logs.php', 'a+');
		fwrite($f, $message . "\n-----------------------------\n");
		fclose($f);
		if ($die)
			die('Error.');
	}

	/**
	 * @param string $tx
	 * @return mixed
	 */
	public function returnData(string $tx)
	{
		if ($this->test) $url = 'https://www.sandbox.paypal.com/it/cgi-bin/webscr';
		else $url = 'https://www.paypal.com/it/cgi-bin/webscr';

		// Init cURL
		$request = curl_init();

		// Set request options
		curl_setopt_array($request, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query(
				[
					'cmd' => '_notify-synch',
					'tx' => $tx,
					'at' => $this->token,
				]),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CAINFO => INCLUDE_PATH . 'model/Paypal/files/cacert.pem',
		]);

		// Execute request and get response and status code
		$response = curl_exec($request);
		$status = curl_getinfo($request, CURLINFO_HTTP_CODE);

		// Close connection
		curl_close($request);

		################################################

		if ($status == 200 and strpos($response, 'SUCCESS') === 0) {
			// Remove SUCCESS part (7 characters long)
			$response = substr($response, 7);

			// URL decode
			$response = urldecode($response);

			// Turn into associative array
			preg_match_all('/^([^=\s]++)=(.*+)/m', $response, $m, PREG_PATTERN_ORDER);
			$response = array_combine($m[1], $m[2]);

			// Fix character encoding if different from UTF-8 (in my case)
			if (isset($response['charset']) and strtoupper($response['charset']) !== 'UTF-8') {
				foreach ($response as $key => &$value) {
					$value = mb_convert_encoding($value, 'UTF-8', $response['charset']);
				}
				$response['charset_original'] = $response['charset'];
				$response['charset'] = 'UTF-8';
			}

			// Sort on keys for readability (handy when debugging)
			ksort($response);

			#######################################

			$checker = $this->getChecker();
			switch ($checker->verifyOrder($response['item_number'], $response['mc_gross'])) {
				case 1:
					return $checker->execute($response, 'pdt');
					break;
				case 2:
					return $checker->alreadyExecuted($response, 'pdt');
					break;
			}
		} else {
			$this->model->error('Errore durante la verifica del pagamento. Contattare l\'amministrazione.<br />Status: <b>' . $status . '</b><br />Risposta dal server:<br /><br />' . print_r($response, true));
		}
	}

	/**
	 * @param array $ipn_data
	 * @return bool
	 */
	public function ipn(array $ipn_data)
	{
		if ($this->test)
			$url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		else
			$url = 'https://www.paypal.com/cgi-bin/webscr';

		// Set up request to PayPal
		$request = curl_init();
		curl_setopt_array($request, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query(['cmd' => '_notify-validate'] + $ipn_data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CAINFO => INCLUDE_PATH . 'model' . PATH_SEPARATOR . 'Paypal' . PATH_SEPARATOR . 'files' . PATH_SEPARATOR . 'cacert.pem',
		]);

		// Execute request and get response and status code
		$response = curl_exec($request);
		$status = curl_getinfo($request, CURLINFO_HTTP_CODE);

		// Close connection
		curl_close($request);

		if ($status != 200 or $response !== 'VERIFIED')
			$this->log("Errore: " . $status . "\nRisposta:\n" . $response);

		if (array_key_exists('charset', $ipn_data) and ($charset = $ipn_data['charset']) and $charset !== 'utf-8') {
			// Convert all the values
			foreach ($ipn_data as $key => &$value) {
				$value = mb_convert_encoding($value, 'utf-8', $charset);
			}

			// And store the charset values for future reference
			$ipn_data['charset'] = 'utf-8';
			$ipn_data['charset_original'] = $charset;
		}

		$response = $ipn_data;

		if ($response['payment_status'] !== 'Completed')
			$this->log("Pagamento non completato.");

		$checker = $this->getChecker();
		switch ($checker->verifyOrder($response['item_number'], $response['mc_gross'])) {
			case 1:
				return $checker->execute($response, 'ipn');
				break;
			case 2:
				return $checker->alreadyExecuted($response, 'ipn');
				break;
		}
	}

	/**
	 * @return PaypalCheck
	 */
	private function getChecker(): PaypalCheck
	{
		require_once(INCLUDE_PATH . 'app/config/Paypal/PaypalCheck.php');
		$checker = new PaypalCheck($this->model);
		return $checker;
	}

	/**
	 * @param array $request
	 * @param string $rule
	 * @return array|null
	 */
	public function getController(array $request, string $rule): ?array
	{
		return $rule === 'paypal' ? [
			'controller' => 'Paypal',
		] : null;
	}
}
