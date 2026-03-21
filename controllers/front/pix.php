<?php

class AgappmaxPixModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    protected function logPixError($message, $cartId = 0)
    {
        PrestaShopLogger::addLog('[AGAPPMAX PIX] ' . $message, 3, null, 'Cart', (int)$cartId, true, (int)$this->context->shop->id);
    }

    protected function failAndBack($message, $logMessage = null, $cartId = 0)
    {
        if ($logMessage) {
            $this->logPixError($logMessage, $cartId);
        }
        $this->errors[] = $message;
        $this->redirectWithNotifications('index.php?controller=order&step=1');
    }

    public function postProcess()
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        $cart = $this->context->cart;
        if (!$cart || !$cart->id_customer) {
            $this->failAndBack($module->l('Carrinho ou cliente ausente.'), 'Carrinho ou cliente ausente', $cart ? $cart->id : 0);
        }

        $module->syncCartCouponsForBillingType($cart, 'pix');

        $address = $cart->id_address_invoice ? new Address((int)$cart->id_address_invoice) : null;
        $docInput = Tools::getValue('agappmax_document');
        $doc = preg_replace('/\D+/', '', (string)$docInput);
        if (!$doc) {
            $this->failAndBack($module->l('Documento obrigatório.'), 'Documento obrigatório não encontrado', $cart->id);
        }

        $appmaxCustomerId = $module->ensureAppmaxCustomerId($cart->id_customer, true, $doc);
        if (!$appmaxCustomerId) {
            $detail = $module->getLastCustomerSyncError();
            $msg = $module->l('Falha ao sincronizar cliente na AppMax.');
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

        $payload = $module->buildPixPayload($appmaxOrderId, $appmaxCustomerId, $doc);
        $payload['customer'] = [
            'customer_id' => (int)$appmaxCustomerId,
        ];
        $resp = $client->createPixPayment($payload);
        if (!$resp['success']) {
            $this->failAndBack(
                $module->l('Falha ao gerar PIX: ') . ($resp['error'] ?? ''),
                'Falha ao gerar PIX: ' . ($resp['error'] ?? json_encode($resp)),
                $cart->id
            );
        }

        $paymentId = $resp['data']['pay_reference'] ?? ($resp['data']['payment_id'] ?? ($resp['data']['id'] ?? null));
        $status = $resp['data']['status'] ?? 'PENDENTE';
        $pixQrcode = $resp['data']['pix_qrcode'] ?? null;
        $pixEmv = $resp['data']['pix_emv'] ?? null;
        $pixCreatedAt = $resp['data']['pix_creation_date'] ?? null;
        $pixExpiresAt = $resp['data']['pix_expiration_date'] ?? null;
        $appmaxCustomerId = $resp['data']['customer']['id'] ?? ($resp['data']['customer_id'] ?? null);
        $siteId = $resp['data']['customer']['site_id'] ?? ($resp['data']['site_id'] ?? null);

        if (!$status) {
            $this->failAndBack($module->l('Status nao retornado pela AppMax.'), 'Status não retornado pela AppMax', $cart->id);
        }

        $orderStatus = $module->getMappedOrderStateId($status);
        if ($orderStatus <= 0) {
            $this->failAndBack($module->l('Status retornado nao mapeado: ') . $status, 'Status retornado não mapeado: ' . $status, $cart->id);
        }
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        $module->validateOrder($cart->id, $orderStatus, $amount, $module->getOrderPaymentLabel('pix'), null, [], null, false, $cart->secure_key);
        $idOrder = (int)$module->currentOrder;

        if ($paymentId) {
            Db::getInstance()->insert($module::TBL_PAYMENT, [
                'id_order' => $idOrder,
                'appmax_payment_id' => pSQL($paymentId),
                'appmax_order_id' => (int)$appmaxOrderId,
                'billing_type' => 'pix',
                'status' => pSQL($status),
                'pix_qrcode' => $pixQrcode ? pSQL($pixQrcode, true) : null,
                'pix_emv' => $pixEmv ? pSQL($pixEmv) : null,
                'pix_creation_date' => $pixCreatedAt ? pSQL($pixCreatedAt) : null,
                'pix_expiration_date' => $pixExpiresAt ? pSQL($pixExpiresAt) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Propaga referências do PIX para o pagamento PS
        $order = new Order($idOrder);
        $payments = $order->getOrderPayments();
        if (!empty($payments)) {
            foreach ($payments as $pmt) {
                if ($paymentId) {
                    $pmt->transaction_id = pSQL($paymentId);
                }
                if ($pixEmv) {
                    $pmt->card_number = pSQL($pixEmv);
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
