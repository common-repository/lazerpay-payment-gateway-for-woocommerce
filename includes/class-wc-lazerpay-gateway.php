<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Lazerpay_Gateway
 */
class WC_Lazerpay_Gateway extends WC_Payment_Gateway {

	/**
	 * Checkout page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Checkout page description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Is gateway enabled?
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $test_mode;

	/**
	 * Should orders be marked as complete after payment?
	 *
	 * @var bool
	 */
	public $autocomplete_order;

	/**
	 * Lazerpay test public key.
	 *
	 * @var string
	 */
	public $test_public_key;

	/**
	 * Lazerpay test secret key.
	 *
	 * @var string
	 */
	public $test_secret_key;

	/**
	 * Lazerpay live public key.
	 *
	 * @var string
	 */
	public $live_public_key;

	/**
	 * Lazerpay live secret key.
	 *
	 * @var string
	 */
	public $live_secret_key;

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	public $secret_key;

	public $msg;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'lazerpay';
		$this->method_title       = __( 'Lazerpay', 'lazerpay-payment-gateway-for-woocommerce' );
		$this->method_description = sprintf( 'Lazerpay allows you to receive crypto payments from your customers easily. <a href="%1$s" target="_blank">Sign up</a> for a Lazerpay account, and <a href="%2$s" target="_blank">get your API keys</a>.', 'https://lazerpay.finance', 'https://dashboard.lazerpay.finance/settings' );

		$this->has_fields = true;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->test_mode          = $this->get_option( 'test_mode' ) === 'yes';
		$this->autocomplete_order = $this->get_option( 'autocomplete_order' ) === 'yes';

		$this->test_public_key = $this->get_option( 'test_public_key' );
		$this->test_secret_key = $this->get_option( 'test_secret_key' );

		$this->live_public_key = $this->get_option( 'live_public_key' );
		$this->live_secret_key = $this->get_option( 'live_secret_key' );

		$this->public_key = $this->test_mode ? $this->test_public_key : $this->live_public_key;
		$this->secret_key = $this->test_mode ? $this->test_secret_key : $this->live_secret_key;

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

		// Payment listener/API hook.
		add_action( 'woocommerce_api_wc_lazerpay_gateway', array( $this, 'verify_lazerpay_payment' ) );

		// Webhook listener/API hook.
		add_action( 'woocommerce_api_wc_lazerpay_webhook', array( $this, 'process_webhook' ) );

		// Check if the gateway can be used.
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = false;
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 */
	public function is_valid_for_use() {

		if ( ! in_array( strtoupper(get_woocommerce_currency() ), apply_filters( 'woocommerce_lazerpay_supported_currencies', array( 'NGN', 'USD', 'AED', 'GBP', 'EUR' ) ) ) ) {

			/* translators: %s: URL to WooCommerce general settings page */
			$this->msg = sprintf( __( 'Lazerpay does not support your store currency. Kindly set it to either AED, EUR (€), GBP (£), NGN (&#8358) or USD ($) <a href="%s">here</a>.', 'lazerpay-payment-gateway-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=general' ) );

			return false;

		}

		return true;

	}

