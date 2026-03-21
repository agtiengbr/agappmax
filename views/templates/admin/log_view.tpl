<div class="panel">
  <h3><i class="icon-search"></i> Detalhes do Log #{$log.id_log|intval}</h3>
  <table class="table">
    <tr><th>Método</th><td>{$log.method|escape}</td></tr>
    <tr><th>URL</th><td style="word-break:break-all;">{$log.url|escape}</td></tr>
    <tr><th>HTTP</th><td>{$log.response_code|intval}</td></tr>
    <tr><th>Duração (ms)</th><td>{$log.duration_ms|intval}</td></tr>
    <tr><th>Data</th><td>{$log.created_at|escape}</td></tr>
  </table>

  <h4>Headers da requisição</h4>
  <pre style="white-space:pre-wrap;">{$log.request_headers|escape}</pre>

  <h4>Body da requisição</h4>
  <pre style="white-space:pre-wrap;">{$log.request_body|escape}</pre>

  <h4>Headers da resposta</h4>
  <pre style="white-space:pre-wrap;">{$log.response_headers|escape}</pre>

  <h4>Body da resposta</h4>
  <pre style="white-space:pre-wrap;">{$log.response_body|escape}</pre>

  <div class="panel-footer">
    <a class="btn btn-default" href="{$currentIndex|escape}&token={$token|escape}"><i class="icon-arrow-left"></i> Voltar à lista</a>
    <a class="btn btn-default" href="{$link->getAdminLink('AdminAgappmax')}"><i class="icon-cog"></i> Configurações</a>
  </div>
</div>
