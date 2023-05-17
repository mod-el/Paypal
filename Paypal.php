<?php namespace Model\Paypal;

use Model\Core\Module;
use Model\Payments\PaymentInterface;
use Model\Payments\PaymentsOrderInterface;

class Paypal extends Module implements PaymentInterface
{
	public function beginPayment(PaymentsOrderInterface $order, string $type, array $options = [])
	{
		$config = $this->retrieveConfig();

		if ($config['test'])
			$url = 'https://www.sandbox.paypal.com/it/cgi-bin/webscr';
		else
			$url = 'https://www.paypal.com/it/cgi-bin/webscr';

		$orderShipping = $order->getShipping();
		$orderPrice = $order->getPrice();

		if ($orderPrice > $orderShipping)
			$orderPrice -= $orderShipping;
		else
			$orderShipping = 0;

		$orderData = array_merge([
			'cmd' => '_xclick',
			'business' => $config['email'],
			'item_name' => $order->getOrderDescription(),
			'item_number' => $order['id'],
			'quantity' => false,
			'currency_code' => 'EUR',
			'amount' => $orderPrice,
			'shipping' => $orderShipping ?: false,
			'no_shipping' => $orderShipping ? false : 1,
			'no_note' => 1,
		], $options);

		switch ($type) {
			case 'client':
				return $orderData;
				break;
			case 'server':
				echo '<form action="' . $url . '" name="PayPalForm" method="post">';
				foreach ($orderData as $k => $v) {
					if ($v === false)
						continue;
					if ($k === 'amount')
						$v = round($v, 2);

					echo '<input type="hidden" name="' . $k . '" value="' . htmlentities($v, ENT_QUOTES, 'utf-8') . '" />';
				}
				echo '<noscript><input type="image" src="http://www.paypal.com/it_IT/i/btn/x-click-but01.gif" name="submit" alt="Effettua i tuoi pagamenti con PayPal. &Egrave; un sistema rapido, gratuito e sicuro." /><br /><br /></noscript>';
				echo '</form>';
				echo '<script>document.PayPalForm.submit();</script>';
				die();
				break;
		}
	}

	public function handleRequest(): array
	{
		switch ($this->model->getRequest(3)) {
			case 'return':
				if (!isset($_GET['tx']))
					throw new \Exception('Wrong parameters');

				return $this->returnData($_GET['tx']);
			case 'ipn':
				return $this->ipn($_POST);
			default:
				throw new \Exception('Unknown request type');
		}
	}

	/**
	 * @param string $tx
	 * @return mixed
	 */
	private function returnData(string $tx): array
	{
		$config = $this->retrieveConfig();

		if ($config['test'])
			$url = 'https://www.sandbox.paypal.com/it/cgi-bin/webscr';
		else
			$url = 'https://www.paypal.com/it/cgi-bin/webscr';

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
					'at' => $config['token'],
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
				foreach ($response as $key => &$value)
					$value = mb_convert_encoding($value, 'UTF-8', $response['charset']);
				$response['charset_original'] = $response['charset'];
				$response['charset'] = 'UTF-8';
			}

			// Sort on keys for readability (handy when debugging)
			ksort($response);

			#######################################

			return [
				'id' => $response['item_number'],
				'price' => $response['mc_gross'],
				'meta' => [
					'type' => 'pdt',
				],
			];
		} else {
			$this->model->error('Errore durante la verifica del pagamento. Contattare l\'amministrazione.<br />Status: <b>' . $status . '</b><br />Risposta dal server:<br /><br />' . print_r($response, true));
		}
	}

	/**
	 * @param array $ipn_data
	 * @return array
	 */
	private function ipn(array $ipn_data): array
	{
		$config = $this->retrieveConfig();

		if ($config['test'])
			$url = 'https://www.sandbox.paypal.com/it/cgi-bin/webscr';
		else
			$url = 'https://www.paypal.com/it/cgi-bin/webscr';

		// Set up request to PayPal
		$request = curl_init();
		curl_setopt_array($request, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query(['cmd' => '_notify-validate'] + $ipn_data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CAINFO => INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . 'Paypal' . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'cacert.pem',
		]);

		// Execute request and get response and status code
		$response = curl_exec($request);
		$status = curl_getinfo($request, CURLINFO_HTTP_CODE);

		// Close connection
		curl_close($request);

		if ($status != 200 or $response !== 'VERIFIED')
			throw new \Exception("Errore: " . $status . "\nRisposta:\n" . $response);

		if (array_key_exists('charset', $ipn_data) and ($charset = $ipn_data['charset']) and $charset !== 'utf-8') {
			// Convert all the values
			foreach ($ipn_data as $key => &$value)
				$value = mb_convert_encoding($value, 'utf-8', $charset);

			// And store the charset values for future reference
			$ipn_data['charset'] = 'utf-8';
			$ipn_data['charset_original'] = $charset;
		}

		$response = $ipn_data;

		if ($response['payment_status'] !== 'Completed')
			throw new \Exception("Pagamento non completato.");

		return [
			'id' => $response['item_number'],
			'price' => $response['mc_gross'],
			'meta' => [
				'type' => 'ipn',
			],
		];
	}
}
