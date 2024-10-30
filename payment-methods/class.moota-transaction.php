<?php

class Moota_Transaction {
	public static function request( $order_id, $channel_id, $with_unique_code, $start_unique_code, $end_unique_code, $payment_method_type = 'bank_transfer') {
		global $woocommerce;
		$order = new WC_Order( $order_id );

		$items = [];
		/**
		 * @var $item WC_Order_Item_Product
		 */
		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_product_id() );

			$image_meta = wp_get_attachment_metadata( $item->get_product_id() );
			$image_file = get_attached_file( $item->get_product_id(), false );

			$image_url = empty( $image_meta['original_image'] ) ? $image_file : path_join( dirname( $image_file ), $image_meta['original_image'] );

			if(empty($image_url)){
				$image_url = get_the_post_thumbnail_url( $item->get_product_id() );
			}

			$items[] = [
				'name'      => $item->get_name(),
				'qty'       => $item->get_quantity(),
				'price'     => $product->get_price() * $item->get_quantity(),
				'sku'       => $product->get_sku(),
				'image_url' => $image_url
			];

			if ( empty( $product->get_sku() ) ) {
				wc_add_notice( '<strong>SKU salah</strong> Hubungi Admin', 'error' );

				return false;
			}
		}

		if ( $order->get_shipping_total() ) {
			$items[] = [
				'name'      => 'Ongkos Kirim',
				'qty'       => 1,
				'price'     => $order->get_shipping_total(),
				'sku'       => 'shipping-cost',
				'image_url' => ''
			];
		}

		$tax = 0;

		if ( $order->get_tax_totals() ) {
			foreach ( $order->get_tax_totals() as $i ) {
				$tax += $i->amount;
			}
			$items[] = [
				'name'      => 'Pajak',
				'qty'       => 1,
				'price'     => $tax,
				'sku'       => 'taxes-cost',
				'image_url' => ''
			];
		}

		if ( strlen( $start_unique_code ) < 2 ) {
			$start_unique_code = sprintf( '%02d', $start_unique_code );
		}

		if ( $start_unique_code > $end_unique_code ) {
			$end_unique_code += 10;
		}

		$hours = (int) get_option("woomoota_expiration_hour", 3);

		$expired_date = date( 'Y-m-d H:i:s', strtotime( "+{$hours} hours" ) );

		$invoice_date = date("YmdHis");

		$parsed_items = [];

		foreach($items as $item){
			if($item['image_url'] == false){
				$item['image_url'] = "https://via.placeholder.com/150 ";
			}

			$parsed_items[] = $item;
		}

		$payments = moota_get_virtual();

		$selected_payment = [];

		$ewallet_expiration = get_option("woomoota_ewallet_expiration", 20);

		$va_expiration = get_option("woomoota_va_expiration", 2);

		foreach($payments as $payment){
			if($payment->payment_method_id == $channel_id){
				$selected_payment = $payment;
			}
		}

		if(str_contains($selected_payment->payment_method_type, "ewallet")){
			$expired_date = date( 'Y-m-d H:i:s', strtotime( "+".$ewallet_expiration." minute" ) );
		}

		if(str_contains($selected_payment->payment_method_type, "virtual")){
			$expired_date = date( 'Y-m-d H:i:s', strtotime( "+".$va_expiration." hour" ) );
		}

		$args = [
			"invoice_number"		=>	"INV-".$invoice_date.$order->get_order_number(),
			"merchant_id"			=>	get_option("merchant"),
			"amount"				=>	$order->get_total(),
			"payment_method_id"		=>	$channel_id,
			"type"					=>	"payment",
			"callback_url"			=>	home_url( 'mootapay-callback' ),
			"expired_date"			=>	self::convertDateTime( $expired_date ),
			"customer"				=>	[
				'name'  			=>	$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'email' 			=>	$order->get_billing_email(),
				'phone' 			=>	$order->get_billing_phone()
			],
			"items"					=>	$parsed_items,
			"with_unique_code"		=>	$with_unique_code == "no" ? 0 : 1,
			"start_unique_code"		=>	$start_unique_code,
			"end_unique_code"		=>	$end_unique_code
			
		];

		$payment_link = self::get_return_url( $order );

		$response = Moota_Api::run()->postTransaction( $args );

		// var_dump($response, ! empty( $response->data ));

		if ( $response && ! empty( $response->data ) ) {

			if ( get_option( 'payment_mode', 'direct' ) == 'redirect' ) {
				$payment_link = $response->data->payment_link;
			}

			$order->update_meta_data( "trx_id", $response->data->trx_id );
			$order->update_meta_data( "unique_code", $response->data->unique_code );
			$order->update_meta_data( "total", $response->data->total );
			$order->update_meta_data( "payment_link", $response->data->payment_link );

			

			

			if(str_contains($selected_payment->payment_method_type, 'ewallet')){
				Moota_Api::run()->postApi("transaction/ewallet-request-payment/".$response->data->trx_id, []);
			}

		} else {
			if ( isset( $response->errors ) ) {
				foreach ( $response->errors as $error => $msg ) {

					wc_add_notice( '<strong>' . $msg->expired_date[0] ?? $msg . '</strong> ' . $msg->expired_date[0] ?? $msg, 'error' );
				}
			} else {
				wc_add_notice( '<strong>Terjadi Masalah Server</strong> Coba beberapa saat lagi', 'error' );
			}

			return false;
		}

		// Mark as on-hold (we're awaiting the cheque)
		$order->update_status( 'on-hold', __( 'Awaiting Payment', 'woocommerce-gateway-moota' ) );

		// Remove cart
		$woocommerce->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result'   => 'success',
			'redirect' => $payment_link
		);
	}

	public static function get_return_url( $order ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	private static function convertDateTime( $date, $format = 'Y-m-d H:i:s' ) {
		$tz1 = 'UTC';
		$tz2 = 'Asia/Jakarta'; // UTC +7

		$d = new DateTime( $date, new DateTimeZone( $tz1 ) );
		$d->setTimeZone( new DateTimeZone( $tz2 ) );

		return $d->format( $format );
	}

}