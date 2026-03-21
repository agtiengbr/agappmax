<?php

/**
 * Controller "container" do menu AppMax.
 * Mantém o item principal apontando para Configurações.
 */
class AdminAgappmaxParentController extends ModuleAdminController
{
    public function initContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminAgappmax'));
    }
}
