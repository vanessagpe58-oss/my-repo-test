<?php
/**
 * Template: formulario para agregar o editar una direccion.
 * Puede ser sobrescrito copiandolo a: yourtheme/harmony-saved-addresses/address-form.php
 *
 * Variables disponibles:
 * @var array $settings Ajustes visuales del plugin.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$countries = function_exists( 'WC' ) ? WC()->countries->get_countries() : array();
$default_country = function_exists( 'WC' ) ? WC()->countries->get_base_country() : 'MX';
?>
<div class="hsa-address-form" data-hsa-address-form>
	<input type="hidden" name="id" data-hsa-field="id" value="" />

	<div class="hsa-form-grid">
		<div class="hsa-field hsa-field--full">
			<label for="hsa-f-alias"><?php esc_html_e( 'Nombre de la direccion (Casa, Trabajo, etc.)', 'harmony-saved-addresses' ); ?></label>
			<input type="text" id="hsa-f-alias" name="alias" data-hsa-field="alias" maxlength="60" placeholder="<?php esc_attr_e( 'Casa', 'harmony-saved-addresses' ); ?>" />
		</div>

		<div class="hsa-field">
			<label for="hsa-f-first-name"><?php esc_html_e( 'Nombre', 'harmony-saved-addresses' ); ?> *</label>
			<input type="text" id="hsa-f-first-name" name="first_name" data-hsa-field="first_name" required />
		</div>
		<div class="hsa-field">
			<label for="hsa-f-last-name"><?php esc_html_e( 'Apellidos', 'harmony-saved-addresses' ); ?> *</label>
			<input type="text" id="hsa-f-last-name" name="last_name" data-hsa-field="last_name" required />
		</div>

		<!-- Empresa deshabilitada -->
		<input type="hidden" name="company" data-hsa-field="company" value="" />
		<?php /*
		<div class="hsa-field hsa-field--full">
			<label for="hsa-f-company"><?php esc_html_e( 'Empresa (opcional)', 'harmony-saved-addresses' ); ?></label>
			<input type="text" id="hsa-f-company" name="company" data-hsa-field="company" />
		</div>
		*/ ?>

		<div class="hsa-field">
			<label for="hsa-f-country"><?php esc_html_e( 'Pais', 'harmony-saved-addresses' ); ?> *</label>
			<select id="hsa-f-country" name="country" data-hsa-field="country" required>
				<?php foreach ( $countries as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $default_country ); ?>>
						<?php echo esc_html( $name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="hsa-field">
			<label for="hsa-f-state"><?php esc_html_e( 'Estado', 'harmony-saved-addresses' ); ?> *</label>
			<select id="hsa-f-state" name="state" data-hsa-field="state" required></select>
		</div>

		<div class="hsa-field">
			<label for="hsa-f-city"><?php esc_html_e( 'Municipio o ciudad', 'harmony-saved-addresses' ); ?> *</label>
			<input type="text" id="hsa-f-city" name="city" data-hsa-field="city" required />
		</div>
		<div class="hsa-field">
			<label for="hsa-f-neighborhood"><?php esc_html_e( 'Colonia', 'harmony-saved-addresses' ); ?></label>
			<input type="text" id="hsa-f-neighborhood" name="neighborhood" data-hsa-field="neighborhood" />
		</div>

		<div class="hsa-field">
			<label for="hsa-f-postcode"><?php esc_html_e( 'Codigo postal', 'harmony-saved-addresses' ); ?> *</label>
			<input type="text" id="hsa-f-postcode" name="postcode" data-hsa-field="postcode" inputmode="numeric" required />
		</div>
		<div class="hsa-field">
			<label for="hsa-f-phone"><?php esc_html_e( 'Telefono', 'harmony-saved-addresses' ); ?> *</label>
			<input type="tel" id="hsa-f-phone" name="phone" data-hsa-field="phone" required />
		</div>

		<div class="hsa-field hsa-field--full">
			<label for="hsa-f-address1"><?php esc_html_e( 'Calle y numero exterior', 'harmony-saved-addresses' ); ?> *</label>
			<input type="text" id="hsa-f-address1" name="address_1" data-hsa-field="address_1" required />
		</div>
		<div class="hsa-field">
			<label for="hsa-f-address2"><?php esc_html_e( 'Numero interior (opcional)', 'harmony-saved-addresses' ); ?></label>
			<input type="text" id="hsa-f-address2" name="address_2" data-hsa-field="address_2" />
		</div>
		<div class="hsa-field">
			<label for="hsa-f-email"><?php esc_html_e( 'Correo electronico', 'harmony-saved-addresses' ); ?></label>
			<input type="email" id="hsa-f-email" name="email" data-hsa-field="email" />
		</div>

		<div class="hsa-field hsa-field--full">
			<label for="hsa-f-references"><?php esc_html_e( 'Referencias (opcional)', 'harmony-saved-addresses' ); ?></label>
			<textarea id="hsa-f-references" name="references" data-hsa-field="references" rows="2"></textarea>
		</div>

		<div class="hsa-field hsa-field--full">
			<label class="hsa-checkbox-row">
				<input type="checkbox" name="is_default" data-hsa-field="is_default" />
				<span><?php esc_html_e( 'Usar como direccion predeterminada', 'harmony-saved-addresses' ); ?></span>
			</label>
		</div>
	</div>

	<p class="hsa-form-error" data-hsa-form-error role="alert" hidden></p>

	<div class="hsa-form-actions">
		<a href="#" role="button" class="hsa-btn hsa-btn--secondary" data-hsa-action="close-modal">
			<?php esc_html_e( 'Cancelar', 'harmony-saved-addresses' ); ?>
		</a>
		<a href="#" role="button" class="hsa-btn hsa-btn--primary" data-hsa-action="save-address">
			<?php esc_html_e( 'Guardar direccion', 'harmony-saved-addresses' ); ?>
		</a>
	</div>
</div>
