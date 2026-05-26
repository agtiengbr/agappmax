<?php

class AdminAgappmaxController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function postProcess()
    {
        $m = $this->module;
        
        // Regenera token do webhook
        if (Tools::isSubmit('regenerateWebhookToken')) {
            $newToken = bin2hex(random_bytes(32));
            Configuration::updateValue($m::CONFIG_PREFIX . 'WEBHOOK_TOKEN', $newToken);
            $this->confirmations[] = 'Token de webhook regenerado com sucesso.';
        }
        
        // Simulador de webhook
        if (Tools::isSubmit('simulateWebhook')) {
            $idOrder = (int)Tools::getValue('webhook_sim_order');
            $event = Tools::getValue('webhook_sim_event');
            $status = Tools::getValue('webhook_sim_status');
            $dryRun = (bool)Tools::getValue('webhook_sim_dryrun');
            
            if ($idOrder && $event && $status) {
                // Verifica se pedido existe
                $order = new Order($idOrder);
                if (!Validate::isLoadedObject($order)) {
                    $this->errors[] = 'Pedido #' . $idOrder . ' não existe';
                } else {
                    try {
                        $payload = $m->buildWebhookPayload($event, $idOrder, $status);
                        Logger::addLog(
                            '[AGAPPMAX WEBHOOK SIMULATOR] Iniciando simulação: pedido=#' . $idOrder . ', event=' . $event . ', status=' . $status . ', dry_run=' . ($dryRun ? 'yes' : 'no'),
                            1,
                            null,
                            'WebhookSimulator',
                            0,
                            true
                        );
                        
                        $result = $m->handleWebhook($payload, $dryRun, true);
                        
                        if ($result['success']) {
                            $this->confirmations[] = '✓ Simulação concluída: ' . $result['message'];
                        } else {
                            $this->errors[] = '✗ Simulação falhou: ' . $result['message'];
                        }
                        
                        Logger::addLog(
                            '[AGAPPMAX WEBHOOK SIMULATOR] Resultado: ' . ($result['success'] ? 'sucesso' : 'falha') . ' - ' . $result['message'],
                            $result['success'] ? 1 : 2,
                            null,
                            'WebhookSimulator',
                            0,
                            true
                        );
                    } catch (Exception $e) {
                        $this->errors[] = 'Erro na simulação: ' . $e->getMessage();
                        Logger::addLog(
                            '[AGAPPMAX WEBHOOK SIMULATOR] Exceção: ' . $e->getMessage() . ' - ' . $e->getTraceAsString(),
                            3,
                            null,
                            'WebhookSimulator',
                            0,
                            true
                        );
                    }
                }
            } else {
                $this->errors[] = 'Parâmetros inválidos para simulação';
            }
        }
        
        if (Tools::isSubmit('submitAgappmax')) {
            $fields = [
                $m::CFG_ENABLED_PIX,
                $m::CFG_ENABLED_BOLETO,
                $m::CFG_ENABLED_CARD,
                $m::CFG_LABEL_PIX,
                $m::CFG_LABEL_BOLETO,
                $m::CFG_LABEL_CARD,
                $m::CFG_ORDER_PAYMENT_LABEL_PIX,
                $m::CFG_ORDER_PAYMENT_LABEL_BOLETO,
                $m::CFG_ORDER_PAYMENT_LABEL_CARD,
                $m::CFG_IS_SANDBOX,
                $m::CFG_TOKEN_PROD,
                $m::CFG_TOKEN_SANDBOX,
                $m::CFG_LOG_RETENTION_DAYS,
                $m::CFG_CARD_MIN_INSTALLMENT,
                $m::CFG_CARD_MAX_INSTALLMENTS,
                $m::CFG_CARD_SOFT_DESCRIPTOR,
                $m::CFG_CARD_REQUIRE_VALID_ORDER,
                $m::CFG_COUPON_ID_PIX,
                $m::CFG_COUPON_ID_BOLETO,
                $m::CFG_PIX_EXPIRATION_DAYS,
                $m::CFG_BOLETO_BUSINESS_DAYS,
                $m::CFG_SHOW_MISSING_TRANSACTION_WARNING,
            ];


            // 1. Descobrir todas as taxas enviadas no POST
            $feeKeys = [];
            foreach ($_POST as $key => $val) {
                if (strpos($key, $m::CONFIG_PREFIX . 'CARD_INSTALLMENT_FEE_') === 0) {
                    $feeKeys[] = $key;
                }
            }

            // 2. Limpar todas as taxas antigas (até 24x por segurança)
            for ($i = 1; $i <= 24; $i++) {
                $feeKey = $m::CONFIG_PREFIX . 'CARD_INSTALLMENT_FEE_' . $i;
                Configuration::deleteByName($feeKey);
            }

            // 3. Salvar as taxas enviadas
            foreach ($feeKeys as $feeKey) {
                Configuration::updateValue($feeKey, Tools::getValue($feeKey));
            }

            // 4. (Opcional) Log para debug
            if (method_exists('PrestaShopLogger', 'addLog')) {
                PrestaShopLogger::addLog('[AGAPPMAX] Taxas salvas: ' . implode(', ', $feeKeys), 1);
            }

            // 4. Salvar os demais campos normalmente
            foreach ($fields as $f) {
                $postedKey = $m::CONFIG_PREFIX . $f;
                if (Tools::getIsset($postedKey)) {
                    Configuration::updateValue($postedKey, Tools::getValue($postedKey));
                }
            }
            if (class_exists('AgColumnMapping')) {
                $map = [
                    $m::CFG_CPF_FIELD => $m->getCpfMapping(),
                    $m::CFG_CNPJ_FIELD => $m->getCnpjMapping(),
                    $m::CFG_SOCIAL_NAME_FIELD => $m->getSocialNameMapping(),
                    $m::CFG_ADDRESS_NUMBER_FIELD => $m->getAddressNumberMapping(),
                ];
                foreach ($map as $cfgKey => $mapper) {
                    $postedKey = $m::CONFIG_PREFIX . $cfgKey;
                    if ($mapper && Tools::getIsset($postedKey)) {
                        $mapper->mapsTo(Tools::getValue($postedKey));
                    }
                }
            }
            foreach ($m::APPMAX_STATUSES as $s) {
                $key = $m::CONFIG_PREFIX . $m::CFG_STATUS_MAP_PREFIX . $s;
                if (Tools::getIsset($key)) {
                    Configuration::updateValue($key, (int)Tools::getValue($key));
                }
                $keyB = $m::CONFIG_PREFIX . $m::CFG_STATUS_BEHAVIOR_PREFIX . $s;
                if (Tools::getIsset($keyB)) {
                    Configuration::updateValue($keyB, (string)Tools::getValue($keyB));
                }
            }
            $this->confirmations[] = 'Configurações salvas.';
        }

        parent::postProcess();
    }

    public function initContent()
    {
        parent::initContent();

        $m = $this->module;
        $hasMapping = class_exists('AgColumnMapping') && $m->getCpfMapping() && $m->getCnpjMapping() && $m->getSocialNameMapping() && $m->getAddressNumberMapping();
        $identityOptions = [
            'cpf' => $hasMapping ? $m->getCpfMapping()->getColumnsForSelect() : [],
            'cnpj' => $hasMapping ? $m->getCnpjMapping()->getColumnsForSelect() : [],
            'social' => $hasMapping ? $m->getSocialNameMapping()->getColumnsForSelect() : [],
            'address_number' => $hasMapping ? $m->getAddressNumberMapping()->getColumnsForSelect() : [],
        ];

        $cpfField = $hasMapping ? $m->getCpfMapping()->getMappedField() : '';
        $cnpjField = $hasMapping ? $m->getCnpjMapping()->getMappedField() : '';
        $socialField = $hasMapping ? $m->getSocialNameMapping()->getMappedField() : '';
        $addrNumField = $hasMapping ? $m->getAddressNumberMapping()->getMappedField() : '';
        // Carregar taxas de juros individuais para o template
        $maxParc = (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_CARD_MAX_INSTALLMENTS);
        $workerGroupName = $m->name . '_main';
        $workerCount = null;
        $workerRunning = false;

        try {
            if (class_exists('AgClienteWorkerGroup')) {
                $group = AgClienteWorkerGroup::findByName($workerGroupName);
                if (!Validate::isLoadedObject($group)) {
                    if (method_exists($m, 'installWorkers')) {
                        $m->installWorkers();
                    }
                    $group = AgClienteWorkerGroup::findByName($workerGroupName);
                }

                if (Validate::isLoadedObject($group) && class_exists('AgClienteWorker')) {
                    $workers = AgClienteWorker::findByGroup($group, true);
                    $workerCount = is_array($workers) ? count($workers) : 0;
                    $workerRunning = $workerCount > 0;
                }
            }
        } catch (Exception $e) {
            // se falhar (agcliente ausente / sem tabelas), não quebra a tela de config
        }

        $maintenanceTabHtml = '';
        try {
            $agclienteModuleFile = _PS_MODULE_DIR_ . 'agcliente/agcliente.php';
            if (file_exists($agclienteModuleFile)) {
                require_once $agclienteModuleFile;

                if (method_exists('agcliente', 'renderMaintanceTab')) {
                    // JS necessário para os botões funcionarem nesta tela (AdminAgappmax)
                    $this->context->controller->addJs(_PS_MODULE_DIR_ . 'agcliente/views/js/tab_maintenance.js');
                    $maintenanceTabHtml = agcliente::renderMaintanceTab($m);
                }
            }
        } catch (Exception $e) {
            $maintenanceTabHtml = '';
        }

        $config = [
            'SHOP_NAME' => (string)Configuration::get('PS_SHOP_NAME'),
            'ENABLE_PIX' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_ENABLED_PIX),
            'ENABLE_BOLETO' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_ENABLED_BOLETO),
            'ENABLE_CARD' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_ENABLED_CARD),
            'LABEL_PIX' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_LABEL_PIX) ?: 'Pagar com PIX (AppMax)',
            'LABEL_BOLETO' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_LABEL_BOLETO) ?: 'Boleto bancario (AppMax)',
            'LABEL_CARD' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_LABEL_CARD) ?: 'Cartao de credito (AppMax)',
            'ORDER_PAYMENT_LABEL_PIX' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_ORDER_PAYMENT_LABEL_PIX) ?: 'AppMax (PIX)',
            'ORDER_PAYMENT_LABEL_BOLETO' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_ORDER_PAYMENT_LABEL_BOLETO) ?: 'AppMax (Boleto)',
            'ORDER_PAYMENT_LABEL_CARD' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_ORDER_PAYMENT_LABEL_CARD) ?: 'AppMax (Cartao)',
            'SANDBOX' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_IS_SANDBOX),
            'API_TOKEN_PROD' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_TOKEN_PROD),
            'API_TOKEN_SANDBOX' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_TOKEN_SANDBOX),
            'WORKER_LAST_RUN' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_WORKER_LAST_RUN),
            'WORKER_GROUP_NAME' => $workerGroupName,
            'WORKER_RUNNING' => (int)$workerRunning,
            'WORKER_COUNT' => $workerCount,
            'STATUS_MAP' => $m->getStatusMapConfig(),
            'STATUS_BEHAVIOR' => $m->getStatusBehaviorConfig(),
            'LOG_RETENTION_DAYS' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_LOG_RETENTION_DAYS),
            'LOG_CLEAN_INTERVAL_HOURS' => 24,
            'LOG_LAST_CLEAN' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_LOG_LAST_CLEAN),
            'CARD_MIN_INSTALLMENT' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_CARD_MIN_INSTALLMENT),
            'CARD_MAX_INSTALLMENTS' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_CARD_MAX_INSTALLMENTS),
            'CARD_SOFT_DESCRIPTOR' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_CARD_SOFT_DESCRIPTOR),
            'CARD_REQUIRE_VALID_ORDER' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_CARD_REQUIRE_VALID_ORDER),
            'COUPON_ID_PIX' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_COUPON_ID_PIX),
            'COUPON_ID_BOLETO' => Configuration::get($m::CONFIG_PREFIX . $m::CFG_COUPON_ID_BOLETO),
            'CPF_FIELD' => $cpfField,
            'CNPJ_FIELD' => $cnpjField,
            'SOCIAL_NAME_FIELD' => $socialField,
            'ADDRESS_NUMBER_FIELD' => $addrNumField,
            'PIX_EXPIRATION_DAYS' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_PIX_EXPIRATION_DAYS),
            'BOLETO_BUSINESS_DAYS' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_BOLETO_BUSINESS_DAYS),
            'SHOW_MISSING_TRANSACTION_WARNING' => (int)Configuration::get($m::CONFIG_PREFIX . $m::CFG_SHOW_MISSING_TRANSACTION_WARNING),
        ];
        // Adicionar taxas de juros por parcela ao array $config
        for ($i = 1; $i <= $maxParc; $i++) {
            $feeKey = 'CARD_INSTALLMENT_FEE_' . $i;
            $config[$feeKey] = Configuration::get($m::CONFIG_PREFIX . $feeKey);
        }
        
        // Garante que o webhook tenha colunas e token
        $m->ensureWebhookColumns();
        
        $webhookToken = Configuration::get($m::CONFIG_PREFIX . 'WEBHOOK_TOKEN');
        $webhookUrl = $this->context->link->getModuleLink($m->name, 'webhook', ['token' => $webhookToken], true);
        
        $this->context->smarty->assign([
            'form_action' => self::$currentIndex . '&token=' . $this->token,
            'config' => $config,
            'maintenance_tab_html' => $maintenanceTabHtml,
            'order_states' => $m->getOrderStatesOptions(),
            'appmax_statuses' => $m::APPMAX_STATUSES,
            'identity_options' => $identityOptions,
            'identity_has_mapping' => $hasMapping,
            'webhook_url' => $webhookUrl,
            'webhook_token' => $webhookToken,
        ]);

        $this->setTemplate('main.tpl');
    }
}
