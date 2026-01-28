/**
 * ilGhera Carta della Cultura for WC - Admin js
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

			/*Categorie già selezionate da escludere*/
			var selected_categories = [];
			$('.wccdc-field.categories').each(function(){
				var cat_id = $(this).val();
				if (cat_id) {
					selected_categories.push(cat_id);
				}
			})

			var data = {
				'action': 'wccdc-add-cat',
				'number': number,
				'exclude-categories': selected_categories,
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

/**
 * Gestione campo ISBN nella pagina opzioni
 *
 * @return void
 */
var wccdc_isbn_field = function() {
    jQuery(function($){
        $(document).ready(function() {
            
            // Mostra/nascondi sezione personalizzata in base alla fonte principale
            $('input[name="wccdc-isbn-primary-source"]').on('change', function() {
                var selected = $(this).val();
                if (selected === 'custom') {
                    $('#wccdc-custom-isbn-section').show();
                } else {
                    $('#wccdc-custom-isbn-section').hide();
                }
            });
            
            // Mostra/nascondi al caricamento della pagina
            $(document).ready(function() {
                var selected = $('input[name="wccdc-isbn-primary-source"]:checked').val();
                if (selected === 'custom') {
                    $('#wccdc-custom-isbn-section').show();
                }
            });
            
            // Mostra/nascondi campo manuale quando cambia la selezione
            $('select.wccdc-isbn-field').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#wccdc-custom-isbn-field').show();
                } else {
                    $('#wccdc-custom-isbn-field').hide();
                }
            });
            
            // Aggiorna il dropdown quando cambia la fonte ISBN (meta/attribute)
            $('input[name="wccdc-isbn-source"]').on('change', function() {
                var source = $(this).val();

                // Se è in modalità preview (versione free), aggiorna la select localmente
                if ($(this).hasClass('wccdc-preview-only')) {
                    var $select = $('select.wccdc-isbn-field');
                    var candidates = wccdcData.isbnCandidates || {};
                    var i18n = wccdcData.i18n || {};

                    // Ricostruisci la select
                    $select.empty();
                    $select.append('<option value="none">' + (i18n.noneOption || 'Nessuno') + '</option>');

                    if (source === 'meta' && candidates.meta && candidates.meta.length > 0) {
                        var $optgroup = $('<optgroup label="' + (i18n.metaGroup || 'Campi personalizzati') + '"></optgroup>');
                        candidates.meta.forEach(function(metaKey) {
                            $optgroup.append('<option value="meta:' + metaKey + '">' + metaKey + '</option>');
                        });
                        $select.append($optgroup);
                    } else if (source === 'attribute' && candidates.attribute) {
                        var $optgroup = $('<optgroup label="' + (i18n.attributeGroup || 'Attributi di prodotto') + '"></optgroup>');
                        for (var taxonomy in candidates.attribute) {
                            var data = candidates.attribute[taxonomy];
                            var label = data.label || taxonomy;
                            $optgroup.append('<option value="attribute:' + taxonomy + '">' + label + '</option>');
                        }
                        $select.append($optgroup);
                    }

                    $select.append('<option value="custom">' + (i18n.customOption || 'Inserisci manualmente') + '</option>');
                    return;
                }
                // Ricarica la pagina per aggiornare il dropdown con i campi corretti
                // Invia il form per salvare la selezione
                var $form = $('form[name="wccdc-options"]');
                if ($form.length === 0) {
                    $form = $('form.wccdc-options');
                }
                // Assicurati che il nonce sia presente
                if ($form.find('input[name="wccdc-settings-nonce"]').length === 0) {
                    $form.append('<input type="hidden" name="wccdc-settings-nonce" value="' + (wccdcData.settingsNonce || '') + '">');
                }
                // Aggiungi un campo nascosto per indicare che stiamo cambiando solo la fonte
                $form.append('<input type="hidden" name="wccdc-isbn-source-temp" value="' + source + '">');
                // Aggiungi un campo per forzare il refresh senza cache
                $form.append('<input type="hidden" name="wccdc-force-refresh" value="1">');
                // Invia il form
                $form.submit();
            });
            
            // Riesamina campi ISBN (disabilitato in versione free)
            $('#wccdc-rescan-isbn').on('click', function(e) {
                e.preventDefault();

                // Se il pulsante è disabilitato (classe wccdc-disabled), mostra messaggio premium
                if ($(this).hasClass('wccdc-disabled')) {
                    var $message = $('#wccdc-rescan-message');
                    $message.html('<span style="color:#d63638;">' +
                        (wccdcData.premiumMessage || 'Passa a Premium per utilizzare i campi personalizzati.') +
                        '</span>');
                    // Nascondi il messaggio dopo 3 secondi
                    setTimeout(function() {
                        $message.fadeOut(300, function() {
                            $message.html('').show();
                        });
                    }, 3000);
                    return;
                }
                
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $message = $('#wccdc-rescan-message');
                
                // Verifica che wccdcData sia disponibile
                if (typeof wccdcData === 'undefined' || !wccdcData.rescanIsbnNonce) {
                    $message.html('<span style="color:#dc3232;">Errore: configurazione non disponibile. Ricarica la pagina.</span>');
                    return;
                }
                
                $spinner.addClass('is-active');
                $message.text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wccdc_rescan_isbn',
                        nonce: wccdcData.rescanIsbnNonce
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        if (response.success) {
                            $message.html('<span style="color:#46b450;">' + response.data.message + '</span>');
                            // Ricarica la pagina dopo 1.5 secondi
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $message.html('<span style="color:#dc3232;">' + response.data.message + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $spinner.removeClass('is-active');
                        $message.html('<span style="color:#dc3232;">Errore durante la scansione: ' + error + '</span>');
                        console.error('AJAX Error:', status, error);
                    }
                });
            });
            
            // Rimuovi campo manuale (link)
            $('#wccdc-remove-manual-link').on('click', function(e) {
                e.preventDefault();
                
                var $link = $(this);
                var $spinner = $link.next('.spinner');
                var $message = $('#wccdc-remove-message');
                
                // Verifica che wccdcData sia disponibile
                if (typeof wccdcData === 'undefined' || !wccdcData.removeManualFieldNonce) {
                    $message.html('<span style="color:#dc3232;">Errore: configurazione non disponibile. Ricarica la pagina.</span>');
                    return;
                }
                
                $spinner.addClass('is-active');
                $message.text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wccdc_remove_manual_field',
                        nonce: wccdcData.removeManualFieldNonce
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        if (response.success) {
                            $message.html('<span style="color:#46b450;">' + response.data.message + '</span>');
                            // Ricarica la pagina dopo 1.5 secondi
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $message.html('<span style="color:#dc3232;">' + response.data.message + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $spinner.removeClass('is-active');
                        $message.html('<span style="color:#dc3232;">Errore durante la rimozione: ' + error + '</span>');
                        console.error('AJAX Error:', status, error);
                    }
                });
            });
            
        });
    });
}
wccdc_isbn_field();
