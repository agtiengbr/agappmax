<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-cogs"></i> Worker</h3>

    <p>
      Grupo: <code>{$config.WORKER_GROUP_NAME|escape}</code>
    </p>

    <p>
      Status:
      {if $config.WORKER_RUNNING}
        <span class="text-success"><strong>rodando</strong></span>
      {else}
        <span class="text-danger"><strong>parado</strong></span>
      {/if}
      
      {if isset($config.WORKER_COUNT)}
        <span class="text-muted">(workers ativas: {$config.WORKER_COUNT|escape})</span>
      {/if}
    </p>

    <p>
      Última execução registrada: <strong>{if $config.WORKER_LAST_RUN}{$config.WORKER_LAST_RUN|escape}{else}-{/if}</strong>
    </p>

    <p class="text-muted">
      Este módulo usa o sistema de workers do AgCliente.
    </p>

    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
