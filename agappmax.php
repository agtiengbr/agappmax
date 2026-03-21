<?php

require_once __DIR__ . '/base.php';
class Agappmax extends BaseAgappmax
{
	// PrestaShop só reconhece hooks públicos definidos aqui
	public function hookPaymentOptions($params)
	{
		return parent::hookPaymentOptions($params);
	}
}
