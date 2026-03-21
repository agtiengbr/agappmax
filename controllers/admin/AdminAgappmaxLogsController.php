<?php

class AdminAgappmaxLogsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'agappmax_api_log';
        $this->className = '';
        $this->identifier = 'id_log';
        $this->lang = false;
        $this->bootstrap = true;

        parent::__construct();

        $this->meta_title = 'Requisições API';
        $this->page_header_toolbar_title = 'Requisições API';

        $this->fields_list = [
            'id_log' => ['title' => 'ID', 'align' => 'center', 'width' => 50],
            'method' => ['title' => 'Método', 'width' => 70],
            'url' => ['title' => 'URL', 'width' => 400],
            'response_code' => ['title' => 'HTTP', 'align' => 'center', 'width' => 60],
            'duration_ms' => ['title' => 'Duração (ms)', 'align' => 'right', 'width' => 80],
            'created_at' => ['title' => 'Data', 'width' => 120],
        ];

        $this->addRowAction('view');
        $this->list_no_link = true;
        $this->_orderBy = 'id_log';
        $this->_orderWay = 'DESC';
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
        $id = (int)Tools::getValue('id_log');
        if (!$id) {
            return parent::renderView();
        }
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'agappmax_api_log` WHERE `id_log`=' . (int)$id);
        if (!$row) {
            return $this->renderError('Log não encontrado.');
        }

        $this->context->smarty->assign([
            'log' => $row,
            'currentIndex' => self::$currentIndex,
            'token' => $this->token,
        ]);

        return $this->createTemplate('log_view.tpl')->fetch();
    }

    protected function renderError($msg)
    {
        $this->errors[] = $msg;
        return parent::renderList();
    }
}
