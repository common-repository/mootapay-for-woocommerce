<?php

class WC_Moota_Advanced {

	private $merchant_lists = [];

	public function __construct() {

		$this->merchant = get_option("merchant");

		foreach(moota_get_merchant() as $merchant){
			$this->merchant_lists[$merchant->merchant_id] = $merchant->merchant_name; 	
		}

		add_filter( 'woocommerce_get_sections_advanced', [ $this, 'general' ], 20 );
		add_filter( 'woocommerce_get_settings_advanced', [ $this, 'get_settings_for_moota_section' ], 10, 2 );

		add_action( 'woocommerce_update_options_advanced_moota', [ $this, 'save' ] );
	}


	public function general( $sections ) {

		$sections['moota'] = 'Moota Pay';

		return $sections;
	}

	public function get_settings_for_moota_section( $settings, $current_screen ) {
		if ( 'moota' !== $current_screen ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Pengaturan Umum Moota', 'woocommerce-gateway-moota' ),
				'type'  => 'title',
				'desc'  => 'Semua Pembayaran Moota Transaksi Menggunakan Bagian Pengaturan Ini',
			),
			array(
				'id'       => 'mootapay_access_token',
				'title'    => __( 'Access Token', 'woocommerce-gateway-moota' ),
				'type'     => 'password',
				'desc'     => __( 'Mootapay Access Token, <a href="https://app.mootapay.com/integration/api" target="_blank">Ambil Token Disini</a>', 'woocommerce-gateway-moota' ),
				'default'  => null,
				'desc_tip' => false,
			),

			array(
				'title' 		=> __("Merchant"),
				'id'			=> "merchant",
				'type'    		=> 'select',
				'label'   		=> __( 'Pilih Merchant', 'woocommerce-gateway-moota' ),
				'options'		=> $this->merchant_lists
			),
			
			array(
				'id'       => 'payment_mode',
				'title'    => __( 'Payment Mode', 'woocommerce-gateway-moota' ),
				'type'     => 'select',
				'desc'     => __( 'Pembayaran melalui Woocommerce atau Halaman Moota', 'woocommerce-gateway-moota' ),
				'options'  => array(
					'direct'   => 'WooCommerce Page',
					'redirect' => 'Moota Checkout Page'
				),
				'default'  => null,
				'desc_tip' => false,
			),
			array(
				'id'       => 'success_status',
				'title'    => __( 'Status Berhasil', 'woocommerce-gateway-moota' ),
				'type'     => 'select',
				'desc'     => __( 'Status setelah berhasil menemukan order yang telah dibayar', 'woocommerce-gateway-moota' ),
				'default'  => 'processing',
				'desc_tip' => true,
				'options'  => array(
					'completed'  => 'Completed',
					'on-hold'    => 'On Hold',
					'processing' => 'Processing'
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'moota_options',
			),

		);
	}

	public function save() {

		$this->fetch_merchants();
		$this->fetch_bank();
		$this->fetch_escrow();
		$this->get_plugin_token();
	}

	private function fetch_bank() {
		$lists_bank = Moota_Api::run()->getBank($this->merchant);

		if ( ! empty( $lists_bank ) ) {
			update_option( '_user_bank_lists', $lists_bank );
		}
	}

	private function fetch_merchants()
	{
		$list_merchant = Moota_Api::run()->getMerchans();

		if(!empty($list_merchant)){
			update_option( '_user_merchant_lists', $list_merchant );
		}
	}

	private function get_plugin_token() {
		$plugin_token = Moota_Api::run()->getPluginToken();
		if ( ! empty( $plugin_token ) ) {
			update_option( 'plugin_token', $plugin_token );
		}
	}

	private function fetch_escrow() {
		$lists_escrow = Moota_Api::run()->getVirtualAccount($this->merchant);
		if ( $lists_escrow ) {
			update_option( '_user_escrow_lists', $lists_escrow );
		}
	}

}

new WC_Moota_Advanced();