/**
 * AgAppMax - Input Masks
 * Uses IMask.js for CPF/CNPJ, Credit Card Number, CVV, Month and Year masking
 */
document.addEventListener('DOMContentLoaded', function() {
    // CPF/CNPJ Mask - Dynamic pattern based on length
    var documentInputs = document.querySelectorAll('input[name="agappmax_document"]');
    documentInputs.forEach(function(element) {
        if (typeof IMask !== 'undefined') {
            IMask(element, {
                mask: [
                    {
                        mask: '000.000.000-00',
                        lazy: false
                    },
                    {
                        mask: '00.000.000/0000-00',
                        lazy: false
                    }
                ],
                dispatch: function(appended, dynamicMasked) {
                    var number = (dynamicMasked.value + appended).replace(/\D/g, '');
                    return dynamicMasked.compiledMasks.find(function(m) {
                        return number.length <= 11 ? m.mask === '000.000.000-00' : m.mask === '00.000.000/0000-00';
                    });
                }
            });
        }
    });

    // Credit Card Number Mask - xxxx xxxx xxxx xxxx
    var cardNumberInputs = document.querySelectorAll('input[name="agappmax_card_number"]');
    cardNumberInputs.forEach(function(element) {
        if (typeof IMask !== 'undefined') {
            IMask(element, {
                mask: '0000 0000 0000 0000',
                lazy: false
            });
        }
    });

    // Month Mask - 2 digits only (01-12)
    var monthInputs = document.querySelectorAll('input[name="agappmax_card_month"]');
    monthInputs.forEach(function(element) {
        if (typeof IMask !== 'undefined') {
            IMask(element, {
                mask: '00',
                lazy: true
            });
        }
    });

    // Year Mask - 2 digits only
    var yearInputs = document.querySelectorAll('input[name="agappmax_card_year"]');
    yearInputs.forEach(function(element) {
        if (typeof IMask !== 'undefined') {
            IMask(element, {
                mask: '00',
                lazy: true
            });
        }
    });

    // CVV - Already password type in HTML, but add mask for 3-4 digits
    var cvvInputs = document.querySelectorAll('input[name="agappmax_card_cvv"]');
    cvvInputs.forEach(function(element) {
        if (typeof IMask !== 'undefined') {
            IMask(element, {
                mask: '0000',
                lazy: true
            });
        }
    });
});
