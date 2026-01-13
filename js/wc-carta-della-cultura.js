/**
 * ilGhera Carta della Cultura for WC - js
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/js
 * @version 1.1.0
 */
var wccdcController = function() {

	var self = this;

	self.onLoad = function() {

        self.checkForCoupon();

    }

    /**
     * Aggiorna la pagina di checkout nel caso ion cui sia stato inserito in coupon
     *
     * @return void
     */
    self.checkForCoupon = function() {
    
        jQuery(document).ready(function($){
            
            $('body').on('checkout_error', function() {
                
                if ( wccdcOptions.couponConversion ) {

                    var data = {
                        'action': 'check-for-coupon'
                    }

                    $.post(wccdcOptions.ajaxURL, data, function(response) {
                        
                        if (response) {

                            $('body').trigger('update_checkout');
                        
                        }

                    })
                }

            })

        })
            
    }

    /**
     * Aggiorna il riepilogo ordine quando viene applicato un coupon Carta della Cultura
     */
    self.updateOnCouponApplied = function() {
        
        jQuery(document).ready(function($){
            
            // 1. Ascolta l'evento standard di WooCommerce per coupon applicati
            $(document.body).on('applied_coupon', function(e, coupon_code) {
                
                // Controlla se è un coupon Carta della Cultura (inizia con 'wccdc-')
                if (coupon_code && coupon_code.indexOf('wccdc-') === 0) {
                    
                    // Piccolo delay per permettere a WooCommerce di processare il coupon
                    setTimeout(function() {
                        // Forza l'aggiornamento del riepilogo ordine
                        $('body').trigger('update_checkout');
                    }, 300);
                }
            });
            
            // 2. Backup: aggiorna dopo il submit del form di pagamento
            $(document).on('click', '#place_order', function() {
                
                // Controlla se il metodo di pagamento è Carta della Cultura
                var paymentMethod = $('input[name="payment_method"]:checked').val();
                
                if (paymentMethod === 'carta-della-cultura') {
                    // Aspetta che il processo PHP sia completato
                    setTimeout(function() {
                        $('body').trigger('update_checkout');
                    }, 800);
                }
            });
                
        });
            
    }

}

/**
 * Class starter with onLoad method
 */
jQuery(document).ready(function($) {
	
	var Controller = new wccdcController;
	Controller.onLoad();

});

