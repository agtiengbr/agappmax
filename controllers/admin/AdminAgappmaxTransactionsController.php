<?php

class AdminAgappmaxTransactionsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'agappmax_payment';
        $this->className = '';
        $this->identifier = 'id_agappmax_payment';
        $this->lang = false;
        $this->bootstrap = true;

        parent::__construct();

        $this->meta_title = 'Transações AppMax';
        $this->page_header_toolbar_title = 'Transações AppMax';

        $this->fields_list = [
            'id_agappmax_payment' => ['title' => 'ID', 'align' => 'center', 'width' => 50],
            'id_order' => ['title' => 'Pedido', 'align' => 'center', 'width' => 70, 'callback' => 'getOrderLink'],
            'appmax_order_id' => ['title' => 'Pedido AppMax', 'align' => 'center', 'width' => 100],
            'appmax_payment_id' => ['title' => 'ID Pagamento', 'width' => 150],
            'billing_type' => ['title' => 'Tipo', 'width' => 100],
            'status' => ['title' => 'Status', 'width' => 100],
            'created_at' => ['title' => 'Data', 'width' => 140],
            'updated_at' => ['title' => 'Atualizado', 'width' => 140],
        ];

        $this->addRowAction('view');
        $this->addRowAction('vieworder');
        $this->list_no_link = true;
        $this->_orderBy = 'id_agappmax_payment';
        $this->_orderWay = 'DESC';
    }

    public function displayVieworderLink($token, $id, $name = null)
    {
        $row = Db::getInstance()->getRow('SELECT id_order FROM `' . _DB_PREFIX_ . 'agappmax_payment` WHERE `id_agappmax_payment`=' . (int)$id);
        if (!$row || !$row['id_order']) {
            return '';
        }
        $link = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int)$row['id_order'], 'vieworder' => 1]);
        return '<a class="btn btn-default" href="' . $link . '" target="_blank" title="Ver Pedido"><i class="icon-shopping-cart"></i> Pedido</a>';
    }

    public function getOrderLink($idOrder)
    {
        if (!$idOrder) {
            return '-';
        }
        $link = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int)$idOrder, 'vieworder' => 1]);
        return '<a href="' . $link . '" target="_blank">#' . (int)$idOrder . '</a>';
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['back_to_config'] = [
            'href' => $this->context->link->getAdminLink('AdminAgappmax'),
            'desc' => 'Configurações',
            'icon' => 'process-icon-back',
        ];

        parent::initPageHeaderToolbar();
    }

    public function renderView()
    {
        $id = (int)Tools::getValue('id_agappmax_payment');
        if (!$id) {
            return parent::renderView();
        }
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'agappmax_payment` WHERE `id_agappmax_payment`=' . (int)$id);
        if (!$row) {
            $this->errors[] = 'Transação não encontrada.';
            return parent::renderList();
        }

        // Get order info
        $order = null;
        $orderCustomerName = '';
        $orderStateName = '';
        if ($row['id_order']) {
            $order = new Order((int)$row['id_order']);
            if (!Validate::isLoadedObject($order)) {
                $order = null;
            } else {
                $customer = $order->getCustomer();
                $orderCustomerName = $customer->firstname . ' ' . $customer->lastname;
                $orderState = $order->getCurrentStateFull((int)Configuration::get('PS_LANG_DEFAULT'));
                $orderStateName = isset($orderState['name']) ? $orderState['name'] : '';
            }
        }

        $this->context->smarty->assign([
            'transaction' => $row,
            'order' => $order,
            'order_customer_name' => $orderCustomerName,
            'order_state_name' => $orderStateName,
            'currentIndex' => self::$currentIndex,
            'token' => $this->token,
            'back_url' => $this->context->link->getAdminLink('AdminAgappmax'),
        ]);

        return $this->createTemplate('transaction_view.tpl')->fetch();
    }
}
