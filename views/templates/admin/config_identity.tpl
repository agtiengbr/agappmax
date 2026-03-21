<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-user"></i> Dados do cliente (CPF/CNPJ e número)</h3>
    <p>Escolha a coluna já existente para cada campo, sem digitar manualmente. Mesmo comportamento do agasaas.</p>

    {if !$identity_has_mapping}
      <div class="alert alert-warning">Não foi possível carregar as opções de mapeamento. Verifique se a classe de mapeamento está disponível.</div>
    {else}
      <div class="row">
        <div class="col-md-6">
          <label>CPF (tabela customer)</label>
          <select name="AGAPPMAX_CPF_FIELD" class="form-control">
            {foreach from=$identity_options.cpf item=opt}
              <option value="{$opt.id|escape}" {if $config.CPF_FIELD == $opt.id}selected{/if}>{$opt.name|escape}</option>
            {/foreach}
          </select>
        </div>
        <div class="col-md-6">
          <label>CNPJ (tabela customer)</label>
          <select name="AGAPPMAX_CNPJ_FIELD" class="form-control">
            {foreach from=$identity_options.cnpj item=opt}
              <option value="{$opt.id|escape}" {if $config.CNPJ_FIELD == $opt.id}selected{/if}>{$opt.name|escape}</option>
            {/foreach}
          </select>
        </div>
      </div>

      <div class="row" style="margin-top:10px">
        <div class="col-md-6">
          <label>Razão Social (tabela customer, opcional)</label>
          <select name="AGAPPMAX_SOCIAL_NAME_FIELD" class="form-control">
            {foreach from=$identity_options.social item=opt}
              <option value="{$opt.id|escape}" {if $config.SOCIAL_NAME_FIELD == $opt.id}selected{/if}>{$opt.name|escape}</option>
            {/foreach}
          </select>
        </div>
        <div class="col-md-6">
          <label>Número do endereço (tabela address)</label>
          <select name="AGAPPMAX_ADDRESS_NUMBER_FIELD" class="form-control">
            {foreach from=$identity_options.address_number item=opt}
              <option value="{$opt.id|escape}" {if $config.ADDRESS_NUMBER_FIELD == $opt.id}selected{/if}>{$opt.name|escape}</option>
            {/foreach}
          </select>
        </div>
      </div>
    {/if}

    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit">
        <i class="icon-save"></i> Salvar
      </button>
    </div>
  </div>
</form>
