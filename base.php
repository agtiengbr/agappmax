<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once _PS_MODULE_DIR_ . 'agcliente/lib/AgPaymentModule.php';
require_once _PS_MODULE_DIR_ . 'agappmax/classes/Api/AppmaxClient.php';

class BaseAgappmax extends AgPaymentModule
{
    protected $cpf_mapping;
    protected $cnpj_mapping;
    protected $social_name_mapping;
    protected $address_number_mapping;
    protected $lastCustomerSyncError = '';
    const CONFIG_PREFIX = 'AGAPPMAX_';
    // Removido campo unificado, agora cada parcela tem sua própria config


    const CFG_ENABLED_PIX = 'ENABLE_PIX';
    const CFG_ENABLED_BOLETO = 'ENABLE_BOLETO';
    const CFG_ENABLED_CARD = 'ENABLE_CARD';
    const CFG_LABEL_PIX = 'LABEL_PIX';
    const CFG_LABEL_BOLETO = 'LABEL_BOLETO';
    const CFG_LABEL_CARD = 'LABEL_CARD';
    const CFG_ORDER_PAYMENT_LABEL_PIX = 'ORDER_PAYMENT_LABEL_PIX';
    const CFG_ORDER_PAYMENT_LABEL_BOLETO = 'ORDER_PAYMENT_LABEL_BOLETO';
    const CFG_ORDER_PAYMENT_LABEL_CARD = 'ORDER_PAYMENT_LABEL_CARD';
    const CFG_IS_SANDBOX = 'SANDBOX';
    const CFG_TOKEN_PROD = 'API_TOKEN_PROD';
    const CFG_TOKEN_SANDBOX = 'API_TOKEN_SANDBOX';
    const CFG_WORKER_LAST_RUN = 'WORKER_LAST_RUN';
    const CFG_LOG_RETENTION_DAYS = 'LOG_RETENTION_DAYS';
    const CFG_LOG_CLEAN_INTERVAL_HOURS = 'LOG_CLEAN_INTERVAL_HOURS';
    const CFG_LOG_LAST_CLEAN = 'LOG_LAST_CLEAN';
    const CFG_CARD_MIN_INSTALLMENT = 'CARD_MIN_INSTALLMENT';
    const CFG_CARD_MAX_INSTALLMENTS = 'CARD_MAX_INSTALLMENTS';
    const CFG_CARD_SOFT_DESCRIPTOR = 'CARD_SOFT_DESCRIPTOR';
    const CFG_CARD_REQUIRE_VALID_ORDER = 'CARD_REQUIRE_VALID_ORDER';
    const CFG_COUPON_ID_PIX = 'COUPON_ID_PIX';
    const CFG_COUPON_ID_BOLETO = 'COUPON_ID_BOLETO';
    const CFG_CPF_FIELD = 'CPF_FIELD';
    const CFG_CNPJ_FIELD = 'CNPJ_FIELD';
    const CFG_SOCIAL_NAME_FIELD = 'SOCIAL_NAME_FIELD';
    const CFG_ADDRESS_NUMBER_FIELD = 'ADDRESS_NUMBER_FIELD';
    const CFG_STATUS_MAP_PREFIX = 'MAP_STATUS_';
    const CFG_STATUS_BEHAVIOR_PREFIX = 'MAP_BEHAVIOR_';
    const CFG_BOLETO_BUSINESS_DAYS = 'BOLETO_BUSINESS_DAYS';
    const CFG_PIX_EXPIRATION_DAYS = 'PIX_EXPIRATION_DAYS';

    // Status mapeáveis conforme webhooks AppMax
    const APPMAX_STATUSES = [
        'APROVADO',
        'AUTORIZADO',
        'PENDENTE',
        'INTEGRADO',
        'ESTORNADO',
        'CANCELADO',
    ];
    
    const WEBHOOK_EVENTS = [
        // Formatos mais novos (snake/dot) vistos em integrações recentes
        'payment.status_changed',
        'payment.updated',
        'order.status_changed',
        // Formatos legados ainda enviados pela AppMax em algumas contas
        'OrderPaid',
        'OrderApproved',
    ];

    // Database tables (without prefix)
    const TBL_API_LOG = 'agappmax_api_log';
    const TBL_PAYMENT = 'agappmax_payment';
    const TBL_WEBHOOK = 'agappmax_webhook_log';
    const TBL_CUSTOMER = 'agappmax_customer';

    protected $hooks = [
        'displayHeader',
        'paymentOptions',
        'paymentReturn',
        'orderConfirmation',
        'displayOrderConfirmation',
        'displayCustomerAccount',
        'displayAdminOrderMain',
    ];

    protected $main_tab = 'AdminParentPayment';

    protected $workers = [
        [
            'name' => 'main',
            'controller' => 'worker',
            'delay' => 60 * 15,
        ],
    ];

    protected $tabs = [
        [
            'name' => 'AppMax',
            'className' => 'AdminAgappmaxParent',
            'active' => 1,
            'childs' => [
                [
                    'name' => 'Configurações',
                    'className' => 'AdminAgappmax',
                    'active' => 1,
                ],
                [
                    'name' => 'Transações',
                    'className' => 'AdminAgappmaxTransactions',
                    'active' => 1,
                ],
                [
                    'name' => 'Logs',
                    'className' => 'AdminAgappmaxLogs',
                    'active' => 1,
                ],
            ],
        ],
    ];

    public function __construct()
    {
        $this->name = 'agappmax';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.11';
        $this->author = 'AGTI';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'AppMax';
        $this->description = 'Integração com AppMax: PIX, Boleto e Cartão.';

        $this->initMappings();
    }

    protected function initMappings()
    {
        if (!class_exists('AgColumnMapping')) {
            return;
        }

        $this->cpf_mapping = new AgColumnMapping();
        $this->cpf_mapping->setData([
            'table_name' => 'customer',
            'configuration_name' => 'agappmax_cpf',
        ]);
        $this->cpf_mapping->addColumn('djtalbrazilianregister', 'Modulo de Cadastro Brasileiro');
        $this->cpf_mapping->addColumn('ldbrazilianregister', 'Modulo de Cadastro LD');
        $this->cpf_mapping->addColumn('psmodcpf', 'Modulo CPF/CNPJ SoliSYS');
        $this->cnpj_mapping = new AgColumnMapping();
        $this->cnpj_mapping->setData([
            'table_name' => 'customer',
            'configuration_name' => 'agappmax_cnpj',
        ]);
        $this->cnpj_mapping->addColumn('djtalbrazilianregister', 'Modulo de Cadastro Brasileiro');
        $this->cnpj_mapping->addColumn('ldbrazilianregister', 'Modulo de Cadastro LD');
        $this->cnpj_mapping->addColumn('psmodcpf', 'Modulo CPF/CNPJ SoliSYS');
        $this->social_name_mapping = new AgColumnMapping();
        $this->social_name_mapping->setData([
            'table_name' => 'customer',
            'configuration_name' => 'agappmax_social_name',
        ]);
        $this->address_number_mapping = new AgColumnMapping();
        $this->address_number_mapping->setData([
            'table_name' => 'address',
            'configuration_name' => 'agappmax_address_number',
        ]);

        $this->hydrateMappingsFromConfig();

        if (Module::isEnabled('agcustomers')) {
            $this->cpf_mapping->mapsTo('cpf');
            $this->cnpj_mapping->mapsTo('cnpj');
            $this->social_name_mapping->mapsTo('company_name');
            $this->address_number_mapping->mapsTo('number');
        }
    }

    public function getCpfMapping()
    {
        return $this->cpf_mapping;
    }

    public function getCnpjMapping()
    {
        return $this->cnpj_mapping;
    }

    public function getSocialNameMapping()
    {
        return $this->social_name_mapping;
    }

    public function getAddressNumberMapping()
    {
        return $this->address_number_mapping;
    }

