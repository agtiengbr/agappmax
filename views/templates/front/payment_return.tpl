<section>
  <style>
    .agappmax-oc { margin: 10px 0; }
    .agappmax-qr { margin: 10px 0; }
    .agappmax-qr img {
      display: inline-block;
      width: 100%;
      max-width: 220px;
      border: 1px solid #eee;
      padding: 6px;
      background: #fff;
    }
    @media (min-width: 992px) {
      .agappmax-qr img { max-width: 360px; }
    }
  </style>

  {if isset($agappmax_pix) && $agappmax_pix.is_pix}
    <div class="agappmax-oc" data-agappmax-wrapper>
      <h3>Pagamento via PIX</h3>
      <p>Status: <strong data-agappmax-status>{if $agappmax_pix.status_label}{$agappmax_pix.status_label|escape:'htmlall':'UTF-8'}{elseif $agappmax_pix.status}{$agappmax_pix.status|escape:'htmlall':'UTF-8'}{else}Aguardando pagamento{/if}</strong></p>

      <div class="alert alert-success" data-agappmax-approved {if isset($agappmax_is_paid) && $agappmax_is_paid}style="display:block;"{else}style="display:none;"{/if}>
        {if isset($agappmax_is_paid) && $agappmax_is_paid}
          Pagamento aprovado! Seu pedido foi confirmado.
        {/if}
      </div>

      {if !isset($agappmax_is_paid) || !$agappmax_is_paid}
        <div class="alert alert-info" data-agappmax-waiting style="display:none;"></div>
      {/if}

      {if !isset($agappmax_is_paid) || !$agappmax_is_paid}
        <div class="agappmax-qr">
          <img data-agappmax-qrcode-img alt="QR Code PIX" {if $agappmax_pix.img_base64}src="data:image/png;base64,{$agappmax_pix.img_base64|escape:'htmlall':'UTF-8'}"{/if} />
        </div>

        <div class="agappmax-code" style="margin-top:10px;">
          <button type="button" class="btn btn-primary" data-agappmax-copy-btn>Copiar código PIX</button>
          <pre data-agappmax-code-text style="white-space:pre-wrap;background:#f7f7f7;border:1px dashed #ddd;padding:8px;margin-top:6px;">{if $agappmax_pix.copy_code}{$agappmax_pix.copy_code|escape:'htmlall':'UTF-8'}{/if}</pre>
        </div>

        <p style="margin-top:10px;">Válido até: <strong>{if $agappmax_pix.expires_at}{$agappmax_pix.expires_at|escape:'htmlall':'UTF-8'}{else}-{/if}</strong></p>
        <p class="small text-muted">Pague até a data informada para confirmação automática do pedido.</p>
      {/if}
    </div>
  {/if}

  {if isset($agappmax_boleto) && $agappmax_boleto.is_boleto}
    <div class="agappmax-oc" data-agappmax-wrapper>
      <h3>Pagamento via Boleto</h3>
      <p>Status: <strong data-agappmax-status>{if $agappmax_boleto.status_label}{$agappmax_boleto.status_label|escape:'htmlall':'UTF-8'}{elseif $agappmax_boleto.status}{$agappmax_boleto.status|escape:'htmlall':'UTF-8'}{else}Aguardando pagamento{/if}</strong></p>

      <div class="alert alert-success" data-agappmax-approved {if isset($agappmax_is_paid) && $agappmax_is_paid}style="display:block;"{else}style="display:none;"{/if}>
        {if isset($agappmax_is_paid) && $agappmax_is_paid}
          Pagamento aprovado! Seu pedido foi confirmado.
        {/if}
      </div>

      {if !isset($agappmax_is_paid) || !$agappmax_is_paid}
        <div class="alert alert-info" data-agappmax-waiting style="display:none;"></div>
      {/if}

      {if !isset($agappmax_is_paid) || !$agappmax_is_paid}
        {if $agappmax_boleto.billet_url}
          <div class="agappmax-boleto" style="margin:10px 0;">
            <a class="btn btn-default" href="{$agappmax_boleto.billet_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">Baixar PDF do boleto</a>
          </div>
        {/if}

        <div class="agappmax-barcode" style="margin-top:10px;">
          <button type="button" class="btn btn-primary" data-agappmax-boleto-copy-btn>Copiar código de barras</button>
          <pre data-agappmax-boleto-code-text style="white-space:pre-wrap;background:#f7f7f7;border:1px dashed #ddd;padding:8px;margin-top:6px;">{if $agappmax_boleto.identification_field}{$agappmax_boleto.identification_field|escape:'htmlall':'UTF-8'}{/if}</pre>
        </div>

        <p style="margin-top:10px;">Vencimento: <strong>{if $agappmax_boleto.due_date}{$agappmax_boleto.due_date|escape:'htmlall':'UTF-8'}{else}-{/if}</strong></p>
      {/if}
    </div>
  {/if}

  {if isset($agappmax_card) && $agappmax_card.is_card}
    <div class="agappmax-oc" data-agappmax-wrapper>
      <h3>Pagamento via Cartão de Crédito</h3>
      <p>Status: <strong data-agappmax-status>{if $agappmax_card.status_label}{$agappmax_card.status_label|escape:'htmlall':'UTF-8'}{elseif $agappmax_card.status}{$agappmax_card.status|escape:'htmlall':'UTF-8'}{else}Pagamento processado{/if}</strong></p>

      <div class="alert alert-success" data-agappmax-approved {if isset($agappmax_is_paid) && $agappmax_is_paid}style="display:block;"{else}style="display:none;"{/if}>
        {if isset($agappmax_is_paid) && $agappmax_is_paid}
          Pagamento aprovado! Seu pedido foi confirmado.
        {/if}
      </div>
    </div>
  {/if}
</section>

<script>
  window.agappmaxConfirmation = {if isset($agappmax_confirmation_json)}{$agappmax_confirmation_json nofilter}{else}{}{/if};
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var copyBtnSel = '[data-agappmax-copy-btn]';
  var copyTextSel = '[data-agappmax-code-text]';
  var boletoBtnSel = '[data-agappmax-boleto-copy-btn]';
  var boletoTextSel = '[data-agappmax-boleto-code-text]';

  function handleCopy(btnSelector, textSelector) {
    document.querySelectorAll(btnSelector).forEach(function(btn) {
      btn.addEventListener('click', function() {
        var wrapper = btn.closest('[data-agappmax-wrapper]');
        if (!wrapper) { return; }
        var textEl = wrapper.querySelector(textSelector);
        if (!textEl) { return; }
        var text = (textEl.textContent || '').trim();
        if (!text) { return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).catch(function() {});
        } else {
          var temp = document.createElement('textarea');
          temp.value = text;
          document.body.appendChild(temp);
          temp.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(temp);
        }
      });
    });
  }

  handleCopy(copyBtnSel, copyTextSel);
  handleCopy(boletoBtnSel, boletoTextSel);
});
</script>
