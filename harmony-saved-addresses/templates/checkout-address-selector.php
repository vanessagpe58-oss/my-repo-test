<?php
/**
 * Template: selector visual de direcciones en el checkout.
 * Puede ser sobrescrito copiandolo a: yourtheme/harmony-saved-addresses/checkout-address-selector.php
 *
 * Variables disponibles:
 * @var array $addresses    Direcciones guardadas del usuario.
 * @var bool  $is_logged_in Si el usuario tiene sesion iniciada.
 * @var array $settings     Ajustes visuales del plugin.
 * @var bool  $is_blocks    Si se renderiza para Checkout Blocks (oculto por defecto).
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_blocks = $is_blocks ?? false;
$wrapper_class = 'hsa-address-selector' . ( $is_blocks ? ' hsa-address-selector--blocks-source' : '' );
?>
<div id="hsa-address-selector-root" class="<?php echo esc_attr( $wrapper_class ); ?>" data-hsa-root="1">

	<?php if ( $is_logged_in && ! empty( $addresses ) ) : ?>

		<div class="hsa-panel hsa-panel--select" data-hsa-panel="select">
			<h3 class="hsa-panel__title"><?php esc_html_e( 'Elige la direccion de entrega', 'harmony-saved-addresses' ); ?></h3>

			<div class="hsa-address-list" data-hsa-address-list>
				<?php foreach ( $addresses as $address ) : ?>
					<?php
					wc_get_template(
						'address-card.php',
						array(
							'address'  => $address,
							'settings' => $settings,
						),
						'harmony-saved-addresses/',
						HSA_PLUGIN_DIR . 'templates/'
					);
					?>
				<?php endforeach; ?>
			</div>

			<a href="#" role="button" class="hsa-btn hsa-btn--link" data-hsa-action="add-new">
				<?php esc_html_e( 'Agregar otra direccion', 'harmony-saved-addresses' ); ?>
			</a>

			<?php if ( ! empty( $settings['allow_separate_billing'] ) ) : ?>
				<label class="hsa-checkbox-row">
					<input type="checkbox" data-hsa-same-billing checked="checked" />
					<span><?php esc_html_e( 'Utilizar esta misma direccion para facturacion', 'harmony-saved-addresses' ); ?></span>
				</label>

				<div class="hsa-billing-selector" data-hsa-billing-selector hidden>
					<h4 class="hsa-panel__subtitle"><?php esc_html_e( 'Elige la direccion de facturacion', 'harmony-saved-addresses' ); ?></h4>
					<div class="hsa-address-list hsa-address-list--billing" data-hsa-billing-list></div>
				</div>
			<?php endif; ?>

			<a href="#" role="button" class="hsa-btn hsa-btn--primary hsa-btn--large" data-hsa-action="use-address">
				<?php echo esc_html( $settings['continue_button_text'] ?? __( 'Usar esta direccion', 'harmony-saved-addresses' ) ); ?>
			</a>

			<p class="hsa-status-msg" data-hsa-status role="status" aria-live="polite"></p>
		</div>

	<?php else : ?>

		<div class="hsa-panel hsa-panel--new" data-hsa-panel="new">
			<h3 class="hsa-panel__title"><?php esc_html_e( 'Ingresa tu direccion de entrega', 'harmony-saved-addresses' ); ?></h3>

			<?php if ( ! $is_logged_in ) : ?>
				<p class="hsa-login-prompt">
					<?php
					printf(
						/* translators: %s: login link */
						esc_html__( '¿Ya tienes una cuenta? %s para utilizar tus direcciones guardadas.', 'harmony-saved-addresses' ),
						'<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" data-hsa-action="show-login">' . esc_html__( 'Inicia sesion', 'harmony-saved-addresses' ) . '</a>'
					);
					?>
				</p>
				<p class="hsa-login-prompt hsa-login-prompt--secondary">
					<?php esc_html_e( 'Tambien puedes crear una cuenta durante la compra para guardar esta direccion.', 'harmony-saved-addresses' ); ?>
				</p>
			<?php endif; ?>

			<a href="#" role="button" class="hsa-btn hsa-btn--link" data-hsa-action="add-new">
				<?php esc_html_e( 'Agregar nueva direccion', 'harmony-saved-addresses' ); ?>
			</a>

			<p class="hsa-hint">
				<?php
				if ( $is_logged_in ) {
					esc_html_e( 'Guarda tu primera direccion para usarla en futuras compras. Tambien puedes completar el formulario de WooCommerce que aparece debajo.', 'harmony-saved-addresses' );
				} else {
					esc_html_e( 'Completa el formulario habitual de WooCommerce que aparece debajo. Si creas una cuenta, esta direccion se guardara automaticamente para tus proximas compras.', 'harmony-saved-addresses' );
				}
				?>
			</p>
		</div>

	<?php endif; ?>

	<div class="hsa-modal-overlay" data-hsa-modal-overlay hidden>
		<div class="hsa-modal" role="dialog" aria-modal="true" aria-labelledby="hsa-modal-title">
			<div class="hsa-modal__header">
				<h3 id="hsa-modal-title" data-hsa-modal-title><?php esc_html_e( 'Agregar nueva direccion', 'harmony-saved-addresses' ); ?></h3>
				<a href="#" role="button" class="hsa-modal__close" data-hsa-action="close-modal" aria-label="<?php esc_attr_e( 'Cerrar', 'harmony-saved-addresses' ); ?>">&times;</a>
			</div>
			<div class="hsa-modal__body" data-hsa-modal-body>
				<?php
				wc_get_template(
					'address-form.php',
					array( 'settings' => $settings ),
					'harmony-saved-addresses/',
					HSA_PLUGIN_DIR . 'templates/'
				);
				?>
			</div>
		</div>
	</div>

	<input type="hidden" name="hsa_selected_shipping_id" data-hsa-selected-shipping-id value="" />
	<input type="hidden" name="hsa_selected_billing_id" data-hsa-selected-billing-id value="" />
</div>
