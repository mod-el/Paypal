<?php namespace Model\Paypal\Controllers;

use Model\Core\Controller;

class PaypalController extends Controller
{
	public function index()
	{
		$this->model->load('Paypal');

		switch ($this->model->getRequest(1)) {
			case 'return':
				if (!isset($_GET['tx'])) {
					header('Location: ' . PATH);
					die();
				}
				$r = $this->model->_Paypal->returnData($_GET['tx']);
				break;
			case 'ipn':
				$r = $this->model->_Paypal->ipn($_POST);
				break;
			default:
				die('Unknown request');
				break;
		}
		if ($r) {
			if ($this->model->getRequest(1) === 'return' and $this->model->_Paypal->returnTemplate) {
				$this->viewOptions['template'] = $this->model->_Paypal->returnTemplate;
			} else {
				die('Success');
			}
		} else {
			die('Error');
		}
	}
}
