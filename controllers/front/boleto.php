<?php

class AgappmaxBoletoModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    protected function logBoletoError($message, $cartId = 0)
    {
        PrestaShopLogger::addLog('[AGAPPMAX BOLETO] ' . $message, 3, null, 'Cart', (int)$cartId, true, (int)$this->context->shop->id);
    }

    public function postProcess()
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        $cart = $this->context->cart;
        if (!$cart || !$cart->id_customer) {
            $this->errors[] = $module->l('Carrinho ou cliente ausente.');
            $this->logBoletoError('Carrinho ou cliente ausente', $cart ? $cart->id : 0);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $module->syncCartCouponsForBillingType($cart, 'boleto');

        $address = $cart->id_address_invoice ? new Address((int)$cart->id_address_invoice) : null;
        $docInput = Tools::getValue('agappmax_document');
        $doc = preg_replace('/\D+/', '', (string)$docInput);
        if (!$doc) {
            $this->errors[] = $module->l('Documento obrigatório.');
            $this->logBoletoError('Documento obrigatório não encontrado', $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $appmaxCustomerId = $module->ensureAppmaxCustomerId($cart->id_customer, true, $doc);
        if (!$appmaxCustomerId) {
            $detail = $module->getLastCustomerSyncError();
            $msg = $module->l('Falha ao sincronizar cliente na AppMax.');
            if ($detail) {
                $msg .= ' ' . $detail;
            }
            $this->errors[] = $msg;
            $this->logBoletoError('Falha ao sincronizar cliente na AppMax: ' . $detail, $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $module->ensurePaymentColumns();

        $client = $module->getApiClient();

        $orderPayload = $module->buildOrderPayload($cart, $appmaxCustomerId);
        $orderPayload['customer'] = [
            'id' => (int)$appmaxCustomerId,
        ];
        $orderResp = $client->createOrder($orderPayload);
        if (!$orderResp['success']) {
            $this->errors[] = $module->l('Falha ao criar pedido na AppMax: ') . ($orderResp['error'] ?? '');
            $this->logBoletoError('Falha ao criar pedido AppMax: ' . ($orderResp['error'] ?? json_encode($orderResp)), $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
        $appmaxOrderId = $orderResp['data']['id'] ?? null;
        if (!$appmaxOrderId) {
            $this->errors[] = $module->l('Pedido AppMax sem ID retornado.');
            $this->logBoletoError('Pedido AppMax sem ID retornado', $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
        $orderCustomerId = $orderResp['data']['customer']['id'] ?? ($orderResp['data']['customer_id'] ?? null);
        $orderSiteId = $orderResp['data']['customer']['site_id'] ?? ($orderResp['data']['site_id'] ?? null);
        if ($orderCustomerId) {
            $module->upsertAppmaxCustomer($cart->id_customer, (int)$orderCustomerId, $orderSiteId);
            $appmaxCustomerId = (int)$orderCustomerId;
        }

        $payload = $module->buildBoletoPayload($appmaxOrderId, $appmaxCustomerId, $doc);
        $payload['customer'] = [
            'customer_id' => (int)$appmaxCustomerId,
        ];
        $resp = $client->createBoletoPayment($payload);
        if (!$resp['success']) {
            $this->errors[] = $module->l('Falha ao gerar boleto: ') . ($resp['error'] ?? '');
            $this->logBoletoError('Falha ao gerar boleto: ' . ($resp['error'] ?? json_encode($resp)), $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $paymentId = $resp['data']['pay_reference'] ?? ($resp['data']['payment_id'] ?? ($resp['data']['id'] ?? null));
        $status = $resp['data']['status'] ?? 'PENDENTE';
        $boletoUrl = $resp['data']['pdf'] ?? null;
        $digitableLine = $resp['data']['digitable_line'] ?? null;
        $dueDate = $resp['data']['due_date'] ?? null;
        $appmaxCustomerId = $resp['data']['customer']['id'] ?? ($resp['data']['customer_id'] ?? null);
        $siteId = $resp['data']['customer']['site_id'] ?? ($resp['data']['site_id'] ?? null);

        if (!$status) {
            $this->errors[] = $module->l('Status nao retornado pela AppMax.');
            $this->logBoletoError('Status não retornado pela AppMax', $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }

        $orderStatus = $module->getMappedOrderStateId($status);
        if ($orderStatus <= 0) {
            $this->errors[] = $module->l('Status retornado nao mapeado: ') . $status;
            $this->logBoletoError('Status retornado não mapeado: ' . $status, $cart->id);
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        $module->validateOrder($cart->id, $orderStatus, $amount, $module->getOrderPaymentLabel('boleto'), null, [], null, false, $cart->secure_key);
        $idOrder = (int)$module->currentOrder;

        if ($paymentId) {
            Db::getInstance()->insert($module::TBL_PAYMENT, [
                'id_order' => $idOrder,
                'appmax_payment_id' => pSQL($paymentId),
                'appmax_order_id' => (int)$appmaxOrderId,
                'billing_type' => 'boleto',
                'status' => pSQL($status),
                'boleto_url' => $boletoUrl ? pSQL($boletoUrl) : null,
                'boleto_due_date' => $dueDate ? pSQL($dueDate) : null,
                'boleto_digitable_line' => $digitableLine ? pSQL($digitableLine) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Atualiza pagamento PS com referências do boleto
        $order = new Order($idOrder);
        $payments = $order->getOrderPayments();
        if (!empty($payments)) {
            foreach ($payments as $pmt) {
                if ($paymentId) {
                    $pmt->transaction_id = pSQL($paymentId);
                }
                if ($digitableLine) {
                    $pmt->card_number = pSQL($digitableLine);
                }
                $pmt->update();
            }
        }

        if ($appmaxCustomerId) {
            $module->upsertAppmaxCustomer($cart->id_customer, $appmaxCustomerId, $siteId);
        }

        $module->applyStatusMapping($idOrder, $status);

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$module->id . '&id_order=' . $idOrder . '&key=' . $cart->secure_key);
    }
}
