<?php
/**
 * Template: tarjeta individual de una direccion guardada.
 * Puede ser sobrescrito copiandolo a: yourtheme/harmony-saved-addresses/address-card.php
 *
 * Variables disponibles:
 * @var array $address  Datos de la direccion.
 * @var array $settings Ajustes visuales del plugin.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_default = ! empty( $address['is_default'] );
$full_name  = trim( ( $address['first_name'] ?? '' ) . ' ' . ( $address['last_name'] ?? '' ) );

$state_name   = $address['state'] ?? '';
$country_code = $address['country'] ?? '';
if ( function_exists( 'WC' ) && $country_code ) {
	$states = WC()->countries->get_states( $country_code );
	if ( isset( $states[ $state_name ] ) ) {
		$state_name = $states[ $state_name ];
	}
}
?>
<div
	class="hsa-address-card<?php echo $is_default ? ' hsa-address-card--default' : ''; ?>"
	data-hsa-address-card
	data-address-id="<?php echo esc_attr( $address['id'] ?? '' ); ?>"
	tabindex="0"
	role="button"
	aria-pressed="false"
>
	<div class="hsa-address-card__radio" aria-hidden="true">
		<span class="hsa-address-card__radio-dot"></span>
	</div>

	<div class="hsa-address-card__content">
		<?php if ( ! empty( $settings['show_alias'] ) && ! empty( $address['alias'] ) ) : ?>
			<p class="hsa-address-card__alias">
				<?php echo esc_html( $address['alias'] ); ?>
				<?php if ( $is_default ) : ?>
					<span class="hsa-badge"><?php esc_html_e( 'Predeterminada', 'harmony-saved-addresses' ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<p class="hsa-address-card__name"><?php echo esc_html( $full_name ); ?></p>
		<p class="hsa-address-card__line">
			<?php echo esc_html( trim( ( $address['address_1'] ?? '' ) . ( ! empty( $address['address_2'] ) ? ' Int. ' . $address['address_2'] : '' ) ) ); ?>
		</p>
		<?php if ( ! empty( $address['neighborhood'] ) ) : ?>
			<p class="hsa-address-card__line"><?php echo esc_html( $address['neighborhood'] ); ?></p>
		<?php endif; ?>
		<p class="hsa-address-card__line">
			<?php echo esc_html( trim( ( $address['city'] ?? '' ) . ( $state_name ? ', ' . $state_name : '' ) ) ); ?>
		</p>
		<p class="hsa-address-card__line"><?php echo esc_html( $address['postcode'] ?? '' ); ?></p>
		<?php if ( ! empty( $address['phone'] ) ) : ?>
			<p class="hsa-address-card__line hsa-address-card__phone"><?php echo esc_html( $address['phone'] ); ?></p>
		<?php endif; ?>

		<p class="hsa-address-card__selected-flag" data-hsa-selected-flag>
			<?php esc_html_e( 'Direccion seleccionada', 'harmony-saved-addresses' ); ?>
		</p>

		<div class="hsa-address-card__actions">
			<button type="button" class="hsa-btn hsa-btn--text" data-hsa-action="edit" data-address-id="<?php echo esc_attr( $address['id'] ?? '' ); ?>">
				<?php esc_html_e( 'Modificar direccion', 'harmony-saved-addresses' ); ?>
			</button>
			<?php if ( ! $is_default ) : ?>
				<button type="button" class="hsa-btn hsa-btn--text" data-hsa-action="make-default" data-address-id="<?php echo esc_attr( $address['id'] ?? '' ); ?>">
					<?php esc_html_e( 'Marcar como predeterminada', 'harmony-saved-addresses' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( ! empty( $settings['allow_delete_addresses'] ) ) : ?>
				<button type="button" class="hsa-btn hsa-btn--text hsa-btn--danger" data-hsa-action="delete" data-address-id="<?php echo esc_attr( $address['id'] ?? '' ); ?>">
					<?php esc_html_e( 'Eliminar', 'harmony-saved-addresses' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
</div>
