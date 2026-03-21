<?php

class AgappmaxWorkerModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;

    public function initContent()
    {
        parent::initContent();

        $idWorker = (int) Tools::getValue('id_agworker');

        // Mantém compatibilidade com o formato de workers do AgCliente
        if (class_exists('AgClienteWorker') && $idWorker) {
            try {
                $agti_worker = new AgClienteWorker($idWorker);
                if (Validate::isLoadedObject($agti_worker)) {
                    // Atualiza o watchdog desta worker
                    $agti_worker->save();
                }
            } catch (Exception $e) {
                // não derruba execução do worker
            }
        }

        /** @var BaseAgappmax $module */
        $module = $this->module;
        $module->runWorker();

        header('Content-Type: text/plain');
        exit('OK');
    }
}
