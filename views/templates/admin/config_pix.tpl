<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-barcode"></i> PIX</h3>
    <div class="row">
      <div class="col-md-4">
        <label>Status</label>
        <select name="AGAPPMAX_ENABLE_PIX" class="form-control">
          <option value="1" {if $config.ENABLE_PIX}selected{/if}>Habilitado</option>
          <option value="0" {if !$config.ENABLE_PIX}selected{/if}>Desabilitado</option>
        </select>
      </div>
      <div class="col-md-4">
        <label>Texto no checkout</label>
        <input type="text" class="form-control" name="AGAPPMAX_LABEL_PIX" value="{$config.LABEL_PIX|escape}" placeholder="Pagar com PIX (AppMax)">
      </div>
      <div class="col-md-4">
        <label>ID de cupom (CartRule) opcional</label>
        <input type="text" class="form-control" name="AGAPPMAX_COUPON_ID_PIX" value="{$config.COUPON_ID_PIX|escape}">
      </div>
    </div>
    <div class="row" style="margin-top:10px">
      <div class="col-md-4">
        <label>Texto na coluna Pagamento (pedido)</label>
        <input type="text" class="form-control" name="AGAPPMAX_ORDER_PAYMENT_LABEL_PIX" value="{$config.ORDER_PAYMENT_LABEL_PIX|escape}" placeholder="AppMax (PIX)">
      </div>
      <div class="col-md-4">
        <label>Validade do PIX (dias)</label>
        <input type="number" min="1" class="form-control" name="AGAPPMAX_PIX_EXPIRATION_DAYS" value="{$config.PIX_EXPIRATION_DAYS|intval}">
      </div>
    </div>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
