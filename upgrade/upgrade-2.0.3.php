<?php

/**
 * Adiciona flag para exibir cartão apenas para clientes com pedido válido.
 */
function upgrade_module_2_0_3($module)
{
    if (!is_object($module)) {
        return true;
    }

    $key = $module::CONFIG_PREFIX . $module::CFG_CARD_REQUIRE_VALID_ORDER;
    if (Configuration::get($key) === false) {
        Configuration::updateValue($key, 0);
    }

    return true;
}
