<div class="panel">
  <div class="panel-heading">
    <i class="icon-credit-card"></i> Detalhes da Transação #{$transaction.id_agappmax_payment}
  </div>
  <div class="panel-body">
    <div class="row">
      <div class="col-md-6">
        <table class="table table-bordered">
          <tr>
            <th>ID Interno</th>
            <td>{$transaction.id_agappmax_payment}</td>
          </tr>
          <tr>
            <th>Pedido PrestaShop</th>
            <td>
              {if $transaction.id_order}
                <a href="{$link->getAdminLink('AdminOrders', true, [], ['id_order' => $transaction.id_order, 'vieworder' => 1])}" target="_blank">
                  #{$transaction.id_order}
                </a>
              {else}
                -
              {/if}
            </td>
          </tr>
          <tr>
            <th>Pedido AppMax</th>
            <td>{$transaction.appmax_order_id|escape:'htmlall':'UTF-8'}</td>
          </tr>
          <tr>
            <th>ID Pagamento AppMax</th>
            <td><code>{$transaction.appmax_payment_id|escape:'htmlall':'UTF-8'}</code></td>
          </tr>
          <tr>
            <th>Tipo de Pagamento</th>
            <td>
              {if $transaction.billing_type == 'credit-card'}
                <span class="label label-info">Cartão de Crédito</span>
              {elseif $transaction.billing_type == 'boleto'}
                <span class="label label-warning">Boleto</span>
              {elseif $transaction.billing_type == 'pix'}
                <span class="label label-success">PIX</span>
              {else}
                {$transaction.billing_type|escape:'htmlall':'UTF-8'}
              {/if}
            </td>
          </tr>
          <tr>
            <th>Status</th>
            <td><strong>{$transaction.status|escape:'htmlall':'UTF-8'}</strong></td>
          </tr>
          <tr>
            <th>Criado em</th>
            <td>{$transaction.created_at|escape:'htmlall':'UTF-8'}</td>
          </tr>
          <tr>
            <th>Atualizado em</th>
            <td>{if $transaction.updated_at}{$transaction.updated_at|escape:'htmlall':'UTF-8'}{else}-{/if}</td>
          </tr>
        </table>
      </div>

      <div class="col-md-6">
        {if $transaction.billing_type == 'boleto'}
          <div class="panel panel-default">
            <div class="panel-heading"><i class="icon-barcode"></i> Dados do Boleto</div>
            <div class="panel-body">
              <p><strong>Vencimento:</strong> {if $transaction.boleto_due_date}{$transaction.boleto_due_date|escape:'htmlall':'UTF-8'}{else}-{/if}</p>
              <p><strong>Linha Digitável:</strong><br><code>{if $transaction.boleto_digitable_line}{$transaction.boleto_digitable_line|escape:'htmlall':'UTF-8'}{else}-{/if}</code></p>
              {if $transaction.boleto_url}
                <p><a href="{$transaction.boleto_url|escape:'htmlall':'UTF-8'}" target="_blank" class="btn btn-default"><i class="icon-download"></i> Abrir PDF</a></p>
              {/if}
            </div>
          </div>
        {/if}

        {if $transaction.billing_type == 'pix'}
          <div class="panel panel-default">
            <div class="panel-heading"><i class="icon-qrcode"></i> Dados do PIX</div>
            <div class="panel-body">
              <p><strong>Criação:</strong> {if $transaction.pix_creation_date}{$transaction.pix_creation_date|escape:'htmlall':'UTF-8'}{else}-{/if}</p>
              <p><strong>Expiração:</strong> {if $transaction.pix_expiration_date}{$transaction.pix_expiration_date|escape:'htmlall':'UTF-8'}{else}-{/if}</p>
              {if $transaction.pix_emv}
                <p><strong>EMV (copia e cola):</strong><br><textarea class="form-control" rows="3" readonly>{$transaction.pix_emv|escape:'htmlall':'UTF-8'}</textarea></p>
              {/if}
              {if $transaction.pix_qrcode}
                <p><strong>QR Code:</strong><br><img src="{$transaction.pix_qrcode|escape:'htmlall':'UTF-8'}" alt="QR Code PIX" style="max-width:200px;"></p>
              {/if}
            </div>
          </div>
        {/if}

        {if $order}
          <div class="panel panel-default">
            <div class="panel-heading"><i class="icon-shopping-cart"></i> Dados do Pedido</div>
            <div class="panel-body">
              <p><strong>Cliente:</strong> {$order_customer_name|escape:'htmlall':'UTF-8'}</p>
              <p><strong>Total:</strong> {displayPrice price=$order->total_paid}</p>
              <p><strong>Status Atual:</strong> {$order_state_name|escape:'htmlall':'UTF-8'}</p>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
  <div class="panel-footer">
    <a href="{$currentIndex}&token={$token}" class="btn btn-default"><i class="icon-arrow-left"></i> Voltar à lista</a>
    <a href="{$back_url}" class="btn btn-default"><i class="icon-cog"></i> Configurações</a>
  </div>
</div>
