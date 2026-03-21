<?php

/**
 * Controlador de teste para simular aprovação de pagamento por cartão de crédito
 * Acesse: /module/agappmax/testcard
 */
class AgappmaxTestcardModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        $cart = $this->context->cart;
        
        if (!$cart || !$cart->id_customer) {
            die('ERRO: Carrinho ou cliente ausente.');
        }

        $module->syncCartCouponsForBillingType($cart, 'card');

        $cartTotal = $cart->getOrderTotal(true, Cart::BOTH);
        if ($cartTotal < 5.00) {
            die('ERRO: Valor mínimo para pagamento com cartão é R$ 5,00.');
        }

        $customer = new Customer((int)$cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            die('ERRO: Cliente inválido.');
        }

        // Obtém documento usando o método do módulo
        $address = $cart->id_address_invoice ? new Address((int)$cart->id_address_invoice) : null;
        $doc = $module->resolveDocumentNumber($customer, $address);
        
        if (!$doc) {
            die('ERRO: Documento do cliente não encontrado. Configure o CPF/CNPJ no cadastro.');
        }

        // Sincroniza cliente
        // Simula ID do cliente AppMax (não sincroniza de verdade)
        $appmaxCustomerId = 999999;

        $module->ensurePaymentColumns();

        // SIMULAÇÃO - não cria pedido real na AppMax
        $appmaxOrderId = 'TEST_ORDER_' . uniqid();

        // Dados fake de cartão para teste
        $card = [
            'number' => '4111111111111111',
            'cvv' => '123',
            'month' => 12,
            'year' => 2028,
            'name' => 'TEST CARD',
        ];
        $installments = 1;
        
        $result = $module->processCreditCardPayment($cart, $appmaxOrderId, $appmaxCustomerId, $doc, $card, $installments, true);
        
        if (!$result['success']) {
            die('ERRO: ' . $result['error']);
        }
        
        $idOrder = $result['id_order'];

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$module->id . '&id_order=' . $idOrder . '&key=' . $cart->secure_key);
    }
}
