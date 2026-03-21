<?php

class AgappmaxCardModuleFrontController extends ModuleFrontController
{
    protected function logCardError($message, $cartId = 0)
    {
        PrestaShopLogger::addLog('[AGAPPMAX CARD] ' . $message, 3, null, 'Cart', (int)$cartId, true, (int)$this->context->shop->id);
    }

    protected function failAndBack($message, $logMessage = null, $cartId = 0)
    {
        if ($logMessage) {
            $this->logCardError($logMessage, $cartId);
        }
        $this->errors[] = $message;
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }

    public $ssl = true;

    public function postProcess()
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        $cart = $this->context->cart;
        if (!$cart || !$cart->id_customer) {
            $this->failAndBack('Carrinho ou cliente ausente.', 'Carrinho ou cliente ausente', $cart ? $cart->id : 0);
        }

        $module->syncCartCouponsForBillingType($cart, 'card');

        // Validacao de valor minimo (AppMax exige R$ 5,00 para cartao)
        $minOrderValue = 5.00;
        $cartTotal = $cart->getOrderTotal(true, Cart::BOTH);
        if ($cartTotal < $minOrderValue) {
            $this->failAndBack('Valor minimo para pagamento com cartao e R$ 5,00.', 'Valor do pedido abaixo do minimo para cartao: R$ ' . number_format($cartTotal, 2, ',', '.'), $cart->id);
        }

        $address = $cart->id_address_invoice ? new Address((int)$cart->id_address_invoice) : null;
        $docInput = Tools::getValue('agappmax_document');
        $doc = preg_replace('/\D+/', '', (string)$docInput);
        if (!$doc) {
            $this->failAndBack('Documento obrigatorio.', 'Documento obrigatório não encontrado', $cart->id);
        }

        $appmaxCustomerId = $module->ensureAppmaxCustomerId($cart->id_customer, true, $doc);
        if (!$appmaxCustomerId) {
            $detail = $module->getLastCustomerSyncError();
            $msg = 'Falha ao sincronizar cliente na AppMax.';
            if ($detail) {
                $msg .= ' ' . $detail;
            }
            $this->failAndBack($msg, 'Falha ao sincronizar cliente na AppMax: ' . $detail, $cart->id);
        }

        $module->ensurePaymentColumns();

        $client = $module->getApiClient();

        $orderPayload = $module->buildOrderPayload($cart, $appmaxCustomerId);
        $orderPayload['customer'] = [
            'id' => (int)$appmaxCustomerId,
        ];
        $orderResp = $client->createOrder($orderPayload);
        if (!$orderResp['success']) {
            $this->failAndBack(
                $module->l('Falha ao criar pedido na AppMax: ') . ($orderResp['error'] ?? ''),
                'Falha ao criar pedido AppMax: ' . ($orderResp['error'] ?? json_encode($orderResp)),
                $cart->id
            );
        }
        $appmaxOrderId = $orderResp['data']['id'] ?? null;
        if (!$appmaxOrderId) {
            $this->failAndBack($module->l('Pedido AppMax sem ID retornado.'), 'Pedido AppMax sem ID retornado', $cart->id);
        }
        $orderCustomerId = $orderResp['data']['customer']['id'] ?? ($orderResp['data']['customer_id'] ?? null);
        $orderSiteId = $orderResp['data']['customer']['site_id'] ?? ($orderResp['data']['site_id'] ?? null);
        if ($orderCustomerId) {
            $module->upsertAppmaxCustomer($cart->id_customer, (int)$orderCustomerId, $orderSiteId);
            $appmaxCustomerId = (int)$orderCustomerId;
        }

        $card = [
            'number' => preg_replace('/\D+/', '', Tools::getValue('agappmax_card_number')),
            'cvv' => preg_replace('/\D+/', '', Tools::getValue('agappmax_card_cvv')),
            'month' => (int)Tools::getValue('agappmax_card_month', 0) ?: null,
            'year' => (int)Tools::getValue('agappmax_card_year', 0) ?: null,
            'name' => Tools::getValue('agappmax_card_name'),
        ];
        $installments = (int)Tools::getValue('agappmax_installments', 1);
        
        $result = $module->processCreditCardPayment($cart, $appmaxOrderId, $appmaxCustomerId, $doc, $card, $installments);
        
        if (!$result['success']) {
            $this->failAndBack(
                $module->l('Falha ao processar cartão: ') . $result['error'],
                'Falha ao processar cartão: ' . $result['error'],
                $cart->id
            );
        }
        
        $idOrder = $result['id_order'];

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$module->id . '&id_order=' . $idOrder . '&key=' . $cart->secure_key);
    }
}
