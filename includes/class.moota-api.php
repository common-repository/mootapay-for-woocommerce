<?php

class Moota_Api {
	private static $base_api = 'https://api.mootapay.com/api/v1';
	private static $run;
	private $api_token;

	public function __construct( $api_token = null ) {
		if ( empty($api_token) ) {
			$api_token = get_option('mootapay_access_token');
		}

		$this->api_token = $api_token;
	}

	public static function run( $api_token = null ) {
		if ( ! self::$run instanceof self ) {
			self::$run = new self( $api_token );
		}

		return self::$run;
	}

	public function postApi( $endpoint, $args = [] ) {

		$ch = curl_init();

		$options = array(
			CURLOPT_URL => self::$base_api. $endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			CURLINFO_HEADER_OUT		=> true,
			CURLOPT_FOLLOWLOCATION => false,
		   // CURLOPT_ENCODING       => "utf-8",
			CURLOPT_AUTOREFERER    => true,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_POST            => 1,
			CURLOPT_POSTFIELDS     => $args['body'],
			CURLOPT_SSL_VERIFYHOST => 0,            
			CURLOPT_SSL_VERIFYPEER => false,        
			CURLOPT_VERBOSE        => 1,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $this->api_token,
			)

		);

		curl_setopt_array($ch, $options);

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headers = curl_getinfo($ch, CURLINFO_HEADER_OUT );

		curl_close($ch);

		if ( $httpcode != 200 ) {
			return (object)[ 'errors' => json_decode($response) ];
		} else {
			return json_decode($response);
		}
	}

	public function getApi( $endpoint, $headers = [] ) {

		$default_header = [
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_token,
		];

		$headers['headers'] = wp_parse_args( $headers, $default_header );
		$response           = wp_remote_get( self::$base_api . $endpoint, $headers );

		if ( ( ! is_wp_error( $response ) ) && ( 200 === wp_remote_retrieve_response_code( $response ) ) ) {
			$responseBody = json_decode( $response['body'] );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $responseBody;
			}
		}

		return [];
	}

	public function getPaymentMethod(): array {
		$payment_method = [];
		$response = $this->getApi( '/payment-method', [
			'page'     => 1,
			'per_page' => 100
		] );

		if ( ! empty($response->data) ) {
			foreach ($response->data as $item) {
				$payment_method[$item->category][] = $item;
			}

		}

		return $payment_method;
	}

	public function getBank($merchant_id = ""): array {
		$response =  $this->getApi( '/payment-methods/'.$merchant_id, [
			'page'     => 1,
			'per_page' => 50
		] );

		if ( ! empty($response->data) ) {
			return array_filter($response->data, fn($item) => $item->category == "bank_transfer");
		}

		return [];
	}

	public function getVirtualAccount($merchant_id = ""): array {
		$response =  $this->getApi( '/payment-methods/'.$merchant_id, [
			'page'     => 1,
			'per_page' => 50
		] );

		

		if ( ! empty($response->data) ) {
			return array_filter($response->data, fn($item) => $item->category == "escrow");
		}

		return [];
	}

	public function getMerchans(): array {
		$response =  $this->getApi( '/merchant', [
			'page'     => 1,
			'per_page' => 50
		] );

		if ( ! empty($response->data) ) {
			return $response->data;
		}

		return [];
	}

	public function getEscrow() {
		$escrow = [];
		$payment_method = $this->getPaymentMethod();
		if ( ! empty($payment_method['escrow']) ) {
			$escrow = $payment_method['escrow'];
		}

		return $escrow;
	}

	public function postTransaction( $data = [] ) {
		return $this->postApi( '/transaction', [
			'body' => wp_json_encode( $data )
		] );
	}

	public function getPluginToken(): string {
		$response = $this->getApi( '/plugin/token' );
		if ( ! empty($response->token) ) {
			return $response->token;
		}

		return "";
	}
}