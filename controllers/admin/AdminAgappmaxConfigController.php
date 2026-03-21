<?php

class AdminAgappmaxConfigController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminAgappmax'));
    }
}
