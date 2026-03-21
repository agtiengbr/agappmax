<div class="row">
  <div class="col-md-3">
    <ul class="nav nav-pills nav-stacked" role="tablist">
      <li class="active"><a href="#tab-cred" role="tab" data-toggle="tab">Geral</a></li>
      <li><a href="#tab-identity" role="tab" data-toggle="tab">Dados do cliente</a></li>
      <li><a href="#tab-pix" role="tab" data-toggle="tab">PIX</a></li>
      <li><a href="#tab-boleto" role="tab" data-toggle="tab">Boleto</a></li>
      <li><a href="#tab-card" role="tab" data-toggle="tab">Cartao</a></li>
      <li><a href="#tab-status" role="tab" data-toggle="tab">Status</a></li>
      <li><a href="#tab-worker" role="tab" data-toggle="tab">Worker</a></li>
      <li><a href="#tab-transactions" role="tab" data-toggle="tab">Transações</a></li>
      <li><a href="#tab-logs" role="tab" data-toggle="tab">Logs API</a></li>
      <li><a href="#tab-maintenance" role="tab" data-toggle="tab">Manutenção</a></li>
    </ul>
  </div>
  <div class="col-md-9">
    <div class="tab-content">
      <div class="tab-pane active" id="tab-cred">{include file='module:agappmax/views/templates/admin/config_credentials.tpl'}</div>
      <div class="tab-pane" id="tab-identity">{include file='module:agappmax/views/templates/admin/config_identity.tpl'}</div>
      <div class="tab-pane" id="tab-pix">{include file='module:agappmax/views/templates/admin/config_pix.tpl'}</div>
      <div class="tab-pane" id="tab-boleto">{include file='module:agappmax/views/templates/admin/config_boleto.tpl'}</div>
      <div class="tab-pane" id="tab-card">{include file='module:agappmax/views/templates/admin/config_card.tpl'}</div>
      <div class="tab-pane" id="tab-status">{include file='module:agappmax/views/templates/admin/config_status.tpl'}</div>
      <div class="tab-pane" id="tab-worker">{include file='module:agappmax/views/templates/admin/config_worker.tpl'}</div>
      <div class="tab-pane" id="tab-transactions">{include file='module:agappmax/views/templates/admin/config_transactions.tpl'}</div>
      <div class="tab-pane" id="tab-logs">{include file='module:agappmax/views/templates/admin/config_logs.tpl'}</div>
      <div class="tab-pane" id="tab-maintenance">{$maintenance_tab_html nofilter}</div>
    </div>
  </div>
</div>
