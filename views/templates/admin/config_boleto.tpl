<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-file"></i> Boleto</h3>
    <div class="row">
      <div class="col-md-4">
        <label>Status</label>
        <select name="AGAPPMAX_ENABLE_BOLETO" class="form-control">
          <option value="1" {if $config.ENABLE_BOLETO}selected{/if}>Habilitado</option>
          <option value="0" {if !$config.ENABLE_BOLETO}selected{/if}>Desabilitado</option>
        </select>
      </div>
      <div class="col-md-4">
        <label>Texto no checkout</label>
        <input type="text" class="form-control" name="AGAPPMAX_LABEL_BOLETO" value="{$config.LABEL_BOLETO|escape}" placeholder="Boleto bancario (AppMax)">
      </div>
      <div class="col-md-4">
        <label>ID de cupom (CartRule) opcional</label>
        <input type="text" class="form-control" name="AGAPPMAX_COUPON_ID_BOLETO" value="{$config.COUPON_ID_BOLETO|escape}">
      </div>
    </div>
    <div class="row" style="margin-top:10px;">
      <div class="col-md-4">
        <label>Texto na coluna Pagamento (pedido)</label>
        <input type="text" class="form-control" name="AGAPPMAX_ORDER_PAYMENT_LABEL_BOLETO" value="{$config.ORDER_PAYMENT_LABEL_BOLETO|escape}" placeholder="AppMax (Boleto)">
      </div>
      <div class="col-md-4">
        <label>Vencimento (dias uteis)</label>
        <input type="number" min="0" step="1" class="form-control" name="AGAPPMAX_BOLETO_BUSINESS_DAYS" value="{$config.BOLETO_BUSINESS_DAYS|intval}">
      </div>
    </div>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
