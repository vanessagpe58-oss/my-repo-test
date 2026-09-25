<?php
/**
 * Checkout Form
 *
 * Sobrescritura del template oficial de WooCommerce para invertir la
 * distribucion en dos columnas: metodos de pago a la izquierda y el
 * selector visual de direcciones Harmony a la derecha.
 *
 * Se conserva la estructura oficial (mismos hooks, misma clase .checkout,
 * mismo formulario con action wc_get_checkout_url()), unicamente se
 * reordenan las llamadas a do_action() para colocar woocommerce_checkout_order_review
 * en col-1 y woocommerce_checkout_billing en col-2.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @see woocommerce/woocommerce/blob/trunk/plugins/woocommerce/templates/checkout/form-checkout.php
 *
 * @package Harmony_Saved_Addresses
 * @version 1.1.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// Si el registro esta deshabilitado y no hay sesion iniciada, no se puede comprar.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<?php if ( $checkout->get_checkout_fields() ) : ?>
		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
	<?php endif; ?>

	<div class="col2-set hsa-checkout-cols" id="customer_details">
		<div class="col-2 hsa-checkout-col hsa-checkout-col--address">
			<?php
			/*
			 * Columna izquierda: selector visual de Harmony Saved Addresses.
			 * La clase HSA_Checkout_Addresses esta enganchada a este hook en
			 * prioridad 5 y reemplaza el formulario nativo de facturacion/envio
			 * por el selector, ademas de pintar los campos WC off-screen para
			 * que WooCommerce valide, calcule envio/impuestos y guarde el pedido.
			 */
			do_action( 'woocommerce_checkout_billing' );
			?>
		</div>
		<div class="col-1 hsa-checkout-col hsa-checkout-col--payment">
			<?php
			/*
			 * Columna derecha: metodos de pago, resumen del pedido y boton
			 * "Realizar pedido". Esta accion renderiza todo el bloque #order_review
			 * (lineas, cupones, totales, pasarelas y submit) desde review-order.php.
			 */
			do_action( 'woocommerce_checkout_order_review' );
			?>
		</div>
	</div>

	<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
