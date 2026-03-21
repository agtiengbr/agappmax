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
        <input type="text" class="form-control" value="{$webhook_token|escape:'html':'UTF-8'}" readonly>
        <span class="input-group-btn">
          <form method="post" action="{$form_action|escape:'html':'UTF-8'}" style="display:inline-block;">
            <button class="btn btn-warning" type="submit" name="regenerateWebhookToken" 
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
      <p>Apenas status mapeados na aba <strong>Status</strong> serão aplicados aos pedidos.</p>
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

<script>
function copyWebhookUrl() {
  var input = document.querySelector('input[value*="module/agappmax/webhook"]');
  input.select();
  document.execCommand('copy');
  alert('URL do webhook copiada!');
}
</script>
