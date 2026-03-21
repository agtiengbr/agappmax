<?php

/**
 * Controlador para processar webhooks da AppMax
 * URL: /module/agappmax/webhook?token=XXX
 */
class AgappmaxWebhookModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    public function postProcess()
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        
        // Desabilita exibição de erros para não poluir resposta do webhook
        @ini_set('display_errors', '0');
        
        // Valida token de segurança
        $token = Tools::getValue('token');
        $expectedToken = Configuration::get($module::CONFIG_PREFIX . 'WEBHOOK_TOKEN');
        
        if (!$token || !$expectedToken || $token !== $expectedToken) {
            $this->logWebhook('ERRO: Token inválido ou ausente', null, 3);
            http_response_code(401);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Unauthorized']));
        }
        
        // Lê o corpo da requisição
        $rawBody = file_get_contents('php://input');
        $this->logWebhook('Webhook recebido', $rawBody, 1);
        
        if (empty($rawBody)) {
            $this->logWebhook('ERRO: Corpo vazio', null, 3);
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Empty body']));
        }
        
        $data = json_decode($rawBody, true);
        if (!$data) {
            $this->logWebhook('ERRO: JSON inválido', $rawBody, 3);
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Invalid JSON']));
        }
        
        // Salva webhook no banco
        $webhookId = $this->saveWebhook($rawBody, $data);
        
        // Processa o webhook
        $result = $this->processWebhook($data, $webhookId);
        
        header('Content-Type: application/json');
        if ($result['success']) {
            $this->logWebhook('Webhook processado com sucesso: ' . $result['message'], $rawBody, 1);
            http_response_code(200);
            die(json_encode(['status' => 'ok', 'message' => $result['message']]));
        } else {
            $this->logWebhook('Webhook ignorado ou falhou: ' . $result['message'], $rawBody, 2);
            http_response_code(200); // Retorna 200 mesmo assim para não reenviar
            die(json_encode(['status' => 'ignored', 'message' => $result['message']]));
        }
    }
    
    protected function processWebhook($data, $webhookId)
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        
        // Identifica o tipo de evento
        $event = $data['event'] ?? ($data['type'] ?? null);
        
        if (!$event) {
            return ['success' => false, 'message' => 'Evento não identificado'];
        }
        
        $this->logWebhook('Tipo de evento: ' . $event, json_encode($data), 1);
        
        // Processa apenas eventos aceitos
        if (!in_array($event, AgAppMax::WEBHOOK_EVENTS)) {
            return ['success' => false, 'message' => 'Evento ignorado: ' . $event];
        }
        
        // Delega ao módulo
        $result = $module->handleWebhook($data, false, true);
        
        if ($result['success']) {
            $this->logWebhook($result['message'], json_encode($data), 1);
        } else {
            $this->logWebhook('Erro: ' . $result['message'], json_encode($data), 2);
        }
        
        return $result;
    }
    
    protected function saveWebhook($rawBody, $data)
    {
        /** @var BaseAgappmax $module */
        $module = $this->module;
        
        $event = $data['event'] ?? ($data['type'] ?? 'unknown');
        $paymentId = $data['payment_id']
            ?? ($data['data']['payment_id'] ?? ($data['data']['pay_reference'] ?? null));

        // Alguns eventos enviam o ID do pedido em data.id (em vez de data.order_id)
        $orderId = $data['order_id']
            ?? ($data['data']['order_id'] ?? ($data['data']['id'] ?? null));
        $status = $data['status'] ?? ($data['data']['status'] ?? null);
        
        Db::getInstance()->insert($module::TBL_WEBHOOK, [
            'event_type' => pSQL($event),
            'payment_id' => $paymentId ? pSQL($paymentId) : null,
            'order_id' => $orderId ? (int)$orderId : null,
            'status' => $status ? pSQL($status) : null,
            'payload' => pSQL($rawBody, true),
            'processed' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        return Db::getInstance()->Insert_ID();
    }
    
    protected function logWebhook($message, $data = null, $severity = 1)
    {
        $logMessage = '[AGAPPMAX WEBHOOK] ' . $message;
        if ($data) {
            $logMessage .= ' | Data: ' . (strlen($data) > 500 ? substr($data, 0, 500) . '...' : $data);
        }
        
        // Força log com timestamp único para evitar duplicatas serem barradas
        Logger::addLog(
            $logMessage . ' [' . microtime(true) . ']',
            $severity,
            null,
            'Webhook',
            0,
            true
        );
    }
}
