<?php

class AgappmaxOrderstatussseModuleFrontController extends ModuleFrontController
{
    /**
     * SSE precisa estar acessível também para convidados, desde que possuam a chave segura do pedido.
     * Caso contrário, a confirmação automática não funcionará para checkouts sem conta.
     */
    public $auth = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $idOrder = (int) Tools::getValue('id_order');
        $secureKey = (string) Tools::getValue('key');

        if (!$idOrder || !$secureKey) {
            exit();
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            exit();
        }

        if ($order->secure_key !== $secureKey) {
            exit();
        }

        // Clientes logados também precisam bater com o pedido
        if ((int) $order->id_customer && $this->context->customer && $this->context->customer->id) {
            if ((int) $this->context->customer->id !== (int) $order->id_customer) {
                exit();
            }
        }

        if (headers_sent()) {
            exit();
        }

        ignore_user_abort(true);
        set_time_limit(0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');

        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        $sleepSeconds = 3;
        $maxIterations = 400; // ~20 minutos
        $iteration = 0;

        do {
            Cache::clean('objectmodel_Order_*');
            Order::cleanHistoryCache();
            $order = new Order($idOrder);

            if ($order->hasBeenPaid()) {
                $this->sendEvent('approved', ['status' => 'approved']);
                break;
            }

            $this->sendEvent('waiting', ['status' => 'waiting']);
            $iteration++;

            if ($iteration >= $maxIterations) {
                $this->sendEvent('timeout', ['status' => 'timeout']);
                break;
            }

            sleep($sleepSeconds);
        } while (!connection_aborted());

        exit();
    }

    private function sendEvent($eventName, array $payload)
    {
        echo 'event: ' . $eventName . "\n";
        echo 'data: ' . json_encode($payload) . "\n\n";
        flush();
    }
}

