function processUsingPaydunk(clientId, orderNumber, totalPrice, tax, shipping) {

    document.getElementById("pd-client_id").value = clientId;
    document.getElementById("pd-order_number").value = orderNumber;
    document.getElementById("pd-price").value = totalPrice;
    document.getElementById("pd-tax").value = tax;
    document.getElementById("pd-shipping").value = shipping;

    var errorElem = document.getElementsByClassName('woocommerce-error');
    if (errorElem.length > 0) {
        for (var i=0; i<errorElem.length; i++) {
            errorElem[i].remove();
        }
    }
    document.getElementById("pd-paydunkButton").dispatchEvent(new MouseEvent('click'));
}

jQuery(document).ready(function($) {

    var formCheckout = 'form.woocommerce-checkout';
    var paymentMethodSelection = 'input[name="payment_method"]';

    var paydunkOption = $(formCheckout).find('#payment_method_paydunk');
    if (paydunkOption.length > 0 && paydunkOption.is(':checked')) {
        $('#customer_details').hide();
    }

    $(formCheckout).on('change', paymentMethodSelection, function() {
        var placeOrderButton = '#place_order';
        if ($(this).val() == 'paydunk') {
            $('#customer_details').hide();
            $(placeOrderButton).addClass('paydunk-order-button');
        } else {
            $('#customer_details').show();
            $(placeOrderButton).removeClass('paydunk-order-button');
        }
    });

    var bodySelector = 'body';
    $(bodySelector).on('click', '#paydunk_button_oncart', function() {
        var data = { 'action': 'paydunk_create_order_from_cart' };
        $.blockUI({ message: '' });
        $.ajax({
            url: ajaxurl,
            data: data,
            method: 'POST',
            success: function(response) {
                if (typeof response == 'string') {
                    response = $('<div />').html(response).text();
                    response = JSON.parse(response);
                }

                if (response.length == 0 || response['result'] == 'error') {
                    if (checkoutURL.indexOf('?') > 0) {
                        checkoutURL = checkoutURL + "&process_by=" + response['process_by'];
                    } else {
                        checkoutURL = checkoutURL + "?process_by=" + response['process_by'];
                    }
                    window.location.href = checkoutURL;
                } else {
                    if (response['redirect']) {
                        window.location.href = response['redirect'];
                    }
                }
            },
            error: function(x, y) {
                if (checkoutURL.indexOf('?') > 0) {
                    checkoutURL = checkoutURL + "&process_by=paydunk";
                } else {
                    checkoutURL = checkoutURL + "?process_by=paydunk"
                }
                window.location.href = checkoutURL;
            }
        });
        //$.getJSON(ajaxurl, data, );
    });

    $(bodySelector).on('click', '#pd-main-close-button', function() {
        window.location.reload();
    });
});