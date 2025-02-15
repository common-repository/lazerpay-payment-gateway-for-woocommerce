jQuery( function( $ ) {

    if ( 'processing' === tbz_wc_lazerpay_params.order_status || 'completed' === tbz_wc_lazerpay_params.order_status) {
        return;
    }

    let lazerpay_submit = false;

    $( '#wc-lazerpay-form' ).hide();

    tbzWcLazerpayPaymentHandler();

    $( '#wc-lazerpay-payment-button' ).click( function() {
        tbzWcLazerpayPaymentHandler();
    } );

    function tbzWcLazerpayPaymentHandler() {

        $( '#wc-lazerpay-form' ).hide();

        if ( lazerpay_submit ) {
            lazerpay_submit = false;
            return true;
        }

        let $form = $( 'form#payment-form, form#order_review' ),
            lazerpay_txn_ref = $form.find( 'input.tbz_wc_lazerpay_txn_ref' );

        lazerpay_txn_ref.val( '' );

        const lazerpay_callback = function( response ) {

            if ( 'incomplete' === response.status ) {
                return;
            }

            $form.append( '<input type="hidden" class="tbz_wc_lazerpay_txn_ref" name="tbz_wc_lazerpay_txn_ref" value="' + response.reference + '"/>' );

            lazerpay_submit = true;

            $form.submit();

            $( 'body' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                },
                css: {
                    cursor: "wait"
                }
            });
        };

        const lazerpayCheckout = new LazerCheckout( {
            amount: tbz_wc_lazerpay_params.amount,
            email: tbz_wc_lazerpay_params.customer_email,
            key: tbz_wc_lazerpay_params.public_key,
            name: tbz_wc_lazerpay_params.customer_name,
            currency: tbz_wc_lazerpay_params.currency,
            reference: tbz_wc_lazerpay_params.reference,
            metadata: {
                order_id: tbz_wc_lazerpay_params.order_id,
            },
            onClose: function() {
                $( this.el ).unblock();
                $( '#wc-lazerpay-form' ).show();
            },
            onSuccess: lazerpay_callback,
        } );
    }

} );