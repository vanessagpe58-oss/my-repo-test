<?php
/**
 * Rediseña la pantalla de confirmación de pedido de WooCommerce.
 *
 * @package Harmony_Saved_Addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HSA_Thankyou_Page {

	public function __construct() {
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_thankyou_template' ), 20, 3 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'send_purchase_confirmation_email' ), 20, 1 );
	}

	/**
	 * Sustituye únicamente el template interno de agradecimiento, conservando
	 * la cabecera, el pie y la estructura normal del tema activo.
	 */
	public function locate_thankyou_template( string $template, string $template_name, string $template_path ): string {
		if ( 'checkout/thankyou.php' !== $template_name ) {
			return $template;
		}

		$custom = HSA_PLUGIN_DIR . 'templates/thankyou.php';
		return file_exists( $custom ) ? $custom : $template;
	}

	/**
	 * Envía una confirmación visual de compra al correo de facturación.
	 * Se marca el pedido para impedir envíos duplicados cuando una pasarela
	 * dispara más de uno de los hooks de pago/estado.
	 */
	public function send_purchase_confirmation_email( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( ! is_email( $email ) || 'yes' === $order->get_meta( '_hsa_confirmation_email_sent', true ) ) {
			return;
		}

		$first_name = trim( (string) $order->get_billing_first_name() );
		$greeting   = $first_name ? sprintf( __( '¡Gracias, %s!', 'harmony-saved-addresses' ), $first_name ) : __( '¡Gracias por tu compra!', 'harmony-saved-addresses' );
		$shop_url   = wc_get_page_permalink( 'shop' );
		$items_html = '';

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			$image   = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '';
			$image_html = $image ? '<img src="' . esc_url( $image ) . '" width="58" height="58" alt="" style="display:block;width:58px;height:58px;object-fit:contain;border-radius:12px;background:#FAF8FF;">' : '';
			$items_html .= '<tr><td style="padding:14px 0;border-bottom:1px solid #ECE8F8;width:72px;vertical-align:middle;">' . $image_html . '</td><td style="padding:14px 10px;border-bottom:1px solid #ECE8F8;vertical-align:middle;font-size:15px;font-weight:700;color:#17172F;">' . esc_html( $item->get_name() ) . '</td><td style="padding:14px 0;border-bottom:1px solid #ECE8F8;vertical-align:middle;text-align:right;"><span style="display:inline-block;padding:9px 12px;border-radius:999px;background:#FAF8FF;color:#5C1ACF;font-weight:800;">x' . absint( $item->get_quantity() ) . '</span></td></tr>';
		}

		$subject = sprintf( __( 'Compra confirmada · Pedido #%s', 'harmony-saved-addresses' ), $order->get_order_number() );
		$message = '<!doctype html><html><body style="margin:0;padding:0;background:#f6f4fb;font-family:Arial,Helvetica,sans-serif;color:#17172F;">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f4fb;padding:28px 12px;"><tr><td align="center">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;background:#ffffff;border:1px solid #ECE8F8;border-radius:22px;overflow:hidden;box-shadow:0 12px 32px rgba(92,26,207,.10);">'
			. '<tr><td style="padding:38px 30px;text-align:center;background:linear-gradient(135deg,#5C1ACF,#7E3AF2,#A855F7);color:#ffffff;">'
			. '<div style="width:62px;height:62px;margin:0 auto 18px;border-radius:50%;background:#ffffff;color:#5C1ACF;font-size:38px;line-height:62px;font-weight:800;">✓</div>'
			. '<h1 style="margin:0 0 10px;font-size:30px;line-height:1.2;color:#ffffff;">' . esc_html( $greeting ) . '</h1>'
			. '<p style="margin:0;font-size:16px;line-height:1.6;color:#ffffff;">Tu pedido ha sido confirmado correctamente.</p></td></tr>'
			. '<tr><td style="padding:30px;">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;background:#FAF8FF;border-radius:16px;"><tr>'
			. '<td style="padding:18px;font-size:13px;color:#666;">Número de pedido<br><strong style="font-size:16px;color:#5C1ACF;">#' . esc_html( $order->get_order_number() ) . '</strong></td>'
			. '<td style="padding:18px;font-size:13px;color:#666;">Fecha de compra<br><strong style="font-size:16px;color:#17172F;">' . esc_html( wc_format_datetime( $order->get_date_created(), 'j \d\e F \d\e Y' ) ) . '</strong></td>'
			. '<td style="padding:18px;font-size:13px;color:#666;text-align:right;">Total<br><strong style="font-size:16px;color:#17172F;">' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></td>'
			. '</tr></table>'
			. '<h2 style="margin:0 0 10px;font-size:21px;color:#17172F;">Productos de tu pedido</h2>'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $items_html . '</table>'
			. '<div style="margin-top:26px;text-align:center;"><a href="' . esc_url( $shop_url ) . '" style="display:inline-block;padding:15px 30px;border-radius:14px;background:#6D28D9;color:#ffffff;text-decoration:none;font-size:16px;font-weight:800;">Seguir comprando</a></div>'
			. '<p style="margin:24px 0 0;text-align:center;font-size:13px;color:#666;">♡ Gracias por ser parte de Harmony.</p>'
			. '</td></tr></table></td></tr></table></body></html>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( wp_mail( $email, $subject, $message, $headers ) ) {
			$order->update_meta_data( '_hsa_confirmation_email_sent', 'yes' );
			$order->save();
		}
	}

	public function body_class( array $classes ): array {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
			$classes[] = 'hsa-custom-thankyou-page';
		}
		return $classes;
	}
}
