<?php
/**
 * Pantalla personalizada de confirmación de pedido.
 *
 * @var WC_Order|false $order
 * @package Harmony_Saved_Addresses
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) :
	?>
	<div class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">
		<?php echo esc_html( apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Gracias. Tu pedido ha sido recibido.', 'woocommerce' ), null ) ); ?>
	</div>
	<?php
	return;
endif;

if ( $order->has_status( 'failed' ) ) :
	?>
	<div class="woocommerce-order hsa-order-failed">
		<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed">
			<?php esc_html_e( 'No pudimos procesar el pago de tu pedido. Inténtalo nuevamente o elige otro método de pago.', 'harmony-saved-addresses' ); ?>
		</p>
		<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
			<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Intentar de nuevo', 'woocommerce' ); ?></a>
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'Mi cuenta', 'woocommerce' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
	return;
endif;

$items      = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$shop_url   = wc_get_page_permalink( 'shop' );
$currency   = $order->get_currency();

// Nombre para el saludo: se prioriza el nombre de facturación y, si no existe,
// el de envío. Si ninguno está disponible, se muestra un saludo genérico.
$first_name = $order->get_billing_first_name();
if ( '' === trim( (string) $first_name ) ) {
	$first_name = $order->get_shipping_first_name();
}
$hsa_title = '' !== trim( (string) $first_name )
	? sprintf(
		/* translators: %s: customer first name */
		__( '¡Gracias, %s!', 'harmony-saved-addresses' ),
		$first_name
	)
	: __( '¡Gracias!', 'harmony-saved-addresses' );

// Cálculo del subtotal (suma del subtotal de línea de cada producto, antes
// de descuentos e impuestos), usando exclusivamente la API nativa de WooCommerce.
$hsa_subtotal = 0;
foreach ( $items as $hsa_item_for_subtotal ) {
	$hsa_subtotal += (float) $hsa_item_for_subtotal->get_subtotal();
}

$hsa_shipping_total = (float) $order->get_shipping_total();
$hsa_discount_total  = (float) $order->get_discount_total();
$hsa_has_discount    = $hsa_discount_total > 0;
$hsa_is_paid         = $order->is_paid();
$hsa_payment_title   = $order->get_payment_method_title();

// Intenta obtener de forma segura los últimos cuatro dígitos de la tarjeta.
// Primero consulta el token de pago de WooCommerce y después metadatos comunes
// utilizados por Stripe, WooPayments, Mercado Pago y otras pasarelas.
$hsa_card_last4 = '';

if ( function_exists( 'wc_get_payment_token_by_id' ) && method_exists( $order, 'get_payment_tokens' ) ) {
	$hsa_payment_tokens = $order->get_payment_tokens();
	if ( ! empty( $hsa_payment_tokens ) ) {
		$hsa_token = wc_get_payment_token_by_id( (int) reset( $hsa_payment_tokens ) );
		if ( $hsa_token && is_a( $hsa_token, 'WC_Payment_Token_CC' ) ) {
			$hsa_card_last4 = (string) $hsa_token->get_last4();
		}
	}
}

if ( '' === $hsa_card_last4 ) {
	$hsa_last4_meta_keys = array(
		'_card_last4',
		'card_last4',
		'_card_last_four',
		'card_last_four',
		'_stripe_card_last4',
		'_wcpay_card_last4',
		'_mercadopago_card_last_four',
		'_mp_card_last_four_digits',
		'last_four_digits',
		'last4',
	);

	foreach ( $hsa_last4_meta_keys as $hsa_meta_key ) {
		$hsa_meta_value = $order->get_meta( $hsa_meta_key, true );
		if ( is_scalar( $hsa_meta_value ) && preg_match( '/(?:^|\\D)(\\d{4})(?:\\D|$)/', (string) $hsa_meta_value, $hsa_last4_match ) ) {
			$hsa_card_last4 = $hsa_last4_match[1];
			break;
		}
	}
}

