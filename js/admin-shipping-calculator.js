jQuery(document).ready(function($) {
    $(document).on('change', '.shipping_method', function(e) {
        var target = $(e.target);
        var priceElement = target.parents('.shipping').find('.wc_input_price.line_total');
        var taxElement = target.parents('.shipping').find('.wc_input_price.line_tax');
        var shipping = target.val();
        target.prop('disabled', true);
        priceElement.length > 0 && priceElement.prop('disabled', true);
        taxElement.length > 0 && taxElement.prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: shipping_calculator.url,
            data: {
                action: 'admin_shipping_calculate',
                nonce: shipping_calculator.nonce,
                products: $('#order_line_items .item').toArray().map(function(item) {
                    return $(item).find('input.order_item_id').val();
                }),
                country: $('#_shipping_country').val() || $('#_billing_country').val(),
                state: $('#_shipping_state').val() || $('#_billing_state').val(),
                postcode: $('#_shipping_postcode').val() || $('#_billing_postcode').val(),
                shipping: shipping
            },
            success: function (response) {
                target.prop('disabled', false);
                priceElement.length > 0 && priceElement.prop('disabled', false);
                taxElement.length > 0 && taxElement.prop('disabled', false);
                var foundRate = response.data.shipping.find(function(rate) {
                    return rate.method.toLowerCase() === shipping.toLowerCase();
                });

                if (!foundRate) {
                    foundRate = response.data.shipping.find(function(rate) {
                        return rate.method.toLowerCase().match(shipping.toLowerCase());
                    });
                }

                priceElement.length > 0 && priceElement.val(foundRate ? foundRate.total : 0);
                taxElement.length > 0 && taxElement.val(foundRate ? foundRate.tax : 0);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                target.prop('disabled', false);
                priceElement.length > 0 && priceElement.prop('disabled', false);
                taxElement.length > 0 && taxElement.prop('disabled', false);
                alert(errorThrown);
            }
        });
    });
});
