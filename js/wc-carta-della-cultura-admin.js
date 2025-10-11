/**
 * ilGhera Carta Docente for WC - Admin js
 *
 * @author ilGhera
 * @package wc-carta-della-cultura/js
 *
 * @since 1.4.0
 */

/**
 * Ajax - Elimina il certificato caricato precedentemente
 */
var wccdc_delete_certificate = function() {
	jQuery(function($){
		$('.wccdc-delete-certificate').on('click', function(){
			var sure = confirm('Sei sicuro di voler eliminare il certificato?');
			if(sure) {
				var cert = $('.cert-loaded').text();
				var data = {
					'action': 'wccdc-delete-certificate',
					'wccdc-delete': true,
                    'delete-nonce': wccdcData.delCertNonce,
					'cert': cert
				}			
				$.post(ajaxurl, data, function(response){
					location.reload();
				})
			}
		})	
	})
}
wccdc_delete_certificate();


/**
 * Aggiunge un nuovo abbinamento bene/ categoria per il controllo in pagina di checkout
 */
var wccdc_add_cat = function() {
	jQuery(function($){
		$('.add-cat-hover.wccdc').on('click', function(){
			var number = $('.setup-cat').length + 1;

			/*Beni già impostati da escludere*/
			var beni_values = [];
			$('.wccdc-field.beni').each(function(){
				beni_values.push($(this).val());
			})

			var data = {
				'action': 'wccdc-add-cat',
				'number': number,
				'exclude-beni': beni_values.toString(),
                'add-cat-nonce': wccdcData.addCatNonce,
			}
			$.post(ajaxurl, data, function(response){
				$(response).appendTo('.categories-container');
				$('.wccdc-tot-cats').val(number);
			})				
		})
	})
}
wccdc_add_cat();


/**
 * Rimuove un abbinamento bene/ categoria
 */
var wccdc_remove_cat = function() {
	jQuery(function($){
		$(document).on('click', '.remove-cat-hover', function(response){
			var cat = $(this).closest('li');
			$(cat).remove();
			var number = $('.setup-cat').length;
			$('.wccdc-tot-cats').val(number);
		})
	})
}
wccdc_remove_cat();


/**
 * Funzionalità Sandbox
 */
var wccdc_sandbox = function() {
	jQuery(function($){

        var data, sandbox;
        var nonce = $('#wccdc-sandbox-nonce').attr('value');
        
        $(document).ready(function() {

            if ( 'wccdc-certificate' == $('.nav-tab.nav-tab-active').data('link') ) {

                if ( $('.wccdc-sandbox-field .tzCheckBox').hasClass( 'checked' ) ) {
                    $('#wccdc-certificate').hide();
                    $('#wccdc-sandbox-option').show();

                } else {
                    $('#wccdc-certificate').show();
                    $('#wccdc-sandbox-option').show();
                }

            }

        })

        $(document).on( 'click', '.wccdc-sandbox-field .tzCheckBox', function() {

            if ( $(this).hasClass( 'checked' ) ) {
                $('#wccdc-certificate').hide();
                sandbox = 1;
            } else {
                $('#wccdc-certificate').show('slow');
                sandbox = 0;
            }

            data = {
                'action': 'wccdc-sandbox',
                'sandbox': sandbox,
                'nonce': nonce
            }

            $.post(ajaxurl, data);

        })

    })
}
wccdc_sandbox();


/**
 * Menu di navigazione della pagina opzioni
 */
var wccdc_menu_navigation = function() {
	jQuery(function($){
		var contents = $('.wccdc-admin');
		var url = window.location.href.split("#")[0];
		var hash = window.location.href.split("#")[1];

		if(hash) {
	        contents.hide();		    
            
            if( 'wccdc-certificate' == hash ) {
                wccdc_sandbox();
            } else {
                $('#' + hash).fadeIn(200);		
            }

	        $('h2#wccdc-admin-menu a.nav-tab-active').removeClass("nav-tab-active");
	        $('h2#wccdc-admin-menu a').each(function(){
	        	if($(this).data('link') == hash) {
	        		$(this).addClass('nav-tab-active');
	        	}
	        })
	        
	        $('html, body').animate({
	        	scrollTop: 0
	        }, 'slow');
		}

		$("h2#wccdc-admin-menu a").click(function () {
	        var $this = $(this);
	        
	        contents.hide();
	        $("#" + $this.data("link")).fadeIn(200);

            if( 'wccdc-certificate' == $this.data("link") ) {
                $('#wccdc-sandbox-option').fadeIn(200);
            
                wccdc_sandbox();
            
            }
	        
            $('h2#wccdc-admin-menu a.nav-tab-active').removeClass("nav-tab-active");
	        $this.addClass('nav-tab-active');

	        window.location = url + '#' + $this.data('link');

	        $('html, body').scrollTop(0);

	    })

	})
}
wccdc_menu_navigation();

/**
 * Mostra i dettagli della mail all'utente
 * nel caso la funzione ordini in sospeso sia stata attivata
 *
 * @return void
 */
var wccdc_email_details = function() {
    jQuery(function($){
        $(document).ready(function() {

            var on_hold       = $('.wccdc-orders-on-hold');
            var email_details = $('.wccdc-email-details');

            if ( $('.tzCheckBox', on_hold).hasClass( 'checked' ) ) {
                $(email_details).show();
            }

            $('.tzCheckBox', on_hold).on( 'click', function() {

                if ( $(this).hasClass( 'checked' ) ) {
                    $(email_details).show('slow');
                } else {
                    $(email_details).hide();
                }

            })
            
        })
    })
}
wccdc_email_details();

/**
 * Attivazione opzione coupon con esclusione spese di spedizione
 *
 * @return void
 */
var wccdc_exclude_shipping = function() {

    jQuery(function($){
        $(document).ready(function() {

            var excludeShipping = $('.wccdc-exclude-shipping');
            var coupon          = $('.wccdc-coupon');

            $('.tzCheckBox', excludeShipping).on( 'click', function() {

                if ( $(this).hasClass( 'checked' ) && ! $('.tzCheckBox', coupon).hasClass( 'checked' ) ) {
                    $('.tzCheckBox', coupon).trigger('click');
                }

            })

            // Non disattivare opzione coupon con esclusione spese di spedizione attive
            $('.tzCheckBox', coupon).on( 'click', function() {

                if ( ! $(this).hasClass( 'checked' ) && $('.tzCheckBox', excludeShipping).hasClass( 'checked' ) ) {
                    alert( 'L\'esclusione delle spese di spedizione prevedere l\'utilizzo di questa funzionalità.' );
                    $('.tzCheckBox', coupon).trigger('click');
                }

            })
        })
    })

}
wccdc_exclude_shipping();