$hsa_payment_detail = $hsa_payment_title;
if ( '' !== $hsa_card_last4 ) {
	$hsa_payment_detail = sprintf(
		/* translators: 1: payment method, 2: last four card digits */
		__( '%1$s terminada en %2$s', 'harmony-saved-addresses' ),
		$hsa_payment_title ? $hsa_payment_title : __( 'Tarjeta', 'harmony-saved-addresses' ),
		$hsa_card_last4
	);
}
?>

<div class="woocommerce-order hsa-thankyou" data-hsa-thankyou>

	<header class="hsa-ty-header">
		<div class="hsa-ty-header__icon" aria-hidden="true">
			<svg viewBox="0 0 64 64" role="img" focusable="false">
				<circle cx="32" cy="32" r="32"></circle>
				<path d="M20 33.5 28 41 44 24" class="hsa-ty-check"></path>
			</svg>
		</div>
		<h1 class="hsa-ty-header__title" id="hsa-thankyou-title"><?php echo esc_html( $hsa_title ); ?></h1>
		<p class="hsa-ty-header__subtitle">
			<?php esc_html_e( 'Tu pedido ha sido realizado con éxito.', 'harmony-saved-addresses' ); ?>
			<br>
			<?php esc_html_e( 'Te avisaremos por correo cuando tu pedido esté en camino.', 'harmony-saved-addresses' ); ?>
		</p>
	</header>

	<div class="hsa-ty-container">

		<div class="hsa-ty-infocard" role="status">
			<div class="hsa-ty-infocard__item">
				<span class="hsa-ty-infocard__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"></rect><path d="M9 3v3M15 3v3M8.5 10h7M8.5 14h7M8.5 18h4.5"></path></svg>
				</span>
				<div class="hsa-ty-infocard__text">
					<span><?php esc_html_e( 'Número de pedido', 'harmony-saved-addresses' ); ?></span>
					<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
				</div>
			</div>
			<div class="hsa-ty-infocard__item">
				<span class="hsa-ty-infocard__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="16" rx="2.5"></rect><path d="M3.5 9.5h17M8 3v3.5M16 3v3.5"></path></svg>
				</span>
				<div class="hsa-ty-infocard__text">
					<span><?php esc_html_e( 'Fecha de compra', 'harmony-saved-addresses' ); ?></span>
					<strong><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'j \d\e F \d\e Y' ) ); ?></strong>
				</div>
			</div>
			<div class="hsa-ty-infocard__item">
				<span class="hsa-ty-infocard__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"></rect><path d="m4 7 8 6 8-6"></path></svg>
				</span>
				<div class="hsa-ty-infocard__text">
					<span><?php esc_html_e( 'Correo del comprador', 'harmony-saved-addresses' ); ?></span>
					<strong class="hsa-ty-infocard__email"><?php echo esc_html( $order->get_billing_email() ); ?></strong>
				</div>
			</div>
		</div>

		<div class="hsa-ty-grid">

			<div class="hsa-ty-main">
				<div class="hsa-ty-products">
					<h2 class="hsa-ty-section-title"><?php esc_html_e( 'Productos de tu pedido', 'harmony-saved-addresses' ); ?></h2>
					<?php foreach ( $items as $hsa_item_id => $hsa_item ) :
						if ( ! apply_filters( 'woocommerce_order_item_visible', true, $hsa_item ) ) {
							continue;
						}
						$hsa_product = $hsa_item->get_product();
						$hsa_image   = $hsa_product ? $hsa_product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) : wc_placeholder_img( 'woocommerce_thumbnail' );
						?>
						<div class="hsa-ty-product">
							<div class="hsa-ty-product__image"><?php echo wp_kses_post( $hsa_image ); ?></div>
							<div class="hsa-ty-product__name">
								<?php echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $hsa_item->get_name(), $hsa_item, false ) ); ?>
							</div>
							<div class="hsa-ty-product__qty" aria-label="<?php echo esc_attr( sprintf( __( 'Cantidad: %d', 'harmony-saved-addresses' ), $hsa_item->get_quantity() ) ); ?>">
								x<?php echo esc_html( $hsa_item->get_quantity() ); ?>
							</div>
						</div>
						<?php do_action( 'woocommerce_order_item_meta_start', $hsa_item_id, $hsa_item, $order, false ); ?>
						<?php do_action( 'woocommerce_order_item_meta_end', $hsa_item_id, $hsa_item, $order, false ); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<aside class="hsa-ty-side">
				<div class="hsa-ty-summary-card">
					<h2><?php esc_html_e( 'Resumen de tu compra', 'harmony-saved-addresses' ); ?></h2>

					<div class="hsa-ty-summary-row">
						<span><?php esc_html_e( 'Subtotal', 'harmony-saved-addresses' ); ?></span>
						<span><?php echo wp_kses_post( wc_price( $hsa_subtotal, array( 'currency' => $currency ) ) ); ?></span>
					</div>

					<div class="hsa-ty-summary-row">
						<span><?php esc_html_e( 'Costo de envío', 'harmony-saved-addresses' ); ?></span>
						<span>
							<?php
							if ( $hsa_shipping_total > 0 ) {
								echo wp_kses_post( wc_price( $hsa_shipping_total, array( 'currency' => $currency ) ) );
							} else {
								esc_html_e( 'Gratis', 'harmony-saved-addresses' );
							}
							?>
						</span>
					</div>

					<?php if ( $hsa_has_discount ) : ?>
						<div class="hsa-ty-summary-row hsa-ty-summary-row--discount">
							<span><?php esc_html_e( 'Descuento', 'harmony-saved-addresses' ); ?></span>
							<span>&minus;<?php echo wp_kses_post( wc_price( $hsa_discount_total, array( 'currency' => $currency ) ) ); ?></span>
						</div>
					<?php endif; ?>

					<div class="hsa-ty-summary-total">
						<span><?php esc_html_e( 'Total', 'harmony-saved-addresses' ); ?></span>
						<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
					</div>
				</div>

				<div class="hsa-ty-payment-card">
					<p class="hsa-ty-payment-status<?php echo $hsa_is_paid ? ' hsa-ty-payment-status--approved' : ''; ?>">
						<span class="hsa-ty-payment-status__icon" aria-hidden="true">
							<?php if ( $hsa_is_paid ) : ?>
								<svg viewBox="0 0 24 24"><path d="M4.5 12.5 9.5 17.5 19.5 6.5"></path></svg>
							<?php else : ?>
								<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5.5l3.5 3.5"></path></svg>
							<?php endif; ?>
						</span>
						<?php echo $hsa_is_paid ? esc_html__( 'Pago aprobado', 'harmony-saved-addresses' ) : esc_html__( 'Pago pendiente', 'harmony-saved-addresses' ); ?>
					</p>
					<?php if ( $hsa_payment_detail ) : ?>
						<p class="hsa-ty-payment-method"><?php echo esc_html( $hsa_payment_detail ); ?></p>
					<?php endif; ?>
				</div>
			</aside>

		</div>

		<?php
		$hsa_bacs_html = '';
		if ( 'bacs' === $order->get_payment_method() ) {
			ob_start();
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
			$hsa_bacs_html = (string) ob_get_clean();
		}

		ob_start();
		do_action( 'woocommerce_thankyou', $order->get_id() );
		$hsa_thankyou_html = (string) ob_get_clean();

		echo $hsa_bacs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<a class="hsa-ty-cta" href="<?php echo esc_url( $shop_url ); ?>">
			<span class="hsa-ty-cta__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24"><path d="M6 8h12l-1 12.5a1.5 1.5 0 0 1-1.5 1.5h-7a1.5 1.5 0 0 1-1.5-1.5z"></path><path d="M9 8V6a3 3 0 0 1 6 0v2"></path></svg>
			</span>
			<?php esc_html_e( 'Seguir comprando', 'harmony-saved-addresses' ); ?>
		</a>

		<p class="hsa-ty-footer">&#9825; <?php esc_html_e( 'Gracias por ser parte de Harmony.', 'harmony-saved-addresses' ); ?></p>
	</div>

	<?php echo $hsa_thankyou_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
