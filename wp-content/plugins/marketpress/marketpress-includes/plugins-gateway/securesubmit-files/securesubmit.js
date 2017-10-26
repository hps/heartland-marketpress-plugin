function secureSubmitResponseHandler(response) {
    if (response.message) {
        jQuery("#securesubmit_checkout_errors").append('<div class="mp_checkout_error">' + response.message + '</div>');
    } else {
        jQuery("#mp_payment_form").append("<input type='hidden' name='securesubmitToken' value='" + response.token_value + "' />");
        jQuery("#mp_payment_form").get(0).submit();
    }
}

jQuery(document).ready(function ($) {
    $("#mp_payment_form").submit(function (event) {
        if ($('input.mp_choose_gateway').length) {
            if ($('input.mp_choose_gateway:checked').val() != "securesubmit") {
                return true;
            }
        }
        $('#mp_payment_confirm').attr("disabled", "disabled").hide();
        $('#securesubmit_processing').show();

        hps.tokenize({
            data: {
                public_key: securesubmit_token.public_key,
                number: $('#cc_number').val().replace(/\D/g, ''),
                cvc: $('#cc_cvv2').val(),
                exp_month: $('#cc_month').val(),
                exp_year: $('#cc_year').val()
            },
            success: function (response) {
                secureSubmitResponseHandler(response);
            },
            error: function (response) {
                secureSubmitResponseHandler(response);
            }
        });

        return false;
    });
});