<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-cogs"></i> Credenciais e Ambiente</h3>
    <div class="row">
      <div class="col-md-3">
        <label>Sandbox</label>
        <select name="AGAPPMAX_SANDBOX" class="form-control">
          <option value="1" {if $config.SANDBOX}selected{/if}>Sim</option>
          <option value="0" {if !$config.SANDBOX}selected{/if}>Nao</option>
        </select>
      </div>
      <div class="col-md-4">
        <label>API Token (Producao)</label>
        <input type="text" class="form-control" name="AGAPPMAX_API_TOKEN_PROD" value="{$config.API_TOKEN_PROD|escape}">
      </div>
      <div class="col-md-5">
        <label>API Token (Sandbox)</label>
        <input type="text" class="form-control" name="AGAPPMAX_API_TOKEN_SANDBOX" value="{$config.API_TOKEN_SANDBOX|escape}">
      </div>
    </div>
    <div class="row" style="margin-top:15px;">
      <div class="col-md-6">
        <label>Exibir aviso de pedidos sem integração</label>
        <select name="AGAPPMAX_SHOW_MISSING_TRANSACTION_WARNING" class="form-control">
          <option value="1" {if $config.SHOW_MISSING_TRANSACTION_WARNING}selected{/if}>Sim</option>
          <option value="0" {if !$config.SHOW_MISSING_TRANSACTION_WARNING}selected{/if}>Nao</option>
        </select>
        <p class="help-block">Quando desativado, o alerta com a lista de pedidos sem transação vinculada deixa de aparecer no administrativo.</p>
      </div>
    </div>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
