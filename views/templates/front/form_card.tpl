<div class="form-group">
  <label for="agappmax_card_name">Nome impresso</label>
  <input type="text" id="agappmax_card_name" name="agappmax_card_name" class="form-control" required="required" />
</div>
<div class="form-group">
  <label for="agappmax_card_number">Numero do cartao</label>
  <input type="text" id="agappmax_card_number" name="agappmax_card_number" class="form-control" required="required" placeholder="0000 0000 0000 0000" />
</div>
<div class="form-group">
  <label for="agappmax_card_month">Mes de validade (MM)</label>
  <input type="text" id="agappmax_card_month" name="agappmax_card_month" class="form-control" required="required" maxlength="2" placeholder="MM" />
</div>
<div class="form-group">
  <label for="agappmax_card_year">Ano de validade (AA)</label>
  <input type="text" id="agappmax_card_year" name="agappmax_card_year" class="form-control" required="required" maxlength="2" placeholder="AA" />
</div>
<div class="form-group">
  <label for="agappmax_card_cvv">CVV</label>
  <input type="password" id="agappmax_card_cvv" name="agappmax_card_cvv" class="form-control" required="required" maxlength="4" placeholder="***" />
</div>
<div class="form-group">
  <label for="agappmax_installments">Parcelas</label>
  <select id="agappmax_installments" name="agappmax_installments" class="form-control" required="required">
    {assign var=max_parc value=$config.CARD_MAX_INSTALLMENTS|default:12}
    {assign var=min_parc value=$config.CARD_MIN_INSTALLMENT|default:5}
    {assign var=valor_total value=$agappmax_card_total|default:0}
    {for $i=1 to $max_parc}
      {assign var=parcela value=($valor_total/$i)}
      {* Always show 1x option, even if total is less than min_parc *}
      {if $i == 1 || $parcela >= $min_parc}
        {assign var=fee_key value="CARD_INSTALLMENT_FEE_"|cat:$i}
        {assign var=fee value=$config[$fee_key]|default:0}
        {if $fee > 0}
          {assign var=total value=($valor_total*(1+($fee/100)))}
          <option value="{$i}">
            {$i}x R$ {($total/$i)|number_format:2:',':'.'} (Total de R$ {$total|number_format:2:',':'.'} com juros)
          </option>
        {else}
          <option value="{$i}">
            {$i}x R$ {($valor_total/$i)|number_format:2:',':'.'} (Total de R$ {$valor_total|number_format:2:',':'.'} sem juros)
          </option>
        {/if}
      {/if}
    {/for}
  </select>
</div>
