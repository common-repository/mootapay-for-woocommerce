<?php

use Moota\Moota\Config\Moota;

class WC_Moota_Virtual_Account extends WC_Payment_Gateway {

	private $all_escrow = [];
	private $escrow_selection = [];

	public function __construct() {
		$this->id                 = 'moota-virtual-account';
		$this->has_fields         = true;
		$this->method_title       = 'MootaPay';
		$this->method_description = 'Terima Pembayaran Melalui Virtual Account';

		$this->init_form_fields();

		$this->init_settings();

		// Populate Values settings
		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options'
		] );

		// custom fields
		add_filter( 'woocommerce_generate_escrow_lists_html', [ $this, 'escrow_lists' ], 99, 4 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, function ( $settings ) {
			return $settings;
		} );

        add_action('woocommerce_order_details_after_order_table', [$this, 'order_details'], 99);
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-moota' ),
				'type'    => 'checkbox',
				'label'   => __( 'Aktifkan Moota Transaksi', 'woocommerce-gateway-moota' ),
				'default' => 'yes'
			),

			'title'              => array(
				'title'       => __( 'Title', 'woocommerce-gateway-moota' ),
				'type'        => 'text',
				'description' => __( 'Nama Yang Muncul Di halaman Checkout', 'woocommerce-gateway-moota' ),
				'default'     => __( 'MootaPay Virtual Account', 'woocommerce-gateway-moota' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Deskripsi', 'woocommerce-gateway-moota' ),
				'type'        => 'textarea',
				'description' => 'Penjelasan akan muncul di halaman checkout',
				'default'     => '',
				'desc_tip'    => true,
			),
			'bank_account_title' => array(
				'title'       => __( 'Pengaturan Pembayaran', 'woocommerce-gateway-moota' ),
				'type'        => 'title',
				'description' => 'Pilih Akun Bank Yang akan ditampilkan halaman pembayaran',
			),
			'toggle_status'      => array(
				'title'       => __( 'Nomor Unik?', 'woocommerce-gateway-moota' ),
				'type'        => 'checkbox',
				'description' => __( 'Centang, untuk aktifkan fitur penambahan 3 angka unik di setiap akhir pesanan / order. Sebagai pembeda dari order satu dengan yang lainnya.', 'woocommerce-gateway-moota' ),
				'desc_tip'    => true,
			),
			'type_append'        => array(
				'title'       => __( 'Tipe Tambahan', 'woocommerce-gateway-moota' ),
				'type'        => 'select',
				'description' => __( 'Increase = Menambah unik number ke total harga, Decrease = Mengurangi total harga dengan unik number', 'woocommerce-gateway-moota' ),
				'default'     => 'increase',
				'desc_tip'    => true,
				'options'     => array(
					'increase' => 'Tambahkan',
					'decrease' => 'Kurangi'
				),
				'id'          => 'woomoota_type_append'
			),
			'va_expiration' => array(
				'title'             => __( 'Batas Kadaluarsa Transaksi Untuk Virtual Account', 'woocommerce-gateway-moota' ),
				'type'              => 'number',
				'description'       => __( 'Batas kadaluarsa transaksi dalam hitungan jam. cont : 20 (untuk 20 jam)', 'woocommerce-gateway-moota' ),
				'id'                => 'woomoota_expiration_hour',
				'default'           => 3,
				'custom_attributes' => array(
					'min' => 1,
					'max' => 24
				),
				'desc_tip'          => true,
			),
			'ewallet_expiration' => array(
				'title'             => __( 'Batas Kadaluarsa Transaksi Untuk Ewallet (QRis, Shopeepay, OVO)', 'woocommerce-gateway-moota' ),
				'type'              => 'number',
				'description'       => __( 'Batas kadaluarsa transaksi dalam hitungan menit. cont : 20 (untuk 20 menit)', 'woocommerce-gateway-moota' ),
				'id'                => 'woomoota_expiration_hour',
				'default'           => 20,
				'custom_attributes' => array(
					'min' => 1,
					'max' => 20
				),
				'desc_tip'          => true,
			),
			'unique_start'       => array(
				'title'             => __( 'Batas Awal Angka Unik', 'woocommerce-gateway-moota' ),
				'type'              => 'number',
				'description'       => __( 'Masukan batas awal angka unik', 'woocommerce-gateway-moota' ),
				'id'                => 'woomoota_start_unique_number',
				'default'           => 1,
				'custom_attributes' => array(
					'min' => 0,
					'max' => 99999
				),
				'desc_tip'          => true,
			),
			'unique_end'         => array(
				'title'             => __( 'Batas Akhir Angka Unik', 'woocommerce-gateway-moota' ),
				'type'              => 'number',
				'description'       => __( 'Masukan batas akhir angka unik', 'woocommerce-gateway-moota' ),
				'id'                => 'woomoota_end_unique_number',
				'default'           => 999,
				'custom_attributes' => array(
					'min' => 0,
					'max' => 99999
				),
				'desc_tip'          => true,
			),
			'escrow_lists'         => array(
				'title'       => __( 'Daftar Rekening Bersama (VA)', 'woocommerce-gateway-moota' ),
				'type'        => 'escrow_lists',
				'description' => __( 'Pilih Bank yang ingin digunakan', 'woocommerce-gateway-moota' ),
				'id'          => 'woomoota_bank_list',
			),
		);
	}

	public function init_settings() {
		parent::init_settings(); // TODO: Change the autogenerated stub
	}

	// Custom fields for check list bank
	public function escrow_lists( $html, $k, $v, $object ) {

		ob_start();
		$field_key       = $object->get_field_key( $k );
		$escrow          = moota_get_virtual();

		?>
		</table>
		<h3 class="wc-settings-sub-title "
		    id="woocommerce_moota-bank-transfer_bank_account_<?php echo esc_attr($v['id']); ?>>"><?php echo esc_attr($v['title']); ?></h3>
		<?php if ( ! empty( esc_attr($v['description']) ) ) : ?>
			<p><?php echo esc_attr($v['description']); ?></p>
		<?php endif; ?>
		<table class="form-table">
		<?php if ( is_array( $escrow ) ) : ?>
			<?php foreach ( $escrow as $item ) :
				$field_key_escrow = $item->payment_method_type;
				$checked = $this->escrow_lists_checked( $k, $field_key_escrow, $item->payment_method_id );
				?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $item->name ); ?></label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span><?php echo wp_kses_post( $item->name ); ?></span>
							</legend>
							<input type="checkbox" name="<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($field_key_escrow); ?>]"
							       id="<?php echo esc_attr($field_key . '_' . $item->payment_method_id); ?>"
							       value="<?php echo esc_attr($item->payment_method_id); ?>" <?php echo esc_attr($checked) ? "checked" : ""; ?>/>
						</fieldset>
					</td>
				</tr>

			<?php endforeach; ?>
		<?php endif; ?>

		<?php

		return ob_get_clean();
	}

	// Custom Validate
	public function validate_escrow_lists_field( $key, $value ) {
		return $value;
	}

	// handle selection bank
	private function escrow_lists_checked( $k, $field_key, $value ): bool {
		if ( empty( $this->escrow_selection ) ) {
			$this->escrow_selection = $this->get_option( $k );
		}

		return ! empty( $this->escrow_selection[ $field_key ] ) && $this->escrow_selection[ $field_key ] == $value;
	}


	/**
	 * Handle WooCommerce Checkout
	 */
	private function escrow_selection( $payment_id ) {

        if ( empty($this->all_escrow) ) {
            $this->all_escrow = moota_get_virtual();
        }

        if ( ! empty($this->all_escrow) ) {
            foreach ($this->all_escrow as $escrow) {
                if ( $payment_id == $escrow->payment_method_id ) {
                    return $escrow;
                }
            }
        }

		return [];
	}

	public function payment_fields() {

        $escrow = $this->settings['escrow_lists'];
		 ?>
		 <ul>
		 <?php if ( ! empty( $escrow ) ) :
             foreach ( $escrow as $item ) :
                    $escrow_selection = $this->escrow_selection( $item );
                    $item = wp_kses_post( $item );
                 ?>
                 <li>
                     <label for="bank-transfer-<?php echo esc_attr($escrow_selection->payment_method_type); ?> va-id-<?php echo esc_attr($item); ?>">
                     <input id="bank-transfer-va-id-<?php echo esc_attr($item); ?>" name="channels" type="radio"
                     value="<?php echo esc_attr($item); ?>">
                     <span><img  src="<?php echo esc_attr($escrow_selection->icon);?>" alt="<?php echo esc_attr($escrow_selection->payment_method_type); ?>"></span>
                     <span class="moota-bank-account"><?php echo esc_attr($escrow_selection->name); ?></span>
                     </label>
                 </li>
             <?php endforeach;
         endif; ?>
		 </ul>
		 <?php
		 $description = $this->get_description();
         if ( $description ) {
             echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
         }
	}


    public function process_payment( $order_id ) {
        $channel_id = sanitize_text_field( $_POST['channels'] );
        $va_detail = $this->escrow_selection( $channel_id );

        $with_unique_code = $this->settings['toggle_status'];
        $unique_start = $this->settings['unique_start'];
        $unique_end = $this->settings['unique_end'];

        return Moota_Transaction::request(
                $order_id,
                $channel_id,
                $with_unique_code,
                $unique_start,
                $unique_end,
                $va_detail->payment_method_type
            );
    }

    public function order_details($order) {
        if ( $order->get_payment_method() == $this->id ) {
            $kodeunik = get_post_meta($order->get_id(), 'unique_code', true );
            $total = get_post_meta($order->get_id(), 'total', true );
            $payment_link = get_post_meta($order->get_id(), 'payment_link', true );
            ?>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
               <?php if ( $this->settings['toggle_status'] != 'no' ) : ?>
               <tr>
                    <th scope="row">Kode Unik</th>
                    <td><?php echo esc_attr($kodeunik); ?></td>
               </tr>
               <?php endif;?>
               <tr>
                   <th scope="row">Nominal Yang Harus Dibayar</th>
                   <td><?php echo wc_price($total);?></td>
               </tr>
               <tr>
                    <td colspan="2" style="text-align: center"><a href="<?php echo esc_attr($payment_link);?>">Check Status Pembayaran</a></td>
               </tr>
            </table>
            <?php
        }
    }

}