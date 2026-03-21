<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-credit-card"></i> Cartao de credito</h3>
    <div class="row">
      <div class="col-md-3">
        <label>Status</label>
        <select name="AGAPPMAX_ENABLE_CARD" class="form-control">
          <option value="1" {if $config.ENABLE_CARD}selected{/if}>Habilitado</option>
          <option value="0" {if !$config.ENABLE_CARD}selected{/if}>Desabilitado</option>
        </select>
      </div>
      <div class="col-md-3">
        <label>Texto no checkout</label>
        <input type="text" class="form-control" name="AGAPPMAX_LABEL_CARD" value="{$config.LABEL_CARD|escape}" placeholder="Cartao de credito (AppMax)">
      </div>
      <div class="col-md-3">
        <label>Valor minimo da parcela (R$)</label>
        <input type="number" step="0.01" min="0" class="form-control" name="AGAPPMAX_CARD_MIN_INSTALLMENT" value="{$config.CARD_MIN_INSTALLMENT|escape}">
      </div>
      <div class="col-md-3">
        <label>Maximo de parcelas</label>
        <input type="number" min="1" class="form-control" name="AGAPPMAX_CARD_MAX_INSTALLMENTS" value="{$config.CARD_MAX_INSTALLMENTS|escape}">
      </div>
    </div>
    <div class="row" style="margin-top:10px;">
      <div class="col-md-4">
        <label>Texto na coluna Pagamento (pedido)</label>
        <input type="text" class="form-control" name="AGAPPMAX_ORDER_PAYMENT_LABEL_CARD" value="{$config.ORDER_PAYMENT_LABEL_CARD|escape}" placeholder="AppMax (Cartao)">
      </div>
    </div>

    <div class="row" style="margin-top:20px;">
      <div class="col-md-6">
        <label>Descriptor na fatura (soft descriptor)</label>
        <input type="text" class="form-control" name="AGAPPMAX_CARD_SOFT_DESCRIPTOR" value="{$config.CARD_SOFT_DESCRIPTOR|escape}" placeholder="{$config.SHOP_NAME|escape}" maxlength="13">
        <small class="text-muted">Máximo de 13 caracteres. Se vazio, usa o nome da loja.</small>
      </div>
      <div class="col-md-6">
        <label>Exibir cartão apenas para clientes com uma venda válida</label>
        <select name="AGAPPMAX_CARD_REQUIRE_VALID_ORDER" class="form-control">
          <option value="0" {if !$config.CARD_REQUIRE_VALID_ORDER}selected{/if}>Não</option>
          <option value="1" {if $config.CARD_REQUIRE_VALID_ORDER}selected{/if}>Sim</option>
        </select>
        <small class="text-muted">Quando ativado, o cartão de crédito só aparece se o cliente já tiver pelo menos um pedido válido (valid=1).</small>
      </div>
    </div>
    <div class="row" style="margin-top:20px;">
      <div class="col-md-12">
        <label>Taxa de juros por parcela (%)</label>
        <div class="row">
          {assign var=max_parc value=$config.CARD_MAX_INSTALLMENTS|default:12}
          {section name=parc start=1 loop=$max_parc+1}
            <div class="col-md-2" style="margin-bottom:8px;">
              <label style="font-weight:normal;">{if $smarty.section.parc.index == 1}1x (à vista){else}{$smarty.section.parc.index}x{/if}</label>
              <input type="number" step="0.01" min="0" class="form-control" name="AGAPPMAX_CARD_INSTALLMENT_FEE_{$smarty.section.parc.index}" value="{if isset($config["CARD_INSTALLMENT_FEE_"|cat:$smarty.section.parc.index])}{$config["CARD_INSTALLMENT_FEE_"|cat:$smarty.section.parc.index]|escape}{else}0{/if}">
            </div>
          {/section}
        </div>
        <small class="text-muted">Configure a taxa de juros para cada quantidade de parcelas. Exemplo: 0 para sem juros.</small>
      </div>
    </div>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>
