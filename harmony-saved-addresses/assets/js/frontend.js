/**
 * Harmony Saved Addresses for WooCommerce - Frontend script.
 * Maneja la seleccion de direcciones, el modal de agregar/editar, las
 * peticiones AJAX y la sincronizacion con el checkout de WooCommerce
 * (checkout clasico via jQuery, y checkout por bloques via wp.data cuando
 * esta disponible).
 *
 * No depende de librerias externas ademas de jQuery (ya incluida por WP/WC).
 */
( function ( $ ) {
	'use strict';

	if ( typeof HSA_DATA === 'undefined' ) {
		return;
	}

	var state = {
		addresses: HSA_DATA.addresses || [],
		selectedShippingId: '',
		selectedBillingId: '',
		editingId: '',
		applying: false,
		useAddressBusy: false,
	};

	var $root = null;

	/**
	 * Punto de entrada.
	 */
	function init() {
		$root = $( '[data-hsa-root]' ).not( '.hsa-address-selector--blocks-source [data-hsa-root]' ).first();

		if ( ! $root.length ) {
			$root = $( '[data-hsa-root]' ).first();
		}

		if ( ! $root.length ) {
			return;
		}

		setDefaultSelection();
		bindEvents();
		populateStatesForForm( getDefaultCountry() );
		movePrivacyBelowPlaceOrder();

		// En checkout clasico, cuando WooCommerce actualiza fragmentos, nos
		// aseguramos de que los campos ocultos sigan sincronizados.
		// Pasamos `false` para no disparar `change` y evitar un loop infinito
		// con update_checkout. El setTimeout espera a que WC termine de
		// inyectar los nuevos inputs en el DOM antes de re-aplicar valores.
		$( document.body ).on( 'updated_checkout', function () {
			setTimeout( function () {
				movePrivacyBelowPlaceOrder();
				if ( ! document.getElementById( 'billing_first_name' ) ) {
					return;
				}
				applySelectionToClassicFields( false );
			}, 50 );
		} );
	}


	/**
	 * Coloca el aviso de privacidad debajo del boton "Realizar el pedido".
	 * WooCommerce reconstruye esta zona al actualizar el checkout, por eso
	 * tambien se ejecuta de nuevo en el evento updated_checkout.
	 */
	function movePrivacyBelowPlaceOrder() {
		var $privacy = $( '.woocommerce-privacy-policy-text' ).first();
		var $button  = $( '#place_order' ).first();

		if ( $privacy.length && $button.length ) {
			$privacy.insertAfter( $button );
		}
	}

	function getDefaultCountry() {
		return ( HSA_DATA && HSA_DATA.default_country ) || 'MX';
	}

	/**
	 * Marca como seleccionada la direccion predeterminada (o la primera) al cargar.
	 */
	function setDefaultSelection() {
		var def = null;
		state.addresses.forEach( function ( addr ) {
			if ( addr.is_default ) {
				def = addr;
			}
		} );
		if ( ! def && state.addresses.length ) {
			def = state.addresses[ 0 ];
		}
		if ( def ) {
			state.selectedShippingId = def.id;
			state.selectedBillingId  = def.id;
			markSelectedCard( def.id, 'shipping' );
		}
	}

	/**
	 * Engancha todos los eventos delegados dentro del selector.
	 */
	function bindEvents() {
		$( document )
			.on( 'click keydown', '[data-hsa-address-card]', onCardActivate )
			.on( 'click', '[data-hsa-action="edit"]', onActionClick )
			.on( 'click', '[data-hsa-action="add-new"]', onActionClick )
			.on( 'click', '[data-hsa-action="make-default"]', onActionClick )
			.on( 'click', '[data-hsa-action="delete"]', onActionClick )
			.on( 'click', '[data-hsa-action="close-modal"]', onActionClick )
			.on( 'click', '[data-hsa-action="save-address"]', onActionClick )
			.on( 'click', '[data-hsa-modal-overlay]', function ( e ) {
				if ( e.target === this ) {
					closeModal();
				}
			} )
			.on( 'change', '[data-hsa-field="country"]', onCountryChange )
			.on( 'change', '[data-hsa-same-billing]', onToggleSameBilling )
			.on( 'click', '[data-hsa-action="use-address"]', onActionClick );
	}

	/**
	 * Wrapper para todos los handlers de botones de accion dentro del popup
	 * (ahora convertidos a <a href="#">). Evita la navegacion por defecto
	 * del ancla y delega al handler especifico segun data-hsa-action.
	 */
	function onActionClick( e ) {
		e.preventDefault();
		var $el   = $( this );
		var act   = $el.data( 'hsa-action' );

		switch ( act ) {
			case 'edit':         onEditClick( e ); break;
			case 'add-new':      onAddNewClick( e ); break;
			case 'make-default': onMakeDefaultClick( e ); break;
			case 'delete':       onDeleteClick( e ); break;
			case 'close-modal':  closeModal( e ); break;
			case 'save-address': onSaveAddressClick( e ); break;
			case 'use-address':  onUseAddressClick( e ); break;
		}
	}

	/**
	 * Dispara el guardado del formulario del modal. Se usa click en lugar
	 * de submit nativo porque el contenedor ahora es un <div> (no se puede
	 * anidar un <form> dentro del <form class="checkout"> de WooCommerce).
	 */
	function onSaveAddressClick( e ) {
		e.preventDefault();
		var $form = $( e.currentTarget ).closest( '[data-hsa-address-form]' );
		onSubmitForm.call( $form.get( 0 ), e );
	}

	/* ---------------------------------------------------------------- */
	/* Seleccion de tarjetas                                             */
	/* ---------------------------------------------------------------- */

	function onCardActivate( e ) {
		if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) {
			return;
		}
		if ( $( e.target ).closest( '.hsa-address-card__actions' ).length ) {
			return; // No seleccionar al pulsar un boton interno.
		}
		e.preventDefault();

		var $card      = $( this );
		var addressId  = $card.data( 'address-id' );
		var isBilling  = $card.closest( '[data-hsa-billing-list]' ).length > 0;

		if ( isBilling ) {
			state.selectedBillingId = addressId;
			markSelectedCard( addressId, 'billing' );
		} else {
			state.selectedShippingId = addressId;
			markSelectedCard( addressId, 'shipping' );

			var sameBilling = $( '[data-hsa-same-billing]' ).is( ':checked' );
			if ( sameBilling || ! $( '[data-hsa-same-billing]' ).length ) {
				state.selectedBillingId = addressId;
			}
		}

		// El boton visible de 'Usar esta direccion' fue retirado.
		// Aplicamos la seleccion inmediatamente al checkout al elegir una tarjeta.
		if ( HSA_DATA.is_checkout ) {
			onUseAddressClick();
		}
	}

	function markSelectedCard( addressId, context ) {
		var $list = 'billing' === context
			? $( '[data-hsa-billing-list]' )
			: $( '[data-hsa-address-list]' ).not( '[data-hsa-billing-list]' );

		$list.find( '[data-hsa-address-card]' ).each( function () {
			var $c = $( this );
			$c.toggleClass( 'is-selected', $c.data( 'address-id' ) === addressId );
			$c.attr( 'aria-pressed', $c.data( 'address-id' ) === addressId ? 'true' : 'false' );
		} );
	}

	/* ---------------------------------------------------------------- */
	/* Modal: agregar / editar                                           */
	/* ---------------------------------------------------------------- */

	function onAddNewClick() {
		state.editingId = '';
		resetForm();
		$( '[data-hsa-modal-title]' ).text( HSA_DATA.i18n.addNew );
		openModal();
	}

	function onEditClick( e ) {
		e.stopPropagation();
		var addressId = $( e.currentTarget ).data( 'address-id' );
		var address   = findAddress( addressId );
		if ( ! address ) {
			return;
		}
		state.editingId = addressId;
		fillForm( address );
		$( '[data-hsa-modal-title]' ).text( HSA_DATA.i18n.modify );
		openModal();
	}

	function openModal() {
		$( '[data-hsa-modal-overlay]' ).prop( 'hidden', false );
		$( '[data-hsa-form-error]' ).prop( 'hidden', true ).text( '' );
		$( 'body' ).addClass( 'hsa-modal-open' );
	}

	function closeModal() {
		$( '[data-hsa-modal-overlay]' ).prop( 'hidden', true );
		$( 'body' ).removeClass( 'hsa-modal-open' );
	}

	function resetForm() {
		var $form = $( '[data-hsa-address-form]' );
		$form.find( '[data-hsa-field]' ).each( function () {
			var $f = $( this );
			if ( $f.attr( 'type' ) === 'checkbox' ) {
				$f.prop( 'checked', false );
			} else {
				$f.val( '' );
			}
		} );
		$form.find( '[data-hsa-field]' ).removeClass( 'hsa-field--invalid' );
		populateStatesForForm( getDefaultCountry() );
		movePrivacyBelowPlaceOrder();
	}

	function fillForm( address ) {
		var $form = $( '[data-hsa-address-form]' );
		Object.keys( address ).forEach( function ( key ) {
			var $field = $form.find( '[data-hsa-field="' + key + '"]' );
			if ( ! $field.length ) {
				return;
			}
			if ( $field.attr( 'type' ) === 'checkbox' ) {
				$field.prop( 'checked', !! address[ key ] );
			} else {
				$field.val( address[ key ] );
			}
		} );
		populateStatesForForm( address.country || getDefaultCountry(), address.state );
	}

	function onCountryChange() {
		populateStatesForForm( $( this ).val() );
	}

	/**
	 * Llena el <select> de estados segun el pais elegido, usando la lista
	 * de estados de WooCommerce previamente localizada en HSA_DATA.states.
	 */
	function populateStatesForForm( countryCode, selectedState ) {
		var $stateField = $( '[data-hsa-field="state"]' );
		if ( ! $stateField.length ) {
			return;
		}

		var states = ( HSA_DATA.states && HSA_DATA.states[ countryCode ] ) || null;
		$stateField.empty();

		if ( states && Object.keys( states ).length ) {
			Object.keys( states ).forEach( function ( code ) {
				var $opt = $( '<option></option>' ).attr( 'value', code ).text( states[ code ] );
				if ( code === selectedState ) {
					$opt.prop( 'selected', true );
				}
				$stateField.append( $opt );
			} );
		} else {
			var $opt = $( '<option></option>' ).attr( 'value', selectedState || '' ).text( selectedState || '' );
			$stateField.append( $opt );
		}
	}

	/* ---------------------------------------------------------------- */
	/* Guardar / eliminar / predeterminar (AJAX)                         */
	/* ---------------------------------------------------------------- */

	function onSubmitForm( e ) {
		e.preventDefault();

		var $form  = $( this );
		var $error = $( '[data-hsa-form-error]' );
		var payload = { action: 'hsa_save_address', nonce: HSA_DATA.nonce };

		$form.find( '[data-hsa-field]' ).each( function () {
			var $f   = $( this );
			var name = $f.data( 'hsa-field' );
			payload[ name ] = $f.attr( 'type' ) === 'checkbox' ? ( $f.is( ':checked' ) ? 1 : 0 ) : $f.val();
		} );

		if ( state.editingId ) {
			payload.id = state.editingId;
		}

		var missing = validateRequiredFields( $form );
		if ( missing.length ) {
			showFormError( HSA_DATA.i18n.requiredField );
			return;
		}

		$.post( HSA_DATA.ajax_url, payload )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					showFormError( ( response && response.data && response.data.message ) || HSA_DATA.i18n.genericError );
					return;
				}
				state.addresses = response.data.addresses;
				if ( response.data.address && response.data.address.is_default ) {
					state.selectedShippingId = response.data.address.id;
					state.selectedBillingId  = response.data.address.id;
				}
				closeModal();
				rerenderAddressList();
			} )
			.fail( function () {
				showFormError( HSA_DATA.i18n.genericError );
			} );
	}

	function validateRequiredFields( $form ) {
		var missing = [];
		$form.find( '[required]' ).each( function () {
			var $f = $( this );
			var invalid = ! String( $f.val() || '' ).trim().length;
			$f.toggleClass( 'hsa-field--invalid', invalid );
			if ( invalid ) {
				missing.push( $f.data( 'hsa-field' ) );
			}
		} );
		return missing;
	}

	function showFormError( message ) {
		$( '[data-hsa-form-error]' ).prop( 'hidden', false ).text( message );
	}

	function onMakeDefaultClick( e ) {
		e.stopPropagation();
		var addressId = $( e.currentTarget ).data( 'address-id' );

		$.post( HSA_DATA.ajax_url, {
			action: 'hsa_set_default_address',
			nonce: HSA_DATA.nonce,
			address_id: addressId,
		} ).done( function ( response ) {
			if ( response && response.success ) {
				state.addresses = response.data.addresses;
				state.selectedShippingId = addressId;
				state.selectedBillingId  = addressId;
				rerenderAddressList();
			}
		} );
	}

	function onDeleteClick( e ) {
		e.stopPropagation();
		var addressId = $( e.currentTarget ).data( 'address-id' );

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( HSA_DATA.i18n.deleteConfirm ) ) {
			return;
		}

		$.post( HSA_DATA.ajax_url, {
			action: 'hsa_delete_address',
			nonce: HSA_DATA.nonce,
			address_id: addressId,
		} ).done( function ( response ) {
			if ( response && response.success ) {
				state.addresses = response.data.addresses;
				if ( state.selectedShippingId === addressId ) {
					setDefaultSelection();
				}
				rerenderAddressList();
			} else if ( response && response.data && response.data.message ) {
				window.alert( response.data.message ); // eslint-disable-line no-alert
			}
		} );
	}

	function findAddress( id ) {
		var found = null;
		state.addresses.forEach( function ( a ) {
			if ( a.id === id ) {
				found = a;
			}
		} );
		return found;
	}

	/**
	 * Vuelve a pintar la lista de tarjetas usando los datos actuales de
	 * state.addresses, sin recargar la pagina. Reconstruye el HTML en el
	 * cliente reutilizando la misma estructura que genera address-card.php.
	 */
	function rerenderAddressList() {
		var $lists = $( '[data-hsa-address-list]' );

		$lists.each( function () {
			var $list      = $( this );
			var isBilling  = $list.is( '[data-hsa-billing-list]' );
			var $container = $list;

			$container.empty();

			if ( ! state.addresses.length ) {
				$container.append( '<p class="hsa-empty-state">' + ( HSA_DATA.i18n.genericError ? '' : '' ) + '</p>' );
			}

			state.addresses.forEach( function ( address ) {
				$container.append( buildCardMarkup( address ) );
			} );

			markSelectedCard( isBilling ? state.selectedBillingId : state.selectedShippingId, isBilling ? 'billing' : 'shipping' );
		} );

		applySelectionToClassicFields();
	}

	/**
	 * Construye el HTML de una tarjeta de direccion en el cliente (JS),
	 * en paralelo a la version PHP usada en el primer render del servidor.
	 */
	function buildCardMarkup( address ) {
		var isDefault = !! address.is_default;
		var fullName  = ( ( address.first_name || '' ) + ' ' + ( address.last_name || '' ) ).trim();
		var settings  = HSA_DATA.settings || {};

		var html = '<div class="hsa-address-card' + ( isDefault ? ' hsa-address-card--default' : '' ) + '" ' +
			'data-hsa-address-card data-address-id="' + escapeHtml( address.id ) + '" tabindex="0" role="button" aria-pressed="' + ( isDefault ? 'true' : 'false' ) + '">' +
			'<div class="hsa-address-card__radio" aria-hidden="true"><span class="hsa-address-card__radio-dot"></span></div>' +
			'<div class="hsa-address-card__content">';

		if ( settings.show_alias && address.alias ) {
			html += '<p class="hsa-address-card__alias">' + escapeHtml( address.alias );
			if ( isDefault ) {
				html += ' <span class="hsa-badge">' + escapeHtml( HSA_DATA.i18n.defaultBadge ) + '</span>';
			}
			html += '</p>';
		}

		html += '<p class="hsa-address-card__name">' + escapeHtml( fullName ) + '</p>';
		html += '<p class="hsa-address-card__line">' + escapeHtml( address.address_1 || '' ) + ( address.address_2 ? ' Int. ' + escapeHtml( address.address_2 ) : '' ) + '</p>';
		if ( address.neighborhood ) {
			html += '<p class="hsa-address-card__line">' + escapeHtml( address.neighborhood ) + '</p>';
		}
		html += '<p class="hsa-address-card__line">' + escapeHtml( ( address.city || '' ) + ( address.state ? ', ' + address.state : '' ) ) + '</p>';
		html += '<p class="hsa-address-card__line">' + escapeHtml( address.postcode || '' ) + '</p>';
		if ( address.phone ) {
			html += '<p class="hsa-address-card__line hsa-address-card__phone">' + escapeHtml( address.phone ) + '</p>';
		}

		html += '<p class="hsa-address-card__selected-flag" data-hsa-selected-flag>' + escapeHtml( HSA_DATA.i18n.selected ) + '</p>';

		html += '<div class="hsa-address-card__actions">' +
			'<button type="button" class="hsa-btn hsa-btn--text" data-hsa-action="edit" data-address-id="' + escapeHtml( address.id ) + '">' + escapeHtml( HSA_DATA.i18n.modify ) + '</button>';

		if ( ! isDefault ) {
			html += '<button type="button" class="hsa-btn hsa-btn--text" data-hsa-action="make-default" data-address-id="' + escapeHtml( address.id ) + '">' + escapeHtml( HSA_DATA.i18n.makeDefault ) + '</button>';
		}
		if ( settings.allow_delete_addresses ) {
			html += '<button type="button" class="hsa-btn hsa-btn--text hsa-btn--danger" data-hsa-action="delete" data-address-id="' + escapeHtml( address.id ) + '">' + escapeHtml( HSA_DATA.i18n.delete ) + '</button>';
		}

		html += '</div></div></div>';

		return html;
	}

	function escapeHtml( str ) {
		return String( str || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/* ---------------------------------------------------------------- */
	/* Facturacion independiente                                         */
	/* ---------------------------------------------------------------- */

	function onToggleSameBilling() {
		var same = $( this ).is( ':checked' );
		$( '[data-hsa-billing-selector]' ).prop( 'hidden', same );

		if ( same ) {
			state.selectedBillingId = state.selectedShippingId;
		} else if ( ! $( '[data-hsa-billing-list]' ).children().length ) {
			state.addresses.forEach( function ( address ) {
				$( '[data-hsa-billing-list]' ).append( buildCardMarkup( address ) );
			} );
			markSelectedCard( state.selectedBillingId, 'billing' );
		}
	}

	/* ---------------------------------------------------------------- */
	/* Aplicar seleccion al checkout                                     */
	/* ---------------------------------------------------------------- */

	function onUseAddressClick() {
		if ( state.useAddressBusy ) {
			return;
		}

		var $btn    = $( '[data-hsa-action="use-address"]' ).first();
		var $status = $( '[data-hsa-status]' );
		$status.removeClass( 'is-error is-success' ).text( '' );

		if ( ! state.selectedShippingId ) {
			$status.addClass( 'is-error' ).text( HSA_DATA.i18n.genericError );
			return;
		}

		state.useAddressBusy = true;
		$btn.prop( 'disabled', true ).addClass( 'is-loading' );
		$status.addClass( 'is-loading' ).text( HSA_DATA.i18n.applyingChange );

		applySelectionToClassicFields( true );
		applySelectionToBlocksStore();

		var requests = [];

		requests.push(
			$.post( HSA_DATA.ajax_url, {
				action: 'hsa_select_address',
				nonce: HSA_DATA.nonce,
				address_id: state.selectedShippingId,
				context: 'shipping',
			} )
		);

		if ( state.selectedBillingId && state.selectedBillingId !== state.selectedShippingId ) {
			requests.push(
				$.post( HSA_DATA.ajax_url, {
					action: 'hsa_select_address',
					nonce: HSA_DATA.nonce,
					address_id: state.selectedBillingId,
					context: 'billing',
				} )
			);
		}

		$.when.apply( $, requests ).always( function () {
			if ( $( 'form.checkout' ).length ) {
				$( document.body ).trigger( 'update_checkout' );
			}

			state.useAddressBusy = false;
			$btn.prop( 'disabled', false ).removeClass( 'is-loading' );
			$status.removeClass( 'is-loading' ).addClass( 'is-success' ).text( HSA_DATA.i18n.selected );
		} );
	}

	/**
	 * Rellena los campos ocultos/inputs reales del checkout clasico de
	 * WooCommerce (billing_* / shipping_*) con los datos de la direccion
	 * seleccionada. NO dispara 'change' para evitar el loop infinito con
	 * update_checkout: el caller (onUseAddressClick) ya dispara
	 * 'update_checkout' manualmente cuando corresponde.
	 */
	function applySelectionToClassicFields( fireChange ) {
		var $checkoutForm = $( 'form.checkout, form#order_review' );
		if ( ! $checkoutForm.length ) {
			return;
		}

		if ( state.applying ) {
			return;
		}
		state.applying = true;

		var shippingAddress = findAddress( state.selectedShippingId );
		var billingAddress  = findAddress( state.selectedBillingId ) || shippingAddress;

		if ( shippingAddress ) {
			fillWcFields( 'shipping', shippingAddress, fireChange );
		}
		if ( billingAddress ) {
			fillWcFields( 'billing', billingAddress, fireChange );
		}

		$( '[data-hsa-selected-shipping-id]' ).val( state.selectedShippingId );
		$( '[data-hsa-selected-billing-id]' ).val( state.selectedBillingId );

		setTimeout( function () { state.applying = false; }, 0 );
	}

	function fillWcFields( prefix, address, fireChange ) {
		var map = {
			first_name: 'first_name',
			last_name: 'last_name',
			company: 'company',
			country: 'country',
			state: 'state',
			city: 'city',
			postcode: 'postcode',
			address_1: 'address_1',
			address_2: 'neighborhood',
		};

		Object.keys( map ).forEach( function ( wcSuffix ) {
			var value = address[ map[ wcSuffix ] ];
			if ( wcSuffix === 'address_2' ) {
				value = ( ( address.neighborhood || '' ) + ' ' + ( address.address_2 || '' ) ).trim();
			}
			var $field = $( '#' + prefix + '_' + wcSuffix );
			if ( $field.length && typeof value !== 'undefined' ) {
				if ( fireChange ) {
					$field.val( value ).trigger( 'change' );
				} else {
					$field.val( value );
				}
			}
		} );

		if ( 'billing' === prefix ) {
			if ( $( '#billing_phone' ).length ) {
				var $phone = $( '#billing_phone' );
				if ( fireChange ) {
					$phone.val( address.phone || '' ).trigger( 'change' );
				} else {
					$phone.val( address.phone || '' );
				}
			}
			if ( $( '#billing_email' ).length && address.email ) {
				var $email = $( '#billing_email' );
				if ( fireChange ) {
					$email.val( address.email ).trigger( 'change' );
				} else {
					$email.val( address.email );
				}
			}
		}
	}

	/**
	 * Compatibilidad best-effort con WooCommerce Checkout Blocks: si el
	 * store de datos de los bloques esta disponible en la pagina (wp.data
	 * con el store "wc/store/cart"), sincroniza la direccion seleccionada
	 * usando sus acciones publicas.
	 */
	function applySelectionToBlocksStore() {
		if ( typeof window.wp === 'undefined' || ! window.wp.data || typeof window.wp.data.dispatch !== 'function' ) {
			return;
		}

		var cartStore = window.wp.data.dispatch( 'wc/store/cart' );
		if ( ! cartStore ) {
			return;
		}

		var shippingAddress = findAddress( state.selectedShippingId );
		var billingAddress  = findAddress( state.selectedBillingId ) || shippingAddress;

		try {
			if ( shippingAddress && typeof cartStore.setShippingAddress === 'function' ) {
				cartStore.setShippingAddress( mapToBlocksAddress( shippingAddress, true ) );
			}
			if ( billingAddress && typeof cartStore.setBillingAddress === 'function' ) {
				cartStore.setBillingAddress( mapToBlocksAddress( billingAddress, false ) );
			}
		} catch ( err ) {
			// Si el store cambia de forma en una version futura de WooCommerce
			// Blocks, fallamos de forma silenciosa: el checkout clasico sigue
			// funcionando de manera independiente.
			if ( window.console && window.console.warn ) {
				window.console.warn( 'Harmony Saved Addresses: no fue posible sincronizar con el checkout por bloques.', err );
			}
		}
	}

	function mapToBlocksAddress( address, isShipping ) {
		var mapped = {
			first_name: address.first_name || '',
			last_name: address.last_name || '',
			company: address.company || '',
			address_1: address.address_1 || '',
			address_2: ( ( address.neighborhood || '' ) + ' ' + ( address.address_2 || '' ) ).trim(),
			city: address.city || '',
			state: address.state || '',
			postcode: address.postcode || '',
			country: address.country || '',
			phone: address.phone || '',
		};
		if ( ! isShipping ) {
			mapped.email = address.email || '';
		}
		return mapped;
	}

	$( init );
} )( jQuery );
