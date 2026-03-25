document.addEventListener('DOMContentLoaded', function () {
    var payment_confirmation = document.getElementById('payment-confirmation');
    if (!payment_confirmation) return;

    var btn_submit = payment_confirmation.getElementsByTagName('button')[0];
    if (!btn_submit) return;

    btn_submit.addEventListener('click', function () {
        // Só aciona o overlay se houver algum formulário agappmax visível/ativo
        var forms = document.querySelectorAll('.agappmax-payment-form');
        if (forms.length === 0) return;

        if (typeof loadingOverlay !== 'undefined') {
            loadingOverlay().activate();
        }
    });
});
