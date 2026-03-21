<?php

/**
 * Recria os menus (Tabs) do módulo.
 * A Tab pai AdminAgappmaxParent redireciona para AdminAgappmax (Configurações).
 */
function upgrade_module_2_0_2($module)
{
    if (!is_object($module) || !method_exists($module, 'RemakeMenus')) {
        return true;
    }

    $module->RemakeMenus();
    return true;
}