    protected function hydrateMappingsFromConfig()
    {
        // Migração (legado): versões antigas gravavam o "campo" em AGAPPMAX_*_FIELD.
        // A partir daqui, o único storage para mapeamento é o próprio AgColumnMapping (configuration_name).
        $legacyToMapper = [
            self::CFG_CPF_FIELD => $this->cpf_mapping,
            self::CFG_CNPJ_FIELD => $this->cnpj_mapping,
            self::CFG_SOCIAL_NAME_FIELD => $this->social_name_mapping,
            self::CFG_ADDRESS_NUMBER_FIELD => $this->address_number_mapping,
        ];

        foreach ($legacyToMapper as $cfgKey => $mapper) {
            if (
                !$mapper
                || !method_exists($mapper, 'getMappedField')
                || !method_exists($mapper, 'mapsTo')
                || (string)$mapper->getMappedField() !== ''
            ) {
                continue;
            }
            $legacyKey = self::CONFIG_PREFIX . $cfgKey;
            $legacyVal = (string)Configuration::get($legacyKey);
            if ($legacyVal !== '') {
                $mapper->mapsTo($legacyVal);
                Configuration::deleteByName($legacyKey);
            }
        }
    }

    public function install()
    {
        $ok = parent::install();
        if (!$ok) {
            return false;
        }

        if (!$this->installDb()) {
            return false;
        }

        $this->resetConfig();
        return true;
    }

    public function uninstall()
    {
        foreach ([
            self::CFG_ENABLED_PIX,
            self::CFG_ENABLED_BOLETO,
            self::CFG_ENABLED_CARD,
            self::CFG_LABEL_PIX,
            self::CFG_LABEL_BOLETO,
            self::CFG_LABEL_CARD,
            self::CFG_ORDER_PAYMENT_LABEL_PIX,
            self::CFG_ORDER_PAYMENT_LABEL_BOLETO,
            self::CFG_ORDER_PAYMENT_LABEL_CARD,
            self::CFG_IS_SANDBOX,
            self::CFG_TOKEN_PROD,
            self::CFG_TOKEN_SANDBOX,
            self::CFG_WORKER_LAST_RUN,
            self::CFG_LOG_RETENTION_DAYS,
            self::CFG_LOG_CLEAN_INTERVAL_HOURS,
            self::CFG_LOG_LAST_CLEAN,
            self::CFG_CARD_MIN_INSTALLMENT,
            self::CFG_CARD_MAX_INSTALLMENTS,
            self::CFG_CARD_SOFT_DESCRIPTOR,
            self::CFG_CARD_REQUIRE_VALID_ORDER,
            // Não mais usado: self::CFG_CARD_INSTALLMENT_FEES,
            self::CFG_COUPON_ID_PIX,
            self::CFG_COUPON_ID_BOLETO,
            self::CFG_PIX_EXPIRATION_DAYS,
            self::CFG_BOLETO_BUSINESS_DAYS,
        ] as $key) {
            Configuration::deleteByName(self::CONFIG_PREFIX . $key);
        }
        foreach (self::APPMAX_STATUSES as $s) {
            Configuration::deleteByName(self::CONFIG_PREFIX . self::CFG_STATUS_MAP_PREFIX . $s);
            Configuration::deleteByName(self::CONFIG_PREFIX . self::CFG_STATUS_BEHAVIOR_PREFIX . $s);
        }

        return parent::uninstall();
    }

