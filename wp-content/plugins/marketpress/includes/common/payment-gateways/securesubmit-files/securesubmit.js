(function ($) {
    $(document).on('mp_checkout_process_securesubmit', securesubmitProcessCheckout);

    function securesubmitProcessCheckout(e, $form) {
        marketpress.loadingOverlay('show');
        e.preventDefault();
        try {
            (new HPS({
                publicKey: securesubmit_token.public_key,
                cardNumber: $('#cc_number').val().replace(/\D/g, ''),
                cardCvv: $('#cc_cvv2').val(),
                cardExpMonth: $('#cc_month').val(),
                cardExpYear: $('#cc_year').val(),
                // Callback when a token is received from the service
                success: function (resp) {
                    secureSubmitResponseHandler(resp);
                },
                // Callback when an error is received from the service
                error: function (resp) {
                    secureSubmitResponseHandler(resp);
                }
                // Immediately call the tokenize method to get a token
            })).tokenize();
        } catch (e) {
            securesubmit_error_message('show', 'There was an issue submitting. Are all of the fields filled out?');
        }
        return false;
    }

    function secureSubmitResponseHandler(response) {
        if (response.error !== undefined && response.error.message !== undefined) {
            marketpress.loadingOverlay('hide');
            securesubmit_error_message('show', response.error.message);
        } else {
            securesubmit_error_message('hide');
            var $form = $('#mp-checkout-form');
            
            $('<input />')
                    .attr({type: "hidden", name: "securesubmitToken"})
                    .val(response.token_value)
                    .appendTo($('#mp-gateway-form-securesubmit'));

            $form.get(0).submit();
        }
        marketpress.loadingOverlay('hide');
        return false;
    }

    /**
     * Show/hide the payment error message
     *
     * @since 3.0
     * @param string action Either "show" or "hide".
     * @param string message The message to show. Required if action is "show".
     */
    function securesubmit_error_message(action, message) {
        var $errors = $('#mp-checkout-payment-form-errors');

        if ('show' == action) {
            alert(message);
            $errors.html('<p>' + message + '</p>').addClass('show');
            $('#mp-checkout-form .button').attr('onclick', '').unbind('click');
            $('#mp-checkout-form').unbind('submit');
        } else {
            $errors.removeClass('show');
        }
    }

}(jQuery));