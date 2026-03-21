<form method="post" action="{$form_action}">
  <div class="panel">
    <h3><i class="icon-random"></i> Mapeamento de status (AppMax → Estado do pedido)</h3>
    <p>Defina o estado do pedido para cada status recebido do AppMax (opcional). Se vazio, o pedido nao sera alterado automaticamente.</p>
    <div class="row">
      {foreach from=$appmax_statuses item=st}
        <div class="col-md-6">
          <div class="well" style="padding:12px;">
            <div class="form-group">
              <label style="font-weight:bold">{$st}</label>
              <select class="form-control" name="AGAPPMAX_MAP_STATUS_{$st}">
                <option value="0">-- nao alterar --</option>
                {foreach from=$order_states item=os}
                  <option value="{$os.id}" {if $config.STATUS_MAP[$st] == $os.id}selected{/if}>{$os.name} ({$os.color})</option>
                {/foreach}
              </select>
            </div>
            <div class="form-group" style="margin-top:8px">
              <label style="display:block;font-weight:normal">Comportamento</label>
              <select class="form-control" name="AGAPPMAX_MAP_BEHAVIOR_{$st}">
                <option value="close" {if $config.STATUS_BEHAVIOR[$st]==='close'}selected{/if}>Fechar pedido (se aplicavel)</option>
                <option value="error" {if $config.STATUS_BEHAVIOR[$st]==='error'}selected{/if}>Exibir erro ao cliente</option>
              </select>
            </div>
          </div>
        </div>
      {/foreach}
    </div>
    <div class="panel-footer">
      <button name="submitAgappmax" class="btn btn-primary" type="submit"><i class="icon-save"></i> Salvar</button>
    </div>
  </div>
</form>

<div class="panel">
  <div class="panel-heading"><i class="icon-bell"></i> Webhook da AppMax</div>
  <div class="panel-body">
    <div class="alert alert-info">
      <p><strong>Como funciona:</strong></p>
      <ul>
        <li>O webhook permite que a AppMax notifique automaticamente seu sistema sobre mudanças de status de pagamentos</li>
        <li>Configure esta URL no painel da AppMax para receber notificações em tempo real</li>
        <li>Apenas eventos de mudança de status mapeados serão processados</li>
        <li>Todos os eventos são registrados em log para auditoria</li>
      </ul>
    </div>

    <div class="form-group">
      <label class="control-label"><strong>URL do Webhook</strong></label>
      <div class="input-group">
        <input type="text" class="form-control" value="{$webhook_url|escape:'html':'UTF-8'}" readonly onclick="this.select()">
        <span class="input-group-btn">
          <button class="btn btn-default" type="button" onclick="copyWebhookUrl()" title="Copiar URL">
            <i class="icon-copy"></i> Copiar
          </button>
        </span>
      </div>
      <p class="help-block">
        Configure esta URL no painel da AppMax em: <strong>Configurações &gt; Webhooks</strong><br>
        A URL já inclui o token de segurança necessário para validação.
      </p>
    </div>

    <div class="form-group">
      <label class="control-label"><strong>Token de Segurança</strong></label>
      <div class="input-group">
        <input type="text" class="form-control" value="{$webhook_token|escape:'html':'UTF-8'}" readonly style="font-family: monospace;">
        <span class="input-group-btn">
          <form method="post" action="{$form_action|escape:'html':'UTF-8'}" style="display:inline-block;">
            <button class="btn btn-warning" type="submit" name="regenerateWebhookToken" 
                    style="min-width: 160px; white-space: nowrap;"
                    onclick="return confirm('Tem certeza? Você precisará atualizar a URL no painel da AppMax.')">
              <i class="icon-refresh"></i> Regenerar Token
            </button>
          </form>
        </span>
      </div>
      <p class="help-block">
        O token garante que apenas a AppMax possa enviar webhooks válidos para seu sistema.<br>
        <strong>Importante:</strong> Ao regenerar o token, a URL do webhook muda. Atualize no painel da AppMax!
      </p>
    </div>

    <hr>

    <div class="alert alert-warning">
      <h4><i class="icon-warning"></i> Eventos Processados</h4>
      <p>O webhook processa apenas os seguintes eventos:</p>
      <ul>
        <li><code>payment.updated</code></li>
        <li><code>payment.status_changed</code></li>
        <li><code>order.status_changed</code></li>
      </ul>
      <p>Apenas status mapeados acima serão aplicados aos pedidos.</p>
    </div>

    <div class="alert alert-success">
      <h4><i class="icon-check"></i> Logs</h4>
      <p>
        Todos os webhooks recebidos são registrados nos logs do PrestaShop.<br>
        Acesse: <strong>Configurações Avançadas &gt; Logs</strong> e filtre por "AGAPPMAX WEBHOOK" para visualizar.
      </p>
    </div>

  </div>
</div>

<div class="panel">
  <div class="panel-heading"><i class="icon-flask"></i> Simulador de Webhook</div>
  <div class="panel-body">
    <div class="alert alert-info">
      <p><strong>Teste o processamento de webhooks sem depender da AppMax</strong></p>
      <p>Simule mudanças de status para qualquer pedido existente. O simulador chama diretamente a rotina de processamento, funcionando mesmo com a manutenção ativada.</p>
    </div>

    <form method="post" action="{$form_action|escape:'html':'UTF-8'}">
      <div class="row">
        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">ID do Pedido</label>
            <input type="number" name="webhook_sim_order" class="form-control" placeholder="Ex: 123" min="1" required>
            <p class="help-block">Digite o ID do pedido PrestaShop</p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">Tipo de Evento</label>
            <select name="webhook_sim_event" class="form-control" required>
              <option value="payment.status_changed">payment.status_changed</option>
              <option value="payment.updated">payment.updated</option>
              <option value="order.status_changed">order.status_changed</option>
            </select>
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">Novo Status</label>
            <select name="webhook_sim_status" class="form-control" required>
              {foreach from=$appmax_statuses item=st}
                {if $config.STATUS_MAP[$st] > 0}
                  <option value="{$st}">{$st}</option>
                {/if}
              {/foreach}
            </select>
            <p class="help-block">Apenas status mapeados</p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-group">
            <label class="control-label">&nbsp;</label>
            <div class="checkbox">
              <label>
                <input type="checkbox" name="webhook_sim_dryrun" value="1">
                <strong>Dry-run</strong> (não altera pedido)
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="panel-footer">
        <button type="submit" name="simulateWebhook" class="btn btn-info">
          <i class="icon-flask"></i> Simular Webhook
        </button>
      </div>
    </form>

    <hr>

    <div class="alert alert-warning">
      <h4><i class="icon-info"></i> Como usar</h4>
      <ol>
        <li>Escolha <strong>qualquer pedido existente</strong> (não precisa ter pagamento AppMax registrado)</li>
        <li>Selecione o <strong>tipo de evento</strong> (geralmente payment.status_changed)</li>
        <li>Escolha o <strong>novo status</strong> que deseja aplicar ao pedido</li>
        <li>Marque <strong>Dry-run</strong> para simular sem alterar o pedido de verdade (recomendado para testes)</li>
        <li>Clique em <strong>Simular Webhook</strong> e verifique o resultado e os logs</li>
      </ol>
      <p><strong>Nota:</strong> Se o pedido não tiver pagamento AppMax registrado, um registro simulado será criado automaticamente.</p>
      <p><strong>Logs:</strong> A simulação é registrada com o marcador <code>[AGAPPMAX WEBHOOK SIMULATOR]</code></p>
    </div>

  </div>
</div>