	/**
	 * Display the payment icon on the checkout page
	 */
	public function get_icon() {

		$icon = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/pay-with-lazerpay.png', TBZ_WC_LAZERPAY_MAIN_FILE ) ) . '" alt="Pay with Lazerpay" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}

	/**
	 * Check if Lazerpay merchant details is filled
	 */
	public function admin_notices() {

		if ( 'no' === $this->enabled ) {
			return;
		}

		// Check required fields.
		if ( ! ( $this->public_key && $this->secret_key ) ) {
			/* translators: %s: Lazerpay WooCommerce payment gateway settings page */
			echo '<div class="error"><p>' . sprintf( __( 'Please enter your Lazerpay merchant details <a href="%s">here</a> to be able to accept payment via Lazerpay on your WooCommerce store.', 'lazerpay-payment-gateway-for-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=lazerpay' ) ) . '</p></div>';
		}
	}

	/**
	 * Check if Lazerpay gateway is enabled.
	 */
	public function is_available() {

		if ( 'yes' === $this->enabled ) {

			if ( ! ( $this->public_key && $this->secret_key ) ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Admin Panel Options
	 */
	public function admin_options() {

		?>

		<h2><?php _e( 'Lazerpay', 'lazerpay-payment-gateway-for-woocommerce' ); ?></h2>

		<h4>
			<strong>
				<?php
				/* translators: 1: URL to Lazerpay developers settings page, 2: Lazerpay WooCommerce payment gateway webhool URL. */
				printf( __( 'Required: To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="%1$s" target="_blank" rel="noopener noreferrer">here</a> to the URL below<span style="color: red"><pre><code>%2$s</code></pre></span>', 'lazerpay-payment-gateway-for-woocommerce' ), 'https://dashboard.lazerpay.finance/settings', strtolower( WC()->api_request_url( 'WC_Lazerpay_Webhook' ) ) );
				?>
			</strong>
		</h4>

		<?php

		if ( $this->is_valid_for_use() ) {

			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';

		} else {
			?>

			<div class="inline error">
				<p>
					<strong><?php _e( 'Lazerpay Payment Gateway Disabled:', 'lazerpay-payment-gateway-for-woocommerce' ); ?></strong>
					<?php echo wp_kses( $this->msg, array( 'a' => array( 'href' => array() ) ) ); ?>
				</p>
			</div>

			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'lazerpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Enable Lazerpay', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Lazerpay as a payment option on the checkout page.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'title'              => array(
				'title'       => __( 'Title', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the payment method title which the user sees during checkout.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Lazerpay', 'lazerpay-payment-gateway-for-woocommerce' ),
			),
			'description'        => array(
				'title'       => __( 'Description', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the payment method description which the user sees during checkout.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Make crypto payments easily.', 'lazerpay-payment-gateway-for-woocommerce' ),
			),
			'test_mode'          => array(
				'title'       => __( 'Test mode', 'lazerpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live. <br />Once you are live uncheck this.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_public_key'    => array(
				'title'       => __( 'Test Public Key', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Test Public Key here.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_secret_key'    => array(
				'title'       => __( 'Test Secret Key', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Test Secret Key here', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_public_key'    => array(
				'title'       => __( 'Live Public Key', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Required: Enter your Live Public Key here.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'live_secret_key'    => array(
				'title'       => __( 'Live Secret Key', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Required: Enter your Live Secret Key here.', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'autocomplete_order' => array(
				'title'       => __( 'Autocomplete Order After Payment', 'lazerpay-payment-gateway-for-woocommerce' ),
				'label'       => __( 'Autocomplete Order', 'lazerpay-payment-gateway-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, the order will be marked as complete after successful payment', 'lazerpay-payment-gateway-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

	}

	/**
	 * Outputs scripts used by Lazerpay.
	 */
	public function payment_scripts() {

		if ( isset( $_GET['pay_for_order'] ) || ! is_checkout_pay_page() ) {
			return;
		}

		if ( 'no' === $this->enabled ) {
			return;
		}

		$order_key = urldecode( sanitize_text_field( $_GET['key'] ) );
		$order_id  = absint( get_query_var( 'order-pay' ) );

		$order = wc_get_order( $order_id );

		$payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

		if ( $this->id !== $payment_method ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'lazerpay', 'https://js.lazerpay.finance/v1/index.min.js', array( 'jquery' ), TBZ_WC_LAZERPAY_VERSION );
		wp_enqueue_script( 'lazerpay-wc', plugins_url( 'assets/js/lazerpay' . $suffix . '.js', TBZ_WC_LAZERPAY_MAIN_FILE ), array( 'jquery', 'lazerpay' ), TBZ_WC_LAZERPAY_VERSION );

		$lazerpay_params = array(
			'public_key' => $this->public_key,
		);

		if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			$email      = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
			$first_name = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
			$last_name  = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
			$name       = trim( $first_name . ' ' . $last_name );

			$amount = $order->get_total();

			$txnref = 'WC|' . $order_id . '|' . time();

			$the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
			$the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			if ( absint( $the_order_id ) === $order_id && $the_order_key === $order_key ) {

				$lazerpay_params['reference']      = $txnref;
				$lazerpay_params['amount']         = $amount;
				$lazerpay_params['currency']       = $order->get_currency();
				$lazerpay_params['customer_email'] = $email;
				$lazerpay_params['customer_name']  = $name;
				$lazerpay_params['order_id']       = $order_id;
				$lazerpay_params['order_status']   = $order->get_status();

				$order->add_meta_data( '_lazerpay_txn_ref', $txnref, true );
				$order->save();
			}
		}

		wp_localize_script( 'lazerpay-wc', 'tbz_wc_lazerpay_params', $lazerpay_params );

	}

	/**
	 * Load admin scripts
	 */
	public function admin_scripts() {

		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'tbz_wc_lazerpay_admin', plugins_url( 'assets/js/lazerpay-admin' . $suffix . '.js', TBZ_WC_LAZERPAY_MAIN_FILE ), array(), TBZ_WC_LAZERPAY_VERSION, true );

	}
	
	/**
	 * Process payment
	 *
	 * @param int $order_id WC Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Displays the payment page
	 */
	public function receipt_page( $order_id ) {

		$order = wc_get_order( $order_id );

		echo '<div id="wc-lazerpay-form">';

		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Lazerpay.', 'lazerpay-payment-gateway-for-woocommerce' ) . '</p>';

		echo '<div id="tbz_wc_lazerpay_form"><form id="order_review" method="post" action="' . strtolower( esc_url( WC()->api_request_url( 'WC_Lazerpay_Gateway' ) ) ) . '"></form><button class="button alt" id="wc-lazerpay-payment-button">' . __( 'Pay Now', 'lazerpay-payment-gateway-for-woocommerce' ) . '</button>';
		echo ' <a class="button cancel" id="lazerpay-cancel-payment-button" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'lazerpay-payment-gateway-for-woocommerce' ) . '</a></div>';


		echo '</div>';
	}

	/**
	 * Verify Lazerpay payment
	 */
	public function verify_lazerpay_payment() {

		@ob_clean();

		if ( isset( $_REQUEST['tbz_wc_lazerpay_txn_ref'] ) ) {
			$txn_ref = sanitize_text_field( $_REQUEST['tbz_wc_lazerpay_txn_ref'] );
		} else {
			$txn_ref = false;
		}

		if ( false === $txn_ref ) {
			wp_redirect( wc_get_page_permalink( 'checkout' ) );
			exit;
		}

		$lazerpay_txn = $this->verify_transaction( $txn_ref );

		if ( false === $lazerpay_txn ) {
			wc_add_notice( 'error', __( 'Unable to verify payment at the moment. Contact us for more information about your order.', 'lazerpay-payment-gateway-for-woocommerce' ) );
			wp_redirect( wc_get_page_permalink( 'checkout' ) );
			exit;
		}

		$transaction_status = strtolower( $lazerpay_txn->data->status );

		if ( in_array( $transaction_status, array( 'confirmed', 'incomplete' ), true ) ) {

			$order_details = explode( '|', $lazerpay_txn->data->reference );
			$order_id      = (int) $order_details[1];
			$order         = wc_get_order( $order_id );

			if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			}

			$order_total      = $order->get_total();
			$fiat_amount      = $lazerpay_txn->data->fiatAmount;
			$lazerpay_txn_ref = $lazerpay_txn->data->reference;

			// check if the fiat amount is less than the order amount.
			if ( 'incomplete' === $transaction_status || $fiat_amount < $order_total) {

				$order->update_status( 'on-hold' );

				if ( method_exists( $order, 'set_transaction_id') ) {
					$order->set_transaction_id( $lazerpay_txn_ref );
					$order->save();
				}

				/* translators: 1: Line break, 2: Line break. */
				$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment transaction was successful but it was underpaid.%2$sYour order is on-hold.', 'lazerpay-payment-gateway-for-woocommerce' ), '<br />', '<br />' );
				$notice_type = 'notice';

				// Add Customer Order Note
				$order->add_order_note( $notice, 1 );

				// Add Admin Order Note
				/* translators: 1: Line break, 2: Lazerpay transaction reference. */
				$admin_order_note = sprintf( __( '<strong>Order is on-hold.</strong>%1$sReason: It was underpaid. Lazerpay Transaction Reference:</strong> %2$s', 'lazerpay-payment-gateway-for-woocommerce' ), '<br />', $lazerpay_txn_ref );
				$order->add_order_note( $admin_order_note );

				wc_add_notice( $notice, $notice_type );

				wp_redirect( $order->get_cancel_order_url( wc_get_checkout_url() ) );
				exit;

			}

			$order->payment_complete( $lazerpay_txn_ref );

			/* translators: %s: Lazerpay transaction reference. */
			$order->add_order_note( sprintf( __( 'Payment via Lazerpay successful (Transaction Reference: %s)', 'lazerpay-payment-gateway-for-woocommerce' ), $lazerpay_txn_ref ) );

			if ( $this->autocomplete_order ) {
				$order->update_status( 'completed' );
			}

			WC()->cart->empty_cart();

		} else {

			$order_details = explode( '|', $lazerpay_txn->data->reference );
			$order_id      = (int) $order_details[1];
			$order         = wc_get_order( $order_id );
			$order->update_status( 'failed', __( 'Lazerpay payment failed.', 'lazerpay-payment-gateway-for-woocommerce' ) );

			wp_redirect( wc_get_checkout_url() );
			exit;

		}

		wp_redirect( $this->get_return_url( $order ) );

		exit;
	}

	/**
	 * Process Webhook
	 */
	public function process_webhook() {

		if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) ) {
			exit;
		}

		sleep( 5 );

		$body = @file_get_contents( 'php://input' );

		$webhook_body = json_decode( $body, true );

		if ( empty( $webhook_body['webhookType'] || 'DEPOSIT_TRANSACTION' !== strtoupper( $webhook_body['webhookType'] ) ) ) {
			exit;
		}

		if ( ! isset( $webhook_body['reference'] ) ) {
			exit;
		}

		$lazerpay_txn = $this->verify_transaction( $webhook_body['reference'] );

		if ( false === $lazerpay_txn ) {
			exit;
		}

		$transaction_status = strtolower( $lazerpay_txn->data->status );

		if ( ! in_array( $transaction_status, array( 'confirmed', 'incomplete' ), true ) ) {
			exit;
		}

		$gateway_txn_ref  = $lazerpay_txn->data->reference;

		$order_details = explode( '|', $gateway_txn_ref );

		$order_id = (int) $order_details[1];

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			exit;
		}

		$order_txn_ref = $order->get_meta( '_lazerpay_txn_ref' );

		if ( $gateway_txn_ref !== $order_txn_ref ) {
			exit;
		}

		http_response_code( 200 );

		if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
			exit;
		}

		$order_total = $order->get_total();
		$fiat_amount = $lazerpay_txn->data->fiatAmount;

		// check if the fiat amount is less than the order amount.
		if ( 'incomplete' === $transaction_status || $fiat_amount < $order_total ) {

			$order->update_status( 'on-hold' );

			if ( method_exists( $order, 'set_transaction_id' ) ) {
				$order->set_transaction_id( $gateway_txn_ref );
				$order->save();
			}

			/* translators: 1: Line break, 2: Line break. */
			$notice      = sprintf( __( 'Thank you for shopping with us.%1$sYour payment transaction was successful but it was underpaid.%2$sYour order is on-hold.', 'lazerpay-payment-gateway-for-woocommerce' ), '<br />', '<br />' );
			$notice_type = 'notice';

			// Add Customer Order Note.
			$order->add_order_note( $notice, 1 );

			// Add Admin Order Note.
			$admin_order_note = sprintf( __( '<strong>Order is on-hold.</strong>%1$sReason: It was underpaid. Lazerpay Transaction Reference:</strong> %2$s', 'lazerpay-payment-gateway-for-woocommerce' ), '<br />', $gateway_txn_ref );
			$order->add_order_note( $admin_order_note );

			wc_add_notice( $notice, $notice_type );

		} else {

			$order->payment_complete( $gateway_txn_ref );

			$order->add_order_note( sprintf( __( 'Payment via Lazerpay successful (Transaction Reference: %s)', 'lazerpay-payment-gateway-for-woocommerce' ), $gateway_txn_ref ) );

			WC()->cart->empty_cart();

			if ( $this->autocomplete_order ) {
				$order->update_status( 'completed' );
			}
		}

		wc_empty_cart();

		exit;
	}

	private function verify_transaction( $txn_id ) {

		$api_url = "https://api.lazerpay.engineering/api/v1/transaction/verify/$txn_id";

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->secret_key,
			'X-api-key'     => $this->public_key,
		);

		$args = array(
			'headers' => $headers,
			'timeout' => 60,
		);

		$request = wp_remote_get( $api_url, $args );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			return json_decode( wp_remote_retrieve_body( $request ) );
		}

		return false;
	}
}
