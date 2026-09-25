<?php
/**
 * Template: seccion "Mis direcciones" dentro de Mi cuenta.
 * Puede ser sobrescrito copiandolo a: yourtheme/harmony-saved-addresses/account-addresses.php
 *
 * Variables disponibles:
 * @var array $addresses Direcciones guardadas del usuario.
 * @var array $settings  Ajustes visuales del plugin.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="hsa-account-addresses" id="hsa-address-selector-root" data-hsa-root="1" data-hsa-context="account">

	<h2><?php esc_html_e( 'Mis direcciones', 'harmony-saved-addresses' ); ?></h2>
	<p class="hsa-account-intro">
		<?php esc_html_e( 'Administra las direcciones que usas para tus compras. Puedes agregar, editar, eliminar o marcar una como predeterminada.', 'harmony-saved-addresses' ); ?>
	</p>

	<div class="hsa-address-list hsa-address-list--account" data-hsa-address-list>
		<?php if ( empty( $addresses ) ) : ?>
			<p class="hsa-empty-state"><?php esc_html_e( 'Todavia no tienes direcciones guardadas.', 'harmony-saved-addresses' ); ?></p>
		<?php else : ?>
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
		<?php endif; ?>
	</div>

	<button type="button" class="hsa-btn hsa-btn--primary" data-hsa-action="add-new">
		<?php esc_html_e( 'Agregar nueva direccion', 'harmony-saved-addresses' ); ?>
	</button>

	<div class="hsa-modal-overlay" data-hsa-modal-overlay hidden>
		<div class="hsa-modal" role="dialog" aria-modal="true" aria-labelledby="hsa-modal-title">
			<div class="hsa-modal__header">
				<h3 id="hsa-modal-title" data-hsa-modal-title><?php esc_html_e( 'Agregar nueva direccion', 'harmony-saved-addresses' ); ?></h3>
				<button type="button" class="hsa-modal__close" data-hsa-action="close-modal" aria-label="<?php esc_attr_e( 'Cerrar', 'harmony-saved-addresses' ); ?>">&times;</button>
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
</div>
