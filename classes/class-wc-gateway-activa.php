<?php
/**
 * activa Payment Gateway
 *
 * Provides a activa Payment Gateway.
 *
 * @class 		woocommerce_activa
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		WooThemes
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * add_testmode_admin_settings_notice()
 * plugin_url()
 * add_currency()
 * add_currency_symbol()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_activa_form()
 * process_payment()
 * receipt_page()
 * check_itn_request_is_valid()
 * check_itn_response()
 * successful_request()
 * setup_constants()
 * validate_signature()
 * validate_ip()
 * validate_response_data()
 * amounts_equal()
 */
class WC_Gateway_Activa extends WC_Payment_Gateway {

	public $version = '1.0.0';

	public function __construct() {
        global $woocommerce;
        $this->id			= 'activa';
        $this->method_title = __( 'Activa', 'woocommerce-gateway-activa' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;
        $this->debug_email 	= get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries = array( 'SI' );

		// Setup available currency codes.
		$this->available_currencies = array( 'EUR' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		$this->setup_constants();

		// Setup default merchant data.
		$this->merchant_id = $this->settings['merchant_id'];
		$this->merchant_key = $this->settings['merchant_key'];
		$this->url = 'https://test4.constriv.com/cg301/servlet/PaymentInitHTTPServlet';
		$this->validate_url = 'https://test4.constriv.com/cg301/servlet/PaymentInitHTTPServlet';
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->add_testmode_admin_settings_notice();
			$this->url = 'https://test4.constriv.com/cg301/servlet/PaymentInitHTTPServlet';
			$this->validate_url = 'https://test4.constriv.com/cg301/servlet/PaymentInitHTTPServlet';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'response_callback', home_url( '/' ) );

		add_action( 'woocommerce_api_wc_gateway_activa', array( $this, 'check_itn_response' ) );
		add_action( 'valid-activa-standard-itn-request', array( $this, 'successful_request' ) );

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_activa', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woocommerce-gateway-activa' ),
											'label' => __( 'Enable activa', 'woocommerce-gateway-activa' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-activa' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woocommerce-gateway-activa' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-activa' ),
    										'default' => __( 'activa', 'woocommerce-gateway-activa' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woocommerce-gateway-activa' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-activa' ),
											'default' => ''
										),
							'testmode' => array(
											'title' => __( 'Activa Sandbox', 'woocommerce-gateway-activa' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-activa' ),
											'default' => 'yes'
										),
							'merchant_id' => array(
											'title' => __( 'Merchant ID', 'woocommerce-gateway-activa' ),
											'type' => 'text',
											'description' => __( 'This is the merchant ID, received from activa.', 'woocommerce-gateway-activa' ),
											'default' => ''
										),
							'merchant_key' => array(
											'title' => __( 'Merchant Key', 'woocommerce-gateway-activa' ),
											'type' => 'text',
											'description' => __( 'This is the merchant key, received from activa.', 'woocommerce-gateway-activa' ),
											'default' => ''
										),
							'send_debug_email' => array(
											'title' => __( 'Send Debug Emails', 'woocommerce-gateway-activa' ),
											'type' => 'checkbox',
											'label' => __( 'Send debug e-mails for transactions through the activa gateway (sends on successful transaction as well).', 'woocommerce-gateway-activa' ),
											'default' => 'yes'
										),
							'capture_action' => array(
											'title'       => __( 'Action type', 'woocommerce-gateway-activa' ),
											'type'        => 'select',
											'label' => __( 'Select Prurchase for automatic capture or Authorization for manual capture.' ),
											'options'     => array(
												'1'   => __( 'Purchase', 'woocommerce-gateway-activa' ),
												'4'   => __( 'Authorization', 'woocommerce-gateway-activa' ),
											),
											'default'     => '1',
							),
							'debug_email' => array(
											'title' => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-activa' ),
											'type' => 'text',
											'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-activa' ),
											'default' => get_option( 'admin_email' )
										)
							);

    }

    /**
     * add_testmode_admin_settings_notice()
     *
     * Add a notice to the merchant_key and merchant_id fields when in test mode.
     *
     * @since 1.0.0
     */
    function add_testmode_admin_settings_notice () {
    	$this->form_fields['merchant_id']['description'] .= ' <strong>' . __( 'Activa Sandbox Merchant ID currently in use.', 'woocommerce-gateway-activa' ) . ' ( 89910582 )</strong>';
    	$this->form_fields['merchant_key']['description'] .= ' <strong>' . __( 'Activa Sandbox Merchant Key currently in use.', 'woocommerce-gateway-activa' ) . ' ( JvrJqy37xd )</strong>';
    }

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	}

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;
		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' && $this->settings['merchant_id'] != '' && $this->settings['merchant_key'] != '' )
			$is_available = true;

        return $is_available;
	} 

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
    	?>
    	<h3><?php _e( 'Activa', 'woocommerce-gateway-activa' ); ?></h3>
    	<p><?php printf( __( 'activa works by sending the user to %sactiva%s to enter their payment information.', 'woocommerce-gateway-activa' ), '<a href="http://activa.co.za/">', '</a>' ); ?></p>

    	<?php
    	if ( 'EUR' == get_option( 'woocommerce_currency' ) ) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
				// Determine the settings URL where currency is adjusted.
				$url = admin_url( 'admin.php?page=wc-settings&tab=general' );
				// Older settings screen.s
				if ( isset( $_GET['page'] ) && 'woocommerce' == $_GET['page'] ) {
					$url = admin_url( 'admin.php?page=woocommerce&tab=catalog' );
				}
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce-gateway-activa' ); ?></strong> <?php echo sprintf( __( 'Choose Euros as your store currency in %1$sPricing Options%2$s to enable the activa Gateway.', 'woocommerce-gateway-activa' ), '<a href="' . esc_url( $url ) . '">', '</a>' ); ?></p></div>
		<?php
		}

    }

    /**
	 * There are no payment fields for activa, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } 

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$ID = $this->settings['merchant_id'];
		$Password = $this->settings['merchant_key'];
		$Action = $this->settings['capture_action'];
		$Amt = $order->order_total;

		if ( $this->settings['testmode'] == 'yes' ){
			$ID = "89910582";
			$Password = "JvrJqy37xd";
	   	}

		$ResponseURL = $this->response_url;
		$ErrorURL = $this->response_url;

		var_dump($ResponseURL);
		var_dump($ErrorURL);

		$TrackId = $order->order_key;

		$DataToSend ="id=$ID&password=$Password&action=$Action&amt=$Amt&currencycode=978&langid=SLO&responseURL=$ResponseURL&errorURL=$ErrorURL&trackid=$TrackId&udf1=$order_id"; 

		$URL = $this->url;

		//Request URL for payment
		$ch=curl_init($URL);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch,CURLOPT_POST,1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$DataToSend);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1); 
		$varResponse=curl_exec($ch); 
		curl_close($ch);

		if (substr($varResponse,0,7) == '!ERROR!') {
			
			echo $varResponse;	
		
		} else {
			//Parse URL

			$varPosiz= strpos($varResponse, ':http');
			$varPaymentId= substr($varResponse,0,$varPosiz);
			$nc=strlen($varResponse);
			$nc=($nc-17);
			$varRedirectURL=substr($varResponse,$varPosiz+1);

			add_post_meta( $order->id, 'paymentid', $varPaymentId );

			//Generate payment URL
			$varRedirectURL ="$varRedirectURL?PaymentID=$varPaymentId";

			//Redirect to payment Gateway
			//echo "<meta http-equiv=\"refresh\" content=\"0;URL=$varRedirectURL\">";
		}

		return array(
			'result' 	=> 'success',
			'redirect'	=> $varRedirectURL//$order->get_checkout_payment_url( true )
		);

	}

	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', $woocommerce->version );
		define( 'PF_MODULE_NAME', 'WooCommerce-activa-Free' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'PF_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		// Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to activa', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-activa' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-activa' ) );

		// General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-activa' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-activa' ) );
		define( 'PF_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woocommerce-gateway-activa' ).
		    __( ' Transaction Notification when the payment status changes to', 'woocommerce-gateway-activa' ).
		    __( ' "Completed", or "Failed"', 'woocommerce-gateway-activa' ) );
	}

} // End Class

class response_callback extends WC_Payment_Gateway {
	public function __construct(){
		file_put_contents("payment.log", print_r($_POST,true), FILE_APPEND | LOCK_EX);

		file_put_contents("payment.log", print_r("------------------\n",true), FILE_APPEND | LOCK_EX);

		$this->successful_request($_POST);

		$PayID=$_POST["paymentid"];
		$TransID=$_POST["tranid"];
		$ResCode=$_POST["result"];
		$AutCode=$_POST["auth"];
		$PosDate=$_POST["postdate"];
		$TrckID=$_POST["trackid"];

		$UD1=$_POST["udf1"];
		$UD2=$_POST["udf2"];
		$UD3=$_POST["udf3"];
		$UD4=$_POST["udf4"];
		$UD5=$_POST["udf5"];
		if(isset($_POST["udf1"])){
			$order = new WC_Order( $UD1 );
		}elseif(isset($_POST["paymentid"])){
			$order = new WC_Order( $this->getOrderByPaymentId($PayID) );
		}

		if(!isset($order)){
			exit;
		}
		
		if(isset($_POST['Error'])){
			//$url = $order->get_cancel_order_url();
			$url = $this->get_return_url( $order );
			$order->update_status( 'failed', sprintf(__('Payment %s via ITN failed. Invalid credit card.', 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $PayID ) ) ) );
			$order->add_order_note( __( 'Invalid credit card!', 'woocommerce-gateway-activa' ) );
		}else{
			$url = $this->get_return_url( $order );	
		}

		//$url = $this->get_return_url( $order );

		echo "REDIRECT=".$url;
		exit;
	}

	function getOrderByPaymentId( $paymentid){
		global $wpdb;

		// find list of states in DB
		$qry = "SELECT post_id FROM wp_postmeta WHERE meta_key='paymentid' AND meta_value = " . $paymentid;
		$order_id = $wpdb->get_results( $qry );

		return $order_id[0]->post_id;
	}

	function successful_request( $posted ) {
		if ( ! isset( $posted['udf1'] ) && ! is_numeric( $posted['udf1'] ) ) { return false; }

		$order_id = (int) $posted['udf1'];
		$order_key = esc_attr( $posted['trackid'] );
		$order = new WC_Order( $order_id );

		if ( $order->order_key !== $order_key ) { exit; }

		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions
			switch ( strtoupper( $posted['result'] ) ) {
				case 'CAPTURED' :
					// Payment completed
					$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-activa' ) );
					$order->payment_complete();
					break;
				case 'APPROVED' :
					// Payment completed
					$order->add_order_note( __( 'ITN payment approved!', 'woocommerce-gateway-activa' ) );
					break;
				case 'NOT APPROVED' :
					$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $posted['result'] ) ) ) );
					break;
				case 'NOT CAPTURED' :
					$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $posted['result'] ) ) ) );
					break;
				case 'DENIED BY RISK' :
					// Failed order
					$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $posted['result'] ) ) ) );
					break;
				case 'HOST TIMEOUT' :
					$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $posted['result'] ) ) ) );
					break;
				default:
					// Hold order
					$order->update_status( 'on-hold', sprintf(__('Payment %s via ITN.'.$posted['result'], 'woocommerce-gateway-activa' ), strtolower( sanitize_text_field( $posted['result'] ) ) ) );
					break;
			} // End SWITCH Statement
		}
	}
}