<?php

class AppmaxClient
{
    /** @var string */
    protected $token;
    /** @var bool */
    protected $sandbox;
    /** @var BaseAgappmax */
    protected $module;

    // Endpoints de pagamento (sandbox homolog informado pelo cliente)
    protected $baseUrlProd = 'https://admin.appmax.com.br/';
    protected $baseUrlSandbox = 'https://homolog.sandboxappmax.com.br/';

    public function __construct(BaseAgappmax $module, $token, $sandbox = true)
    {
        $this->module = $module;
        $this->token = trim((string)$token);
        $this->sandbox = (bool)$sandbox;
    }

    public function request($method, $path, array $body = null, array $headers = [])
    {
        $method = strtoupper($method);
        $url = rtrim($this->sandbox ? $this->baseUrlSandbox : $this->baseUrlProd, '/') . '/' . ltrim($path, '/');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $payload = null;
        if ($body !== null) {
            // Obfuscar dados do cartão antes de logar
            $bodyLog = $body;
            // payment.CreditCard ou payment.creditCard
            foreach (['CreditCard', 'creditCard'] as $ccKey) {
                if (isset($bodyLog['payment'][$ccKey])) {
                    if (isset($bodyLog['payment'][$ccKey]['number'])) {
                        $bodyLog['payment'][$ccKey]['number'] = substr($bodyLog['payment'][$ccKey]['number'], 0, 6) . str_repeat('*', 6);
                    }
                    if (isset($bodyLog['payment'][$ccKey]['cvv'])) {
                        $bodyLog['payment'][$ccKey]['cvv'] = '***';
                    }

                    if (isset($bodyLog['payment'][$ccKey]['month'])) {
                        $bodyLog['payment'][$ccKey]['month'] = '**';
                    }

                    if (isset($bodyLog['payment'][$ccKey]['year'])) {
                        $bodyLog['payment'][$ccKey]['year'] = '**';
                    }
                }
            }
            // creditCard ou CreditCard direto
            foreach (['CreditCard', 'creditCard'] as $ccKey) {
                if (isset($bodyLog[$ccKey])) {
                    if (isset($bodyLog[$ccKey]['number'])) {
                        $bodyLog[$ccKey]['number'] = substr($bodyLog[$ccKey]['number'], 0, 6) . str_repeat('*', 6) . substr($bodyLog[$ccKey]['number'], -4);
                    }
                    if (isset($bodyLog[$ccKey]['cvv'])) {
                        $bodyLog[$ccKey]['cvv'] = '***';
                    }
                }
            }
            // payment.number/cvv direto
            if (isset($bodyLog['payment']['number'])) {
                $bodyLog['payment']['number'] = substr($bodyLog['payment']['number'], 0, 6) . str_repeat('*', 6) . substr($bodyLog['payment']['number'], -4);
            }
            if (isset($bodyLog['payment']['cvv'])) {
                $bodyLog['payment']['cvv'] = '***';
            }
            // cart.number/cvv direto
            if (isset($bodyLog['cart']['number'])) {
                $bodyLog['cart']['number'] = substr($bodyLog['cart']['number'], 0, 6) . str_repeat('*', 6) . substr($bodyLog['cart']['number'], -4);
            }
            if (isset($bodyLog['cart']['cvv'])) {
                $bodyLog['cart']['cvv'] = '***';
            }
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            $payloadLog = json_encode($bodyLog, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers[] = 'Content-Type: application/json';
        } else {
            $payloadLog = null;
        }

        $headers[] = 'Authorization: Bearer ' . $this->token;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $start = microtime(true);
        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = (int)round((microtime(true) - $start) * 1000);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Logar o payload obfuscado
        $this->module->logApiCall($method, $url, $headers, $payloadLog, $httpCode, $respBody, $duration);

        if ($respBody === false) {
            return [
                'success' => false,
                'message' => $curlErr ?: 'Erro desconhecido na conexão',
            ];
        }

        $decoded = json_decode($respBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return [
            'success' => false,
            'message' => 'Resposta AppMax inválida',
            'raw' => $respBody,
        ];
    }

    /**
     * Cria pagamento via cartão de crédito.
     * Estrutura do body deve seguir a doc AppMax: POST /api/v3/payment/credit-card
     */
    public function createCreditCardPayment(array $payload)
    {
        return $this->request('POST', '/api/v3/payment/credit-card', $payload);
    }

    /**
     * Cria cliente na AppMax.
     */
    public function createCustomer(array $payload)
    {
        return $this->request('POST', '/api/v3/customer', $payload);
    }

    /**
     * Cria boleto.
     * POST /api/v3/payment/boleto
     */
    public function createBoletoPayment(array $payload)
    {
        return $this->request('POST', '/api/v3/payment/boleto', $payload);
    }

    /**
     * Cria cobrança PIX.
     * POST /api/v3/payment/pix
     */
    public function createPixPayment(array $payload)
    {
        return $this->request('POST', '/api/v3/payment/pix', $payload);
    }

    /**
     * Cria order na AppMax antes de processar pagamento.
     */
    public function createOrder(array $payload)
    {
        return $this->request('POST', '/api/v3/order', $payload);
    }
}
