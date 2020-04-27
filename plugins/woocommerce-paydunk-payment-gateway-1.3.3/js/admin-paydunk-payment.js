jQuery(document).ready(function($) {

    $('#woocommerce_paydunk_payment_acceptant_url').attr('readonly', 'readonly');
    $('#woocommerce_paydunk_payment_redirect_url').attr('readonly', 'readonly');

    function hideAllPaymentCode()
    {
        $('.authorize_net').closest('tr').hide();
        $('.paypal').closest('tr').hide();
    }

    function showPaymentConfirmationOnCart()
    {
        if ($('#woocommerce_paydunk_show_on_cart').is(':checked')) {
            $('#woocommerce_paydunk_enable_cart_confirmation').closest('tr').show();
        } else {
            $('#woocommerce_paydunk_enable_cart_confirmation').closest('tr').hide();
        }
    }
    showPaymentConfirmationOnCart();

    $('#woocommerce_paydunk_show_on_cart').on('change', function() {
        showPaymentConfirmationOnCart();
    });

    function showSelectedPaymentmethod(triggrerBySelector)
    {
        hideAllPaymentCode();

        if ($(triggrerBySelector).val() == 'authorize_net') {
            $('.authorize_net').closest('tr').show();
        }
        if ($(triggrerBySelector).val() == 'paypal') {
            $('.paypal').closest('tr').show();
        }
    }
    showSelectedPaymentmethod('#woocommerce_paydunk_process_payment_by');

    $('#woocommerce_paydunk_process_payment_by').on('change', function() {
        showSelectedPaymentmethod(this);
    });
});