    protected function installDb()
    {
        $prefix = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $sql = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TBL_API_LOG . "` (
            `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `method` VARCHAR(16) NOT NULL,
            `url` TEXT NOT NULL,
            `request_headers` MEDIUMTEXT NULL,
            `request_body` MEDIUMTEXT NULL,
            `response_code` INT NULL,
            `response_headers` MEDIUMTEXT NULL,
            `response_body` MEDIUMTEXT NULL,
            `duration_ms` INT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_log`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TBL_PAYMENT . "` (
            `id_agappmax_payment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `appmax_payment_id` VARCHAR(64) NOT NULL,
            `appmax_order_id` BIGINT UNSIGNED NULL,
            `billing_type` VARCHAR(32) NOT NULL,
            `status` VARCHAR(64) NULL,
            `boleto_url` VARCHAR(255) NULL,
            `boleto_due_date` VARCHAR(32) NULL,
            `boleto_digitable_line` VARCHAR(255) NULL,
            `pix_qrcode` MEDIUMTEXT NULL,
            `pix_emv` TEXT NULL,
            `pix_creation_date` VARCHAR(32) NULL,
            `pix_expiration_date` VARCHAR(32) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id_agappmax_payment`),
            UNIQUE KEY `uniq_order` (`id_order`),
            KEY `idx_appmax_payment` (`appmax_payment_id`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TBL_WEBHOOK . "` (
            `id_webhook` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event` VARCHAR(128) NULL,
            `payment_id` VARCHAR(64) NULL,
            `headers` MEDIUMTEXT NULL,
            `payload` MEDIUMTEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_webhook`),
            KEY (`payment_id`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}" . self::TBL_CUSTOMER . "` (
            `id_agappmax_customer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_customer` INT UNSIGNED NOT NULL,
            `appmax_customer_id` BIGINT UNSIGNED NOT NULL,
            `site_id` BIGINT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id_agappmax_customer`),
            UNIQUE KEY `uniq_customer` (`id_customer`),
            KEY `idx_appmax_customer` (`appmax_customer_id`)
        ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4;";

        foreach ($sql as $s) {
            if (!Db::getInstance()->execute($s)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Garante que as tabelas e colunas existam mesmo em upgrades ou instalações parciais.
     */
    public function ensureTables()
    {
        // CREATE TABLE IF NOT EXISTS é idempotente; safe para rodar sempre
        $this->installDb();
        $this->ensurePaymentColumns();
    }

    protected function resetConfig()
    {
        $defaults = [
            self::CFG_ENABLED_PIX => 1,
            self::CFG_ENABLED_BOLETO => 1,
            self::CFG_ENABLED_CARD => 1,
            self::CFG_LABEL_PIX => 'Pagar com PIX (AppMax)',
            self::CFG_LABEL_BOLETO => 'Boleto bancario (AppMax)',
            self::CFG_LABEL_CARD => 'Cartao de credito (AppMax)',
            self::CFG_ORDER_PAYMENT_LABEL_PIX => 'AppMax (PIX)',
            self::CFG_ORDER_PAYMENT_LABEL_BOLETO => 'AppMax (Boleto)',
            self::CFG_ORDER_PAYMENT_LABEL_CARD => 'AppMax (Cartao)',
            self::CFG_IS_SANDBOX => 1,
            self::CFG_TOKEN_PROD => '',
            self::CFG_TOKEN_SANDBOX => '',
            self::CFG_LOG_RETENTION_DAYS => 90,
            self::CFG_LOG_CLEAN_INTERVAL_HOURS => 24,
            self::CFG_CARD_MIN_INSTALLMENT => 5.00,
            self::CFG_CARD_MAX_INSTALLMENTS => 12,
            self::CFG_CARD_SOFT_DESCRIPTOR => '',
            self::CFG_CARD_REQUIRE_VALID_ORDER => 0,
            // Não mais usado: self::CFG_CARD_INSTALLMENT_FEES => '',
            self::CFG_COUPON_ID_PIX => '',
            self::CFG_COUPON_ID_BOLETO => '',
            self::CFG_PIX_EXPIRATION_DAYS => 1,
            self::CFG_BOLETO_BUSINESS_DAYS => 3,
        ];
        foreach ($defaults as $key => $value) {
            $fullKey = self::CONFIG_PREFIX . $key;
            // Só define se não existir
            if (Configuration::get($fullKey) === false) {
                Configuration::updateValue($fullKey, $value);
            }
        }
        foreach (self::APPMAX_STATUSES as $s) {
            $mapKey = self::CONFIG_PREFIX . self::CFG_STATUS_MAP_PREFIX . $s;
            $behaviorKey = self::CONFIG_PREFIX . self::CFG_STATUS_BEHAVIOR_PREFIX . $s;
            if (Configuration::get($mapKey) === false) {
                Configuration::updateValue($mapKey, 0);
            }
            if (Configuration::get($behaviorKey) === false) {
                Configuration::updateValue($behaviorKey, 'close');
            }
        }
    }

    public function getOrderPaymentLabel($billingType)
    {
        $normalized = strtoupper(str_replace(['_', '-'], '', (string)$billingType));
        $cfgKey = null;
        $fallback = (string)$this->displayName;

        if ($normalized === 'PIX') {
            $cfgKey = self::CFG_ORDER_PAYMENT_LABEL_PIX;
            $fallback = 'AppMax (PIX)';
        } elseif ($normalized === 'BOLETO') {
            $cfgKey = self::CFG_ORDER_PAYMENT_LABEL_BOLETO;
            $fallback = 'AppMax (Boleto)';
        } elseif ($normalized === 'CREDITCARD' || $normalized === 'CARD') {
            $cfgKey = self::CFG_ORDER_PAYMENT_LABEL_CARD;
            $fallback = 'AppMax (Cartao)';
        }

        if (!$cfgKey) {
            return $fallback;
        }

        $label = Configuration::get(self::CONFIG_PREFIX . $cfgKey);
        if ($label === '' || $label === null || $label === false) {
            return $fallback;
        }

        return (string) $label;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminAgappmax'));
        return '';
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }

        $this->context->smarty->assign([
            'agappmax_prefill_doc' => $this->getCheckoutDocumentPrefill(),
        ]);

        // Valor minimo para pagamento com cartao (AppMax exige R$ 5,00)
        $minOrderValue = 5.00;
        $cartTotal = $this->context->cart ? $this->context->cart->getOrderTotal(true, Cart::BOTH) : 0;

        $options = [];
        // Cartao so aparece se valor >= R$ 5,00
        if ($this->canShowCardOption() && $cartTotal >= $minOrderValue) {
            $options[] = $this->buildCardOption();
        }
        if ((int)Configuration::get(self::CONFIG_PREFIX . self::CFG_ENABLED_PIX)) {
            $options[] = $this->buildPixOption();
        }
        if ((int)Configuration::get(self::CONFIG_PREFIX . self::CFG_ENABLED_BOLETO)) {
            $options[] = $this->buildBoletoOption();
        }
        return $options;
    }

    protected function customerHasValidOrder($customerId)
    {
        if (!$customerId) {
            return false;
        }

        $query = new DbQuery();
        $query->select('1');
        $query->from('orders', 'o');
        $query->where('o.id_customer = ' . (int)$customerId);
        $query->where('o.valid = 1');

        return (bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    public function canShowCardOption()
    {
        if (!Configuration::get(self::CONFIG_PREFIX . self::CFG_ENABLED_CARD)) {
            return false;
        }

        if (!Configuration::get(self::CONFIG_PREFIX . self::CFG_CARD_REQUIRE_VALID_ORDER)) {
            return true;
        }

        $customerId = (int)$this->context->customer->id;
        if (!$customerId) {
            return false;
        }

        return $this->customerHasValidOrder($customerId);
    }

    protected function buildPixOption()
    {
        $option = new PaymentOption();
        $label = Configuration::get(self::CONFIG_PREFIX . self::CFG_LABEL_PIX);
        if ($label === '' || $label === null) {
            $label = 'Pagar com PIX (AppMax)';
        }
        $action = $this->context->link->getModuleLink($this->name, 'pix');
        $form = '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="agappmax-payment-form">'
            . $this->fetch('module:' . $this->name . '/views/templates/hook/payment_option_identity.tpl')
            . '</form>';
        $option->setCallToActionText($label)
            ->setAction($action)
            ->setForm($form);
        return $option;
    }

    protected function buildBoletoOption()
    {
        $option = new PaymentOption();
        $label = Configuration::get(self::CONFIG_PREFIX . self::CFG_LABEL_BOLETO);
        if ($label === '' || $label === null) {
            $label = 'Boleto bancario (AppMax)';
        }
        $action = $this->context->link->getModuleLink($this->name, 'boleto');
        $form = '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="agappmax-payment-form">'
            . $this->fetch('module:' . $this->name . '/views/templates/hook/payment_option_identity.tpl')
            . '</form>';
        $option->setCallToActionText($label)
            ->setAction($action)
            ->setForm($form);
        return $option;
    }

    protected function buildCardOption()
    {
        $option = new PaymentOption();
        $label = Configuration::get(self::CONFIG_PREFIX . self::CFG_LABEL_CARD);
        if ($label === '' || $label === null) {
            $label = 'Cartao de credito (AppMax)';
        }
        $action = $this->context->link->getModuleLink($this->name, 'card');
        // Calcular valor total do pedido para o template de cartão
        $valor_total = 0;
        if ($this->context->cart && method_exists($this->context->cart, 'getOrderTotal')) {
            $valor_total = $this->context->cart->getOrderTotal(true, Cart::BOTH);
        }
        // Montar array de taxas de juros por parcela
        $maxParc = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_CARD_MAX_INSTALLMENTS);
        $config = [];
        for ($i = 1; $i <= $maxParc; $i++) {
            $feeKey = 'CARD_INSTALLMENT_FEE_' . $i;
            $config[$feeKey] = Configuration::get(self::CONFIG_PREFIX . $feeKey);
        }
        $config['CARD_MAX_INSTALLMENTS'] = $maxParc;
        $config['CARD_MIN_INSTALLMENT'] = Configuration::get(self::CONFIG_PREFIX . self::CFG_CARD_MIN_INSTALLMENT);
        $this->context->smarty->assign([
            'agappmax_card_total' => $valor_total,
            'config' => $config,
        ]);
        $form = '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="agappmax-payment-form">'
            . $this->fetch('module:' . $this->name . '/views/templates/hook/payment_option_identity.tpl')
            . $this->fetch('module:' . $this->name . '/views/templates/front/form_card.tpl')
            . '</form>';
        $option->setCallToActionText($label)
            ->setAction($action)
            ->setForm($form);
        return $option;
    }

    public function getStatusMapConfig()
    {
        $map = [];
        foreach (self::APPMAX_STATUSES as $s) {
            $map[$s] = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_STATUS_MAP_PREFIX . $s);
        }
        return $map;
    }

    /**
     * Verifica se coluna existe na tabela do banco.
     */
    protected function columnExists($table, $column)
    {
        $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="' . pSQL(_DB_NAME_) . '" AND TABLE_NAME="' . pSQL(_DB_PREFIX_ . $table) . '" AND COLUMN_NAME="' . pSQL($column) . '"';
        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * Garante colunas extras da tabela de pagamentos em upgrades.
     */
    public function ensurePaymentColumns()
    {
        $table = self::TBL_PAYMENT;
        $db = Db::getInstance();
        if (!$this->columnExists($table, 'appmax_order_id')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `appmax_order_id` BIGINT UNSIGNED NULL AFTER `appmax_payment_id`');
        }
        if (!$this->columnExists($table, 'boleto_url')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `boleto_url` VARCHAR(255) NULL AFTER `status`');
        }
        if (!$this->columnExists($table, 'boleto_due_date')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `boleto_due_date` VARCHAR(32) NULL AFTER `boleto_url`');
        }
        if (!$this->columnExists($table, 'boleto_digitable_line')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `boleto_digitable_line` VARCHAR(255) NULL AFTER `boleto_due_date`');
        }
        if (!$this->columnExists($table, 'pix_qrcode')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `pix_qrcode` MEDIUMTEXT NULL AFTER `boleto_digitable_line`');
        }
        if (!$this->columnExists($table, 'pix_emv')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `pix_emv` TEXT NULL AFTER `pix_qrcode`');
        }
        if (!$this->columnExists($table, 'pix_creation_date')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `pix_creation_date` VARCHAR(32) NULL AFTER `pix_emv`');
        }
        if (!$this->columnExists($table, 'pix_expiration_date')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `pix_expiration_date` VARCHAR(32) NULL AFTER `pix_creation_date`');
        }
    }

    public function ensureWebhookColumns()
    {
        $table = self::TBL_WEBHOOK;
        $db = Db::getInstance();
        if (!$this->columnExists($table, 'event_type')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `event_type` VARCHAR(128) NULL AFTER `id_webhook`');
        }
        if (!$this->columnExists($table, 'order_id')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `order_id` BIGINT UNSIGNED NULL AFTER `payment_id`');
        }
        if (!$this->columnExists($table, 'status')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `status` VARCHAR(64) NULL AFTER `order_id`');
        }
        if (!$this->columnExists($table, 'processed')) {
            $db->execute('ALTER TABLE `' . _DB_PREFIX_ . $table . '` ADD `processed` TINYINT(1) DEFAULT 0 AFTER `payload`');
        }
        
        // Garante que o token de webhook existe
        if (!Configuration::get(self::CONFIG_PREFIX . 'WEBHOOK_TOKEN')) {
            Configuration::updateValue(self::CONFIG_PREFIX . 'WEBHOOK_TOKEN', bin2hex(random_bytes(32)));
        }
    }

    public function getStatusBehaviorConfig()
    {
        $out = [];
        foreach (self::APPMAX_STATUSES as $s) {
            $out[$s] = (string)Configuration::get(self::CONFIG_PREFIX . self::CFG_STATUS_BEHAVIOR_PREFIX . $s);
        }
        return $out;
    }

    public function getOrderStatesOptions()
    {
        $states = OrderState::getOrderStates((int)$this->context->language->id);
        $opts = [];
        foreach ($states as $st) {
            $opts[] = [
                'id' => (int)$st['id_order_state'],
                'name' => $st['name'],
                'color' => $st['color'],
            ];
        }
        return $opts;
    }

    public function getMappedOrderStateId($status)
    {
        $rawStatus = trim((string)$status);
        if ($rawStatus === '') {
            return 0;
        }

        // Normaliza para chave compatível com a configuração (APROVADO, PENDENTE, etc.)
        // Importante: primeiro coloca em maiúsculas, depois sanitiza; caso contrário, letras minúsculas viram "_".
        $statusKey = strtoupper($rawStatus);
        $statusKey = preg_replace('/[^A-Z0-9_]/', '_', $statusKey);
        $statusKey = preg_replace('/_+/', '_', $statusKey);
        $statusKey = trim($statusKey, '_');

        // Algumas integrações podem enviar status em inglês; mapeia para as chaves usadas no módulo.
        $aliases = [
            'APPROVED' => 'APROVADO',
            'AUTHORIZED' => 'AUTORIZADO',
            'PENDING' => 'PENDENTE',
            'INTEGRATED' => 'INTEGRADO',
            'REFUNDED' => 'ESTORNADO',
            'CANCELED' => 'CANCELADO',
            'CANCELLED' => 'CANCELADO',
        ];
        if (isset($aliases[$statusKey])) {
            $statusKey = $aliases[$statusKey];
        }

        $map = $this->getStatusMapConfig();
        return isset($map[$statusKey]) ? (int)$map[$statusKey] : 0;
    }

    /**
     * Monta payload para criação de cliente na AppMax.
     */
    protected function buildCustomerPayload(Customer $customer, Address $address = null, $document = null)
    {
        $addr = $address ?: new Address($customer->id_address_invoice ?: $customer->id_address_delivery);
        $phone = $addr && $addr->phone_mobile ? $addr->phone_mobile : ($addr ? $addr->phone : '');
        $stateIso = '';
        if ($addr && $addr->id_state) {
            $state = new \State((int)$addr->id_state);
            if (Validate::isLoadedObject($state)) {
                $stateIso = (string)$state->iso_code;
            }
        }

        $docNumber = $document !== null ? preg_replace('/\D+/', '', (string)$document) : '';

        return [
            'access-token' => $this->getAccessToken(),
            'firstname' => (string)$customer->firstname,
            'lastname' => (string)$customer->lastname,
            'email' => (string)$customer->email,
            'telephone' => (string)$phone,
            'postcode' => $addr ? (string)$addr->postcode : '',
            'address_street' => $addr ? (string)$addr->address1 : '',
            'address_street_number' => $this->resolveAddressNumber($addr),
            'address_street_complement' => $addr ? (string)$addr->address2 : '',
            'address_street_district' => $addr ? (string)$addr->other : '',
            'address_city' => $addr ? (string)$addr->city : '',
            'address_state' => $stateIso,
            'ip' => Tools::getRemoteAddr(),
            'document_number' => $docNumber,
        ];
    }

    /**
     * Garante o ID AppMax; cria cliente on-the-fly se não houver mapeamento.
     */
    public function ensureAppmaxCustomerId($idCustomer, $forceCreate = false, $document = null)
    {
        $mapped = $this->getAppmaxCustomerId($idCustomer);
        if ($mapped && !$forceCreate) {
            return $mapped;
        }

        $customer = new Customer((int)$idCustomer);
        if (!Validate::isLoadedObject($customer)) {
            return 0;
        }
        $address = null;
        $cart = $this->context && $this->context->cart ? $this->context->cart : null;
        if ($cart && $cart->id_address_invoice) {
            $address = new Address((int)$cart->id_address_invoice);
        }
        $docNumber = $document !== null ? $document : $this->resolveDocumentNumber($customer, $address);
        if (!$docNumber) {
            $msg = 'Documento obrigatório ausente para sync de cliente.';
            $this->lastCustomerSyncError = $msg;
            PrestaShopLogger::addLog('[AGAPPMAX] ' . $msg, 3, null, 'Customer', (int)$idCustomer, true, (int)$this->context->shop->id);
            return 0;
        }

        $payload = $this->buildCustomerPayload($customer, $address, $docNumber);
        $client = $this->getApiClient();
        $resp = $client->createCustomer($payload);
        if (!$resp['success']) {
            $msg = $resp['error'] ?? json_encode($resp);
            $this->lastCustomerSyncError = $msg;
            PrestaShopLogger::addLog('[AGAPPMAX] createCustomer falhou: ' . $msg, 3, null, 'Customer', (int)$idCustomer, true, (int)$this->context->shop->id);
            return 0;
        }
        $rawData = $resp['data'] ?? [];
        $data = is_array($rawData) ? $rawData : (is_object($rawData) ? get_object_vars($rawData) : []);
        $appmaxId = isset($data['id']) ? (int)$data['id'] : 0;
        $siteId = isset($data['site_id']) ? $data['site_id'] : null;
        if (!$appmaxId) {
            $msg = 'createCustomer sem id retornado: ' . json_encode($data);
            $this->lastCustomerSyncError = $msg;
            PrestaShopLogger::addLog('[AGAPPMAX] ' . $msg, 3, null, 'Customer', (int)$idCustomer, true, (int)$this->context->shop->id);
            return 0;
        }
        if ($appmaxId) {
            $this->upsertAppmaxCustomer((int)$idCustomer, (int)$appmaxId, $siteId);
            $this->lastCustomerSyncError = '';
            return (int)$appmaxId;
        }
        return 0;
    }

    public function getLastCustomerSyncError()
    {
        return (string)$this->lastCustomerSyncError;
    }

    /**
     * Monta payload de Order para AppMax.
     */
    public function buildOrderPayload(Cart $cart, $appmaxCustomerId)
    {
        $products = [];
        foreach ($cart->getProducts() as $p) {
            $name = (string)$p['name'];
            if (isset($p['id_product'])) {
                $name = self::getMaskedProductName(
                    (int)$p['id_product'],
                    isset($p['id_product_attribute']) ? (int)$p['id_product_attribute'] : 0,
                    $name
                );
            }
            $products[] = [
                'sku' => (string)$p['reference'],
                'name' => $name,
                'qty' => (int)$p['quantity'],
                'digital_product' => !empty($p['is_virtual']) ? 1 : 0,
            ];
        }
        $totalProducts = (float)$cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        $shipping = (float)$cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $discount = abs((float)$cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS));
        $freightType = 'PAC';
        return [
            'access-token' => $this->getAccessToken(),
            'total' => $totalProducts,
            'products' => $products,
            'shipping' => $shipping,
            'customer_id' => (int)$appmaxCustomerId,
            'discount' => $discount,
            'freight_type' => $freightType,
        ];
    }

    protected function getManagedCouponIds()
    {
        $ids = [];
        $pix = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_COUPON_ID_PIX);
        $boleto = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_COUPON_ID_BOLETO);
        if ($pix > 0) {
            $ids[] = $pix;
        }
        if ($boleto > 0) {
            $ids[] = $boleto;
        }
        return array_values(array_unique($ids));
    }

    protected function getCouponIdForBillingType($billingType)
    {
        $normalized = strtolower(str_replace(['_', '-'], '', (string)$billingType));
        if ($normalized === 'pix') {
            return (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_COUPON_ID_PIX);
        }
        if ($normalized === 'boleto') {
            return (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_COUPON_ID_BOLETO);
        }
        return 0;
    }

    protected function cartHasRule(Cart $cart, $idCartRule)
    {
        $idCartRule = (int)$idCartRule;
        if ($idCartRule <= 0 || empty($cart->id)) {
            return false;
        }
        $idLang = (int)($this->context->language ? $this->context->language->id : Configuration::get('PS_LANG_DEFAULT'));
        if (method_exists($cart, 'getCartRules')) {
            $rules = $cart->getCartRules($idLang);
            if (is_array($rules)) {
                foreach ($rules as $r) {
                    if ((int)($r['id_cart_rule'] ?? 0) === $idCartRule) {
                        return true;
                    }
                }
            }
            return false;
        }

        $sql = 'SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_cart_rule` WHERE `id_cart`=' . (int)$cart->id . ' AND `id_cart_rule`=' . (int)$idCartRule;
        return (bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Aplica (ou remove) cupons configurados por forma de pagamento.
     *
     * Observação: isto afeta apenas os IDs definidos em
     * AGAPPMAX_COUPON_ID_PIX/AGAPPMAX_COUPON_ID_BOLETO.
     */
    public function syncCartCouponsForBillingType(Cart $cart, $billingType)
    {
        if (empty($cart->id)) {
            return;
        }

        $desiredId = (int)$this->getCouponIdForBillingType($billingType);
        $managed = $this->getManagedCouponIds();

        $changed = false;
        foreach ($managed as $id) {
            if ($desiredId > 0 && $id === $desiredId) {
                continue;
            }
            if ($this->cartHasRule($cart, $id)) {
                $cart->removeCartRule((int)$id);
                $changed = true;
            }
        }

        if ($desiredId > 0 && !$this->cartHasRule($cart, $desiredId)) {
            $added = $cart->addCartRule((int)$desiredId);
            if (!$added && class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[AGAPPMAX] Falha ao aplicar CartRule #' . (int)$desiredId . ' para billing_type=' . (string)$billingType, 2, null, 'Cart', (int)$cart->id, true, (int)$this->context->shop->id);
            }
            $changed = $changed || (bool)$added;
        }

        if ($changed) {
            $cart->update();
        }
    }

    /**
     * Retorna o nome mascarado do produto, usando a feature MASKED_NAME se existir,
     * senão retorna o nome do produto do PrestaShop, já removendo caracteres especiais.
     *
     * Mesmo comportamento do módulo agyapay.
     */
    public static function getMaskedProductName($id_product, $id_product_attribute = 0, $fallbackName = '')
    {
        $finalName = '';
        if (class_exists('Feature') && class_exists('Product')) {
            $id_lang = (int)Configuration::get('PS_LANG_DEFAULT');
            // Busca a feature MASKED_NAME
            $sql = new DbQuery();
            $sql->select('id_feature');
            $sql->from('feature_lang');
            $sql->where('name = "MASKED_NAME"');
            $sql->where('id_lang = ' . (int)$id_lang);
            $id_feature = Db::getInstance()->getValue($sql);
            if ($id_feature) {
                $sql = new DbQuery();
                $sql->select('id_feature_value');
                $sql->from('feature_product');
                $sql->where('id_product = ' . (int)$id_product);
                $sql->where('id_feature = ' . (int)$id_feature);
                $id_feature_value = Db::getInstance()->getValue($sql);
                if ($id_feature_value) {
                    $feature_value = new FeatureValue($id_feature_value, $id_lang);
                    if (!empty($feature_value->value)) {
                        $finalName = $feature_value->value;
                    }
                }
            }
            if (!$finalName) {
                $finalName = Product::getProductName((int)$id_product, (int)$id_product_attribute, (int)$id_lang);
            }
        }
        if (!$finalName) {
            $finalName = $fallbackName;
        }
        // Remove caracteres especiais conforme padrão do agbling/agmelhorenvio
        $finalName = str_replace(['^','<','>',';','=','#','{','}'], '', $finalName);
        return $finalName;
    }

    /**
     * Retorna o id do cliente na AppMax mapeado para o cliente PS, se existir.
     */
    public function getAppmaxCustomerId($idCustomer)
    {
        $row = Db::getInstance()->getRow('SELECT `appmax_customer_id` FROM `' . _DB_PREFIX_ . self::TBL_CUSTOMER . '` WHERE `id_customer`=' . (int)$idCustomer);
        return $row ? (int)$row['appmax_customer_id'] : 0;
    }

    /**
     * Salva/atualiza o mapeamento de cliente AppMax -> PrestaShop.
     */
    public function upsertAppmaxCustomer($idCustomer, $appmaxCustomerId, $siteId = null)
    {
        $idCustomer = (int)$idCustomer;
        $appmaxCustomerId = (int)$appmaxCustomerId;
        if ($idCustomer <= 0 || $appmaxCustomerId <= 0) {
            return;
        }
        $exists = Db::getInstance()->getValue('SELECT `id_agappmax_customer` FROM `' . _DB_PREFIX_ . self::TBL_CUSTOMER . '` WHERE `id_customer`=' . $idCustomer);
        $now = date('Y-m-d H:i:s');
        if ($exists) {
            Db::getInstance()->update(self::TBL_CUSTOMER, [
                'appmax_customer_id' => $appmaxCustomerId,
                'site_id' => $siteId !== null ? (int)$siteId : null,
                'updated_at' => $now,
            ], 'id_agappmax_customer=' . (int)$exists);
        } else {
            Db::getInstance()->insert(self::TBL_CUSTOMER, [
                'id_customer' => $idCustomer,
                'appmax_customer_id' => $appmaxCustomerId,
                'site_id' => $siteId !== null ? (int)$siteId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function resolveDocumentNumber(Customer $customer, Address $address = null)
    {
        if (!class_exists('AgColumnMapping') || !$this->cpf_mapping || !$this->cnpj_mapping || !$this->social_name_mapping) {
            PrestaShopLogger::addLog('[AGAPPMAX] resolveDocumentNumber: mapeamento indisponível (AgColumnMapping ausente)', 2);
            return '';
        }

        $docData = AgColumnMapping::getCustomerDocument(
            $this->cpf_mapping,
            $this->cnpj_mapping,
            $this->social_name_mapping,
            $customer
        );
        $doc = $docData['cpf'] ?: $docData['cnpj'];

        return $doc ? preg_replace('/\D+/', '', (string)$doc) : '';
    }

    protected function resolveAddressNumber(Address $address = null)
    {
        if (!$address || !$address->id) {
            return '';
        }
        $mappedField = '';
        if (
            class_exists('AgColumnMapping')
            && $this->address_number_mapping
            && method_exists($this->address_number_mapping, 'getMappedField')
        ) {
            $mappedField = trim((string)$this->address_number_mapping->getMappedField());
        }

        if ($mappedField !== '' && preg_match('/^[A-Za-z0-9_]+$/', $mappedField) && $this->columnExists('address', $mappedField)) {
            if (property_exists($address, $mappedField) && (string)$address->{$mappedField} !== '') {
                return (string)$address->{$mappedField};
            }
            $row = Db::getInstance()->getRow('SELECT `' . pSQL($mappedField) . '` AS num FROM `' . _DB_PREFIX_ . 'address` WHERE id_address=' . (int)$address->id);
            if ($row && isset($row['num']) && $row['num'] !== '') {
                return (string)$row['num'];
            }
        }

        return property_exists($address, 'number') ? (string)$address->number : '';
    }

    protected function getCheckoutDocumentPrefill()
    {
        $input = Tools::getValue('agappmax_document');
        if ($input) {
            return preg_replace('/\D+/', '', (string)$input);
        }
        if ($this->context && $this->context->customer && $this->context->customer->id) {
            $customer = new Customer((int)$this->context->customer->id);
            $addr = null;
            if ($this->context->cart && $this->context->cart->id_address_invoice) {
                $addr = new Address((int)$this->context->cart->id_address_invoice);
            }
            return $this->resolveDocumentNumber($customer, $addr);
        }
        return '';
    }

    public function getApiClient()
    {
        $sandbox = (bool)Configuration::get(self::CONFIG_PREFIX . self::CFG_IS_SANDBOX);
        $token = $sandbox
            ? Configuration::get(self::CONFIG_PREFIX . self::CFG_TOKEN_SANDBOX)
            : Configuration::get(self::CONFIG_PREFIX . self::CFG_TOKEN_PROD);
        return new AppmaxClient($this, $token, $sandbox);
    }

    protected function getAccessToken()
    {
        $sandbox = (bool)Configuration::get(self::CONFIG_PREFIX . self::CFG_IS_SANDBOX);
        return $sandbox
            ? Configuration::get(self::CONFIG_PREFIX . self::CFG_TOKEN_SANDBOX)
            : Configuration::get(self::CONFIG_PREFIX . self::CFG_TOKEN_PROD);
    }

    public function buildCreditCardPayload(array $card, $appmaxOrderId, $appmaxCustomerId, $document, $installments)
    {
        $docNumber = preg_replace('/\D+/', '', (string)$document);

        $softDescriptor = trim((string)Configuration::get(self::CONFIG_PREFIX . self::CFG_CARD_SOFT_DESCRIPTOR));
        if ($softDescriptor === '') {
            $softDescriptor = (string)Configuration::get('PS_SHOP_NAME');
        }
        $softDescriptor = Tools::substr(str_replace(["\r", "\n", "\t"], ' ', $softDescriptor), 0, 13);
        return [
            'access-token' => $this->getAccessToken(),
            'cart' => ['order_id' => (int)$appmaxOrderId],
            'customer' => ['customer_id' => (int)$appmaxCustomerId],
            'payment' => [
                'CreditCard' => [
                    'number' => (string)$card['number'],
                    'cvv' => (string)$card['cvv'],
                    'month' => (int)$card['month'],
                    'year' => (int)$card['year'],
                    'document_number' => $docNumber,
                    'name' => (string)$card['name'],
                    'installments' => (int)$installments,
                    'soft_descriptor' => $softDescriptor,
                ],
            ],
        ];
    }

    public function buildBoletoPayload($appmaxOrderId, $appmaxCustomerId, $document)
    {
        $docNumber = preg_replace('/\D+/', '', (string)$document);
        return [
            'access-token' => $this->getAccessToken(),
            'cart' => ['order_id' => (int)$appmaxOrderId],
            'customer' => ['customer_id' => (int)$appmaxCustomerId],
            'payment' => [
                'Boleto' => [
                    'document_number' => $docNumber,
                ],
            ],
        ];
    }

    public function buildPixPayload($appmaxOrderId, $appmaxCustomerId, $document)
    {
        $days = max(1, (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_PIX_EXPIRATION_DAYS));
        $expiration = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        return [
            'access-token' => $this->getAccessToken(),
            'cart' => ['order_id' => (int)$appmaxOrderId],
            'customer' => ['customer_id' => (int)$appmaxCustomerId],
            'payment' => [
                'pix' => [
                    'document_number' => (string)$document,
                    'expiration_date' => $expiration,
                ],
            ],
        ];
    }

    /**
     * Processa pagamento por cartão de crédito após criar pedido AppMax
     * @param Cart $cart
     * @param int $appmaxOrderId
     * @param int $appmaxCustomerId
     * @param string $doc
     * @param array $cardData ['number', 'cvv', 'month', 'year', 'name']
     * @param int $installments
     * @param bool $simulateApproval Para testes, simula aprovação sem chamar API
     * @return array ['success' => bool, 'error' => string, 'id_order' => int]
     */
    public function processCreditCardPayment($cart, $appmaxOrderId, $appmaxCustomerId, $doc, $cardData, $installments = 1, $simulateApproval = false)
    {
        if ($simulateApproval) {
            // Simula retorno aprovado para testes
            $resp = [
                'success' => true,
                'data' => [
                    'payment_id' => 'TEST_' . uniqid(),
                    'id' => 'TEST_' . uniqid(),
                    'customer' => [
                        'id' => $appmaxCustomerId,
                    ],
                ],
            ];
        } else {
            $client = $this->getApiClient();
            
            $payload = $this->buildCreditCardPayload($cardData, $appmaxOrderId, $appmaxCustomerId, $doc, $installments);
            $payload['customer'] = [
                'customer_id' => (int)$appmaxCustomerId,
            ];
            
            $resp = $client->createCreditCardPayment($payload);
        }
        
        // Para cartão de crédito, verificamos apenas se success=true
        if (!isset($resp['success']) || !$resp['success']) {
            $error = '';
            if (isset($resp['message'])) {
                $error = $resp['message'];
            } elseif (isset($resp['text'])) {
                $error = $resp['text'];
            } elseif (isset($resp['error'])) {
                $error = $resp['error'];
            }
            return [
                'success' => false,
                'error' => $error ?: 'Falha ao processar cartão',
            ];
        }

        $paymentId = $resp['data']['payment_id'] ?? ($resp['data']['id'] ?? null);
        $updatedCustomerId = $resp['data']['customer']['id'] ?? ($resp['data']['customer_id'] ?? null);
        $siteId = $resp['data']['customer']['site_id'] ?? ($resp['data']['site_id'] ?? null);

        // Cartão com success=true = APROVADO
        $status = 'APROVADO';
        $orderStatus = $this->getMappedOrderStateId($status);
        if ($orderStatus <= 0) {
            return [
                'success' => false,
                'error' => 'Status APROVADO não mapeado na configuração do módulo',
            ];
        }
        
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        try {
            $this->validateOrder($cart->id, $orderStatus, $amount, $this->getOrderPaymentLabel('credit-card'), null, [], null, false, $cart->secure_key);
        } catch (Exception $e) {
            die('Falha ao criar pedido no PrestaShop: ' . $e->getMessage());
        }

        $idOrder = (int)$this->currentOrder;

        if (!$idOrder) {
            return [
                'success' => false,
                'error' => 'Falha ao criar pedido no PrestaShop',
            ];
        }

        if ($paymentId) {
            Db::getInstance()->insert(self::TBL_PAYMENT, [
                'id_order' => $idOrder,
                'appmax_payment_id' => pSQL($paymentId),
                'appmax_order_id' => (int)$appmaxOrderId,
                'billing_type' => 'credit-card',
                'status' => pSQL($status),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($updatedCustomerId) {
            $this->upsertAppmaxCustomer($cart->id_customer, $updatedCustomerId, $siteId);
        }

        return [
            'success' => true,
            'id_order' => $idOrder,
            'status' => $status,
        ];
    }

    public function logApiCall($method, $url, $reqHeaders, $reqBody, $respCode, $respBody, $durationMs)
    {
        Db::getInstance()->insert(self::TBL_API_LOG, [
            'method' => pSQL($method),
            'url' => pSQL($url, true),
            'request_headers' => pSQL(is_array($reqHeaders) ? json_encode($reqHeaders) : (string)$reqHeaders, true),
            'request_body' => $reqBody === null ? null : pSQL((string)$reqBody, true),
            'response_code' => (int)$respCode,
            'response_headers' => null,
            'response_body' => $respBody === null ? null : pSQL((string)$respBody, true),
            'duration_ms' => (int)$durationMs,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function runWorker()
    {
        $this->cleanupLogs();
        Configuration::updateValue(self::CONFIG_PREFIX . self::CFG_WORKER_LAST_RUN, date('Y-m-d H:i:s'));
    }

    protected function cleanupLogs()
    {
        $retentionDays = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_LOG_RETENTION_DAYS);
        $intervalHours = (int)Configuration::get(self::CONFIG_PREFIX . self::CFG_LOG_CLEAN_INTERVAL_HOURS);
        $lastClean = Configuration::get(self::CONFIG_PREFIX . self::CFG_LOG_LAST_CLEAN);
        $now = time();
        if ($lastClean && (strtotime($lastClean) + ($intervalHours * 3600)) > $now) {
            return;
        }
        if ($retentionDays > 0) {
            $threshold = date('Y-m-d H:i:s', $now - ($retentionDays * 86400));
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . self::TBL_API_LOG . "` WHERE `created_at` < '" . pSQL($threshold) . "'");
            Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . self::TBL_WEBHOOK . "` WHERE `created_at` < '" . pSQL($threshold) . "'");
        }
        Configuration::updateValue(self::CONFIG_PREFIX . self::CFG_LOG_LAST_CLEAN, date('Y-m-d H:i:s'));
    }

    public function handleWebhook(array $payload, $dryRun = false, $verboseLog = true)
    {
        $event = $payload['event'] ?? ($payload['type'] ?? 'unknown');
        $data = $payload['data'] ?? $payload;
        
        $paymentId = $data['payment_id']
            ?? ($data['pay_reference'] ?? ($data['payReference'] ?? null))
            ?? ($payload['payment_id'] ?? ($payload['paymentId'] ?? null));

        // Em alguns eventos (ex.: OrderApproved/OrderPaid), a AppMax envia o ID do pedido em data.id
        $orderId = $data['order_id']
            ?? ($data['id'] ?? null)
            ?? ($payload['order_id'] ?? ($payload['id'] ?? null));
        $status = $data['status'] ?? ($payload['status'] ?? ($payload['payment_status'] ?? null));
        
        if ($verboseLog) {
            Logger::addLog(
                '[AGAPPMAX WEBHOOK] handleWebhook chamado: event=' . $event . ', payment_id=' . $paymentId . ', order_id=' . $orderId . ', status=' . $status . ', dry_run=' . ($dryRun ? 'yes' : 'no'),
                1,
                null,
                'Webhook',
                0,
                true
            );
        }

        // Mapear cliente AppMax -> PrestaShop (quando webhook trouxer dados de cliente)
        $customerData = [];
        if (isset($data['customer']) && is_array($data['customer'])) {
            $customerData = $data['customer'];
        } elseif (is_array($data)) {
            $customerData = $data;
        }
        $appmaxCustomerId = $customerData['id'] ?? ($customerData['customer_id'] ?? null);
        $email = $customerData['email'] ?? null;
        $siteId = $customerData['site_id'] ?? null;
        if ($appmaxCustomerId && $email && !$dryRun) {
            $customers = Customer::getCustomersByEmail($email);
            if (!empty($customers)) {
                $idCustomer = (int)$customers[0]['id_customer'];
                $this->upsertAppmaxCustomer($idCustomer, $appmaxCustomerId, $siteId);
            }
        }
        
        if (!$status) {
            return ['success' => false, 'message' => 'Status não encontrado no payload'];
        }
        
        // Busca pedido local
        $idOrder = null;
        $row = null;
        
        // Se for simulação (orderId começa com SIM_ORDER_), extrai o ID diretamente
        if ($orderId && strpos((string)$orderId, 'SIM_ORDER_') === 0) {
            $extractedId = (int)str_replace('SIM_ORDER_', '', (string)$orderId);
            if ($extractedId > 0 && Validate::isLoadedObject(new Order($extractedId))) {
                $idOrder = $extractedId;
                // Busca registro existente desta simulação pelo appmax_order_id
                $row = Db::getInstance()->getRow(
                    'SELECT * FROM `' . _DB_PREFIX_ . self::TBL_PAYMENT . '` WHERE `id_order` = ' . (int)$idOrder . ' AND `appmax_order_id` LIKE "SIM_ORDER_%"'
                );
                // Cria registro simulado se não existir
                if (!$row && !$dryRun) {
                    Db::getInstance()->insert(
                        self::TBL_PAYMENT,
                        [
                            'id_order' => $idOrder,
                            'appmax_order_id' => pSQL($orderId),
                            'appmax_payment_id' => pSQL($paymentId),
                            'status' => pSQL($status),
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]
                    );
                    $row = ['id_order' => $idOrder, 'id_agappmax_payment' => Db::getInstance()->Insert_ID()];
                }
            }
        } else {
            // Webhook real: busca por payment_id ou order_id
            if ($paymentId) {
                $row = Db::getInstance()->getRow(
                    'SELECT * FROM `' . _DB_PREFIX_ . self::TBL_PAYMENT . "` WHERE `appmax_payment_id` = '" . pSQL($paymentId) . "'"
                );
                $idOrder = $row ? (int)$row['id_order'] : 0;
            }
            
            if (!$idOrder && $orderId) {
                $row = Db::getInstance()->getRow(
                    'SELECT * FROM `' . _DB_PREFIX_ . self::TBL_PAYMENT . "` WHERE `appmax_order_id` = " . (int)$orderId
                );
                $idOrder = $row ? (int)$row['id_order'] : 0;
            }
        }
        
        if (!$row && !$idOrder) {
            return ['success' => false, 'message' => 'Pedido não encontrado localmente'];
        }
        
        // Verifica se o status está mapeado
        $orderStateId = $this->getMappedOrderStateId($status);
        if ($orderStateId <= 0) {
            return ['success' => false, 'message' => "Status '$status' não está mapeado no módulo"];
        }
        
        if ($verboseLog) {
            Logger::addLog(
                '[AGAPPMAX WEBHOOK] Pedido encontrado: #' . $idOrder . ', status_atual=' . (new Order($idOrder))->current_state . ', novo_status=' . $orderStateId,
                1,
                null,
                'Webhook',
                0,
                true
            );
        }
        
        // Aplica o status no pedido
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return ['success' => false, 'message' => 'Pedido inválido: #' . $idOrder];
        }
        
        if ((int)$order->current_state === $orderStateId) {
            return ['success' => true, 'message' => "Pedido #$idOrder já está no status correto" . ($dryRun ? ' [DRY-RUN]' : '')];
        }

        if ($this->orderHasStateInHistory($idOrder, $orderStateId)) {
            if ($verboseLog) {
                Logger::addLog(
                    '[AGAPPMAX WEBHOOK] Webhook ignorado no pedido #' . $idOrder . ': estado #' . $orderStateId . ' já existe no histórico',
                    2,
                    null,
                    'Webhook',
                    0,
                    true
                );
            }

            return [
                'success' => true,
                'message' => "Webhook ignorado para pedido #$idOrder: o estado já ocorreu anteriormente" . ($dryRun ? ' [DRY-RUN]' : ''),
            ];
        }

        // Atualiza status na tabela de pagamentos somente quando a transição será aplicada
        if (!$dryRun) {
            Db::getInstance()->update(
                self::TBL_PAYMENT,
                ['status' => pSQL($status), 'updated_at' => date('Y-m-d H:i:s')],
                'id_agappmax_payment=' . (int)$row['id_agappmax_payment']
            );
        }
        
        if (!$dryRun) {
            $history = new OrderHistory();
            $history->id_order = $idOrder;
            $history->changeIdOrderState($orderStateId, $idOrder);
            $history->addWithemail(true);
        }
        
        if ($verboseLog) {
            Logger::addLog(
                '[AGAPPMAX WEBHOOK] Status ' . ($dryRun ? 'seria aplicado' : 'aplicado') . ' no pedido #' . $idOrder . ': ' . $status,
                1,
                null,
                'Webhook',
                0,
                true
            );
        }

        return ['success' => true, 'message' => "Status " . ($dryRun ? 'seria atualizado' : 'atualizado') . " para pedido #$idOrder" . ($dryRun ? ' [DRY-RUN]' : '')];
    }

    protected function orderHasStateInHistory($idOrder, $orderStateId)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1
            FROM `' . _DB_PREFIX_ . 'order_history`
            WHERE `id_order` = ' . (int)$idOrder . '
              AND `id_order_state` = ' . (int)$orderStateId . '
            LIMIT 1'
        );
    }
    
    /**
     * Constrói payload de webhook para testes
     */
    public function buildWebhookPayload($event, $orderId, $status, $paymentId = null)
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TBL_PAYMENT . '` WHERE id_order = ' . (int)$orderId
        );
        
        // Se já existe registro, usa os dados reais
        if ($row) {
            $appmaxOrderId = $row['appmax_order_id'] ?? null;
            $appmaxPaymentId = $paymentId ?: ($row['appmax_payment_id'] ?? 'SIM_' . uniqid());
        } else {
            // Cria dados simulados se não existir registro
            $appmaxOrderId = 'SIM_ORDER_' . $orderId;
            $appmaxPaymentId = $paymentId ?: ('SIM_PAY_' . uniqid());
        }
        
        return [
            'event' => $event,
            'data' => [
                'order_id' => $appmaxOrderId,
                'payment_id' => $appmaxPaymentId,
                'status' => $status,
            ],
        ];
    }

    public function applyStatusMapping($idOrder, $status)
    {
        $map = $this->getStatusMapConfig();
        $behaviors = $this->getStatusBehaviorConfig();
        $key = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', (string)$status));
        $target = isset($map[$key]) ? (int)$map[$key] : 0;
        if ($target <= 0) {
            return;
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return;
        }
        if ((int)$order->current_state === $target) {
            return;
        }
        if ($this->orderHasStateInHistory($idOrder, $target)) {
            return;
        }
        $history = new OrderHistory();
        $history->id_order = $idOrder;
        $history->changeIdOrderState($target, $idOrder);
        $history->addWithemail(true);

        $behavior = $behaviors[$key] ?? 'close';
        if ($behavior === 'error' && $this->context && $this->context->controller) {
            $this->context->controller->errors[] = $this->l('Pagamento nao aprovado.');
        }
    }

    protected function buildHumanStatusLabel($status)
    {
        $map = [
            'PENDENTE' => 'Aguardando pagamento',
            'APROVADO' => 'Pagamento confirmado',
            'AUTORIZADO' => 'Pagamento autorizado',
            'INTEGRADO' => 'Integrado',
            'ESTORNADO' => 'Estornado',
            'CANCELADO' => 'Cancelado',
        ];
        $key = strtoupper((string)$status);
        return $map[$key] ?? $key;
    }

    protected function prepareOrderConfirmData($params)
    {
        $order = isset($params['order']) && $params['order'] instanceof Order
            ? $params['order']
            : (isset($params['objOrder']) && $params['objOrder'] instanceof Order ? $params['objOrder'] : null);

        $isPaid = $order && Validate::isLoadedObject($order) ? (bool) $order->hasBeenPaid() : false;

        $pixData = [
            'is_pix' => false,
            'status' => null,
            'status_label' => null,
            'copy_code' => null,
            'img_base64' => null,
            'expires_at' => null,
        ];
        $boletoData = [
            'is_boleto' => false,
            'status' => null,
            'status_label' => null,
            'billet_url' => null,
            'identification_field' => null,
            'due_date' => null,
        ];
        $cardData = [
            'is_card' => false,
            'status' => null,
            'status_label' => null,
        ];

        if ($order) {
            $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . self::TBL_PAYMENT . '` WHERE `id_order`=' . (int)$order->id . ' ORDER BY `id_agappmax_payment` DESC');
            if ($row && strtoupper($row['billing_type']) === 'PIX') {
                $pixData['is_pix'] = true;
                $pixData['status'] = $row['status'];
                $pixData['status_label'] = $this->buildHumanStatusLabel($row['status']);
                $pixData['copy_code'] = $row['pix_emv'] ?? null;
                $pixData['img_base64'] = $row['pix_qrcode'] ?? null;
                $pixData['expires_at'] = !empty($row['pix_expiration_date']) ? Tools::displayDate($row['pix_expiration_date'], false) : null;
            }
            if ($row && strtoupper($row['billing_type']) === 'BOLETO') {
                $boletoData['is_boleto'] = true;
                $boletoData['status'] = $row['status'];
                $boletoData['status_label'] = $this->buildHumanStatusLabel($row['status']);
                $boletoData['billet_url'] = $row['boleto_url'] ?? null;
                $boletoData['identification_field'] = $row['boleto_digitable_line'] ?? null;
                $boletoData['due_date'] = !empty($row['boleto_due_date']) ? Tools::displayDate($row['boleto_due_date'], false) : null;
            }
            if ($row && strtoupper($row['billing_type']) === 'CREDIT-CARD') {
                $cardData['is_card'] = true;
                $cardData['status'] = $row['status'];
                $cardData['status_label'] = $this->buildHumanStatusLabel($row['status']);
            }
        }

        if ($isPaid) {
            $approvedLabel = $this->buildHumanStatusLabel('APROVADO');

            if ($pixData['is_pix']) {
                $pixData['status'] = 'APROVADO';
                $pixData['status_label'] = $approvedLabel;
            }
            if ($boletoData['is_boleto']) {
                $boletoData['status'] = 'APROVADO';
                $boletoData['status_label'] = $approvedLabel;
            }
            if ($cardData['is_card']) {
                $cardData['status'] = 'APROVADO';
                $cardData['status_label'] = $approvedLabel;
            }
        }

        $eventConfig = [
            'enabled' => false,
            'sseUrl' => null,
            'orderId' => $order ? (int) $order->id : 0,
            'isPaid' => $isPaid,
            'reloadDelay' => 0,
            'autoReload' => true,
            'approvedMessage' => $this->l('Pagamento aprovado! Seu pedido foi confirmado.'),
            'waitingMessage' => $this->l('Estamos aguardando a confirmação do pagamento. Assim que for aprovado, atualizaremos esta página automaticamente.'),
            'timeoutMessage' => $this->l('Ainda não recebemos a confirmação do pagamento. Você pode continuar navegando e voltar mais tarde para verificar novamente.'),
            'approvedStateLabel' => $this->l('Pagamento confirmado'),
        ];

        if ($order && Validate::isLoadedObject($order) && !$isPaid) {
            $eventConfig['enabled'] = (bool) ($pixData['is_pix'] || $boletoData['is_boleto'] || $cardData['is_card']);
            if ($eventConfig['enabled']) {
                $eventConfig['sseUrl'] = $this->context->link->getModuleLink(
                    $this->name,
                    'orderstatussse',
                    [
                        'id_order' => (int) $order->id,
                        'key' => (string) $order->secure_key,
                    ],
                    true
                );
            }
        }

        if ($this->context && $this->context->controller) {
            if (method_exists($this->context->controller, 'registerJavascript')) {
                $this->context->controller->registerJavascript(
                    'agappmax-confirmation',
                    'modules/' . $this->name . '/views/js/confirmation.js',
                    ['position' => 'bottom', 'priority' => 100]
                );
            } elseif (method_exists($this->context->controller, 'addJS')) {
                $this->context->controller->addJS($this->_path . 'views/js/confirmation.js');
            }
        }

        $prefillDoc = '';
        if ($this->context && $this->context->customer && $this->context->customer->id) {
            $customer = new Customer((int)$this->context->customer->id);
            $addr = null;
            if ($this->context->cart && $this->context->cart->id_address_invoice) {
                $addr = new Address((int)$this->context->cart->id_address_invoice);
            }
            $prefillDoc = $this->resolveDocumentNumber($customer, $addr);
        }

        $this->context->smarty->assign([
            'agappmax_pix' => $pixData,
            'agappmax_boleto' => $boletoData,
            'agappmax_card' => $cardData,
            'agappmax_is_paid' => (bool) $isPaid,
            'agappmax_prefill_doc' => $prefillDoc,
            'agappmax_confirmation_json' => json_encode($eventConfig),
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/front/payment_return.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return '';
        }
        return $this->prepareOrderConfirmData($params);
    }

    public function hookDisplayOrderConfirmation($params)
    {
        // Conteudo já renderizado em paymentReturn; evitamos duplicação aqui
        return '';
    }

    public function hookOrderConfirmation($params)
    {
        // Reutiliza o mesmo conteúdo do paymentReturn
        return $this->hookPaymentReturn($params);
    }

    public function hookDisplayHeader($params)
    {
        // Carrega IMask.js via CDN para mascaramento de campos
        $this->context->controller->registerJavascript(
            'imask-js',
            'https://unpkg.com/imask@7.6.1/dist/imask.min.js',
            ['server' => 'remote', 'position' => 'bottom', 'priority' => 80]
        );
        // Carrega script de máscaras do módulo
        $this->context->controller->registerJavascript(
            'agappmax-masks',
            'modules/' . $this->name . '/views/js/agappmax-masks.js',
            ['position' => 'bottom', 'priority' => 90]
        );
        // Loading overlay (spinner ao submeter formulário de pagamento)
        $this->context->controller->registerJavascript(
            'agappmax-loading-overlay',
            'modules/' . $this->name . '/views/js/loadingOverlay.js',
            ['position' => 'bottom', 'priority' => 91]
        );
        $this->context->controller->registerJavascript(
            'agappmax-loading',
            'modules/' . $this->name . '/views/js/agappmax-loading.js',
            ['position' => 'bottom', 'priority' => 92]
        );
        return '';
    }

    public function hookDisplayCustomerAccount($params)
    {
        return '';
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return '';
    }
}
