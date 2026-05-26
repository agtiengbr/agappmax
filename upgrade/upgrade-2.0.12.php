<?php

function upgrade_module_2_0_12($module)
{
    if (!is_object($module)) {
        return true;
    }

    $configKey = $module::CONFIG_PREFIX . $module::CFG_SHOW_MISSING_TRANSACTION_WARNING;
    if (Configuration::get($configKey) === false) {
        Configuration::updateValue($configKey, 1);
    }

    if (method_exists($module, 'isRegisteredInHook') && method_exists($module, 'registerHook')) {
        if (!$module->isRegisteredInHook('displayBackOfficeHeader') && !$module->registerHook('displayBackOfficeHeader')) {
            return false;
        }
    }

    if (method_exists($module, 'ensureTables')) {
        $module->ensureTables();
    }

    return true;
}