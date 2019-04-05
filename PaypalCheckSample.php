<?php namespace Model\Paypal;

class PaypalCheck extends PaypalCheckBase
{
	public function verifyOrder(string $id, float $tot): int
	{
		// Check validity of the id (does the order exists?) and check if the amount to pay is identical to $tot;
		// Then return 1 in case of a new payment ("execute" will be called), 2 in case of an already paid order ("alreadyExecuted" will be called)
	}

	public function execute(array $response, string $type)
	{
		// What to do at the moment of payment
		return true;
	}

	public function alreadyExecuted(array $response, string $type)
	{
		// What to do if order is already executed
		return true;
	}
}
