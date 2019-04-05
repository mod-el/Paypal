<?php namespace Model\Paypal;

use Model\Core\Core;

abstract class PaypalCheckBase
{
	/** @var Core */
	protected $model;

	/**
	 * PaypalCheckBase constructor.
	 * @param Core $model
	 */
	public function __construct(Core $model)
	{
		$this->model = $model;
	}

	/**
	 * @param string $id
	 * @param float $tot
	 * @return mixed
	 */
	abstract function verifyOrder(string $id, float $tot): int;

	/**
	 * @param array $response
	 * @param string $type
	 * @return bool
	 */
	abstract function execute(array $response, string $type);

	/**
	 * @param array $response
	 * @param string $type
	 * @return bool
	 */
	abstract function alreadyExecuted(array $response, string $type);
}
