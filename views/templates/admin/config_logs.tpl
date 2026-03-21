<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-file-text"></i> Logs API</h3>
    <div class="row">
      <div class="col-md-6">
        <label>Retencao de logs (dias)</label>
        <input type="number" min="1" class="form-control" name="AGAPPMAX_LOG_RETENTION_DAYS" value="{$config.LOG_RETENTION_DAYS|intval}">
      </div>
      <div class="col-md-6">
        <label>Intervalo entre limpezas</label>
        <input type="text" class="form-control" value="24 horas (fixo)" readonly>
      </div>
    </div>
    <p>Ultima limpeza: {if $config.LOG_LAST_CLEAN}{$config.LOG_LAST_CLEAN|escape}{else}-{/if}</p>
    <hr>
    <div class="row">
      <div class="col-md-12">
        <a href="{$link->getAdminLink('AdminAgappmaxLogs')}" class="btn btn-default btn-lg btn-block">
          <i class="icon-list"></i> Ver Requisições API
        </a>
      </div>
    </div>
    <hr>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
