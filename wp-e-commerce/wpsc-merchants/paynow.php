<?php

/**
 * Plugin Name: WP eCommerce Netcash Pay Now
 * Plugin URI: https://netcash.co.za/
 * Description: A payment gateway for South African payment system, Netcash Pay Now.
 * Version: 2.0
 * Author: Netcash
 * Author URI: https://netcash.co.za/
**/

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Set gateway variables for WP e-Commerce
$nzshpcrt_gateways[$num]['name'] = 'Netcash Pay Now';
$nzshpcrt_gateways[$num]['internalname'] = 'netcash_paynow';
$nzshpcrt_gateways[$num]['function'] = 'gateway_netcash_paynow';
$nzshpcrt_gateways[$num]['form'] = "form_netcash_paynow";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_netcash_paynow";
$nzshpcrt_gateways[$num]['payment_type'] = "netcash_paynow";
$nzshpcrt_gateways[$num]['supported_currencies']['currency_list'] = array( 'ZAR' );
$nzshpcrt_gateways[$num]['supported_currencies']['option_name'] = 'netcash_paynow_currcode';

// Include the Netcash Pay Now common file
require_once( 'paynow_common.inc' );
require_once(dirname(__FILE__).'/PayNowValidator.php');

function pn_flash_error_notice($message = '') {
	$_SESSION['pn_error'] = "{$message} Please contact your Netcash Pay Now Account manager on 0861 338 338 for assistance.";
}


/**
 * gateway_netcash_paynow()
 *
 * Create the form/information which is submitted to the gateway.
 *
 * @param mixed $sep Separator
 * @param mixed $sessionid Session ID for user session
 * @return
 */
function gateway_netcash_paynow( $sep, $sessionid )
{
	// Variable declaration
	global $wpdb, $wpsc_cart;
	$pnAmount = 0;
	$pnDescription = '';
	$pnOutput = '';

	// Get purchase log
	$sql =
		"SELECT *
		FROM `". WPSC_TABLE_PURCHASE_LOGS ."`
		WHERE `sessionid` = ". $sessionid ."
		LIMIT 1";
	$purchase = $wpdb->get_row( $sql, ARRAY_A ) ;

	if( $purchase['totalprice'] == 0 )
	{
		header( "Location: ". get_option( 'transact_url' ) . $sep ."sessionid=". $sessionid );
		exit();
	}

	// Get cart contents
	$sql =
		"SELECT *
		FROM `". WPSC_TABLE_CART_CONTENTS ."`
		WHERE `purchaseid` = '". $purchase['id'] ."'";
	$cart = $wpdb->get_results( $sql, ARRAY_A ) ;

	// Lookup the currency codes and local price
	$sql =
		"SELECT `code`
		FROM `". WPSC_TABLE_CURRENCY_LIST ."`
		WHERE `id` = '". get_option( 'currency_type' ) ."'
		LIMIT 1";
	$local_curr_code = $wpdb->get_var( $sql );
	$pn_curr_code = get_option( 'netcash_paynow_currcode' );

	// Set default currency
	if( $pn_curr_code == '' )
		$pn_curr_code = 'ZAR';

	// Convert from the currency of the users shopping cart to the currency
	// which the user has specified in their Netcash Pay Now preferences.
	$curr = new CURRENCYCONVERTER();

	$total = $wpsc_cart->calculate_total_price();
	$discount = $wpsc_cart->coupons_amount;

	// If Netcash Pay Now currency differs from local currency
	if( $pn_curr_code != $local_curr_code )
	{
		pnlog( 'Currency conversion required' );
		$pnAmount = $curr->convert( $total, $pn_curr_code, $local_curr_code );
	}
	// Else, if currencies are the same
	else
	{
		pnlog( 'NO Currency conversion required' );
		$pnAmount = $total;

		// Create description from cart contents
		foreach( $cart as $cartItem )
		{
			$itemPrice = round( $cartItem['price'] * ( 100 + $cartItem['gst'] ) / 100, 2 );
			$itemShippingPrice = $cartItem['pnp'];    // No tax charged on shipping
			$itemPriceTotal = ( $itemPrice + $itemShippingPrice ) * $cartItem['quantity'] ;
			$pnDescription .= $cartItem['quantity'] .' x '. $cartItem['name'] .
				' @ '. number_format( $itemPrice, 2, '.', ',' ) .'ea'.
				' (Shipping = '. number_format( $itemShippingPrice, 2, '.', ',' ) .'ea)'.
				' = '. number_format( $itemPriceTotal, 2, '.', ',' ) .';';
		}

		$pnDescription .= ' Base shipping = '. number_format( $purchase['base_shipping'], 2, '.', ',' ) .';';

		if( $discount > 0 )
			$pnDescription .= ' Discount = '. number_format( $discount, 2, '.', ',' ) .';';

		$pnDescription .= ' Total = '. number_format( $pnAmount, 2, '.', ',' );
	}

	$serviceKey = get_option( 'netcash_paynow_service_key' );
	$netcash_paynow_url = 'https://paynow.netcash.co.za/site/paynow.aspx';

	// Create URLs
	$returnUrl = get_option( 'transact_url' ) . $sep ."sessionid=". $sessionid ."&gateway=netcash_paynow";
	$cancelUrl = get_option( 'transact_url' );
	$notifyUrl = get_option( 'siteurl' ) .'/?ipn_request=true';

	// Buyer details
	if( !empty( $_POST['collected_data'][get_option('netcash_paynow_form_name_first')] ) )
		$data['name_first'] = $_POST['collected_data'][get_option('netcash_paynow_form_name_first')];

	if( !empty( $_POST['collected_data'][get_option('netcash_paynow_form_name_last')] ) )
		$data['name_last'] = $_POST['collected_data'][get_option('netcash_paynow_form_name_last')];

	global $user_ID;

	$customerName = "{$data['name_first']} {$data['name_last']}";
	$orderID = $purchase['id'];
	$customerID = $user_ID;
	$netcashGUID = "08693ef6-d77c-4504-976b-ea54be1f68d1";

	// Construct variables for post
	$data = array(
		// Merchant details
		'm1' => $serviceKey,
		'm2' => $netcashGUID,
		// 'return_url' => $returnUrl,
		// 'cancel_url' => $cancelUrl,
		// 'notify_url' => $notifyUrl,

		// Item details
		'p2' => 'Order #'. $purchase['id'] . '-' . date('ymdhis'),
		// 'p3' => get_option( 'blogname' ) .' purchase, Order #'. $purchase['id'],
		// 'p3' => $pnDescription,
		'p4' => number_format( sprintf( "%01.2f", $pnAmount ), 2, '.', '' ),
		// 'm4' => $purchase['id'],
		// 'currency_code' => $pn_curr_code,
		'm5' => $sessionid,

		'p3' => "{$customerName} | {$orderID}",
		'm3' => "$netcashGUID",
		'm4' => "{$customerID}",
		'm14' => "1",

		// Other details
		// 'm6' => PN_USER_AGENT,
		// 'm10' => 'ipn_request=true'
		);

	$email_data = $wpdb->get_results(
		"SELECT `id`, `type`
		FROM `".WPSC_TABLE_CHECKOUT_FORMS."`
		WHERE `type` IN ('email') AND `active` = '1'", ARRAY_A );

	foreach( (array)$email_data as $email )
		$data['email_address'] = $_POST['collected_data'][$email['id']];

	if( !empty( $_POST['collected_data'][get_option('email_form_field')] ) && ( $data['email'] == null ) )
		$data['email_address'] = $_POST['collected_data'][get_option('email_form_field')];

	// Create output string
	foreach( $data as $key => $val )
		$pnOutput .= $key .'='. urlencode( $val ) .'&';

	// Remove last ampersand
	$pnOutput = substr( $pnOutput, 0, -1 );

	// Display debugging information (if in debug mode)
	if( PN_DEBUG || ( defined( 'WPSC_ADD_DEBUG_PAGE' ) && ( WPSC_ADD_DEBUG_PAGE == true ) ) )
	{
		echo "<a href='". $netcash_paynow_url ."?". $pnOutput ."'>Test the URL here</a>";
		echo "<pre>". print_r( $data, true ) ."</pre>";
		exit();
	}

	// Send to Netcash Pay Now (GET)
	header( "Location: ". $netcash_paynow_url ."?". $pnOutput );
	exit();
}

/**
 * pn_netcash_paynow_ipn()
 *
 * Handle IPN callback from Netcash Pay Now
 *
 * @return
 */
function pn_netcash_paynow_ipn()
{
	// Check if this is an IPN request
	// Has to be done like this (as opposed to "exit" as processing needs
	// to continue after this check.
	// if( ( isset($_GET['ipn_request']) && $_GET['ipn_request'] == 'true' ) ) {
		// Variable Initialization
		global $wpdb;
		$pnError = false;
		$pnErrMsg = '';
		$pnDone = false;
		$pnData = array();
		$pnHost = 'https://paynow.netcash.co.za/site/paynow.aspx';

		$posted_id = $_POST['Reference']; //"Order #8-150819114657";
		$pnOrderId = str_ireplace("Order #", "", $posted_id);
		$pnOrderId = preg_replace("/-\d{12}/", "", $pnOrderId);
		$pnParamString = '';

		// Set debug email address
		if( get_option( 'netcash_paynow_debug_email' ) != '' )
			$pnDebugEmail = get_option( 'netcash_paynow_debug_email' );
		elseif( get_option( 'purch_log_email' ) != '' )
			$pnDebugEmail = get_option( 'purch_log_email' );
		else
			$pnDebugEmail = get_option( 'admin_email' );

		pnlog( 'Netcash Pay Now IPN call received' );

		//// Notify Netcash Pay Now that information has been received
		if( !$pnError && !$pnDone )
		{
			header( 'HTTP/1.0 200 OK' );
			flush();
		}

		//// Get data sent by Netcash Pay Now
		if( !$pnError && !$pnDone )
		{
			pnlog( 'Get posted data' );

			// Posted variables from IPN
			$pnData = pnGetData();

			pnlog( 'Netcash Pay Now Data: '. print_r( $pnData, true ) );

			if( $pnData === false )
			{
				$pnError = true;
				$pnErrMsg = PN_ERR_BAD_ACCESS;
			}
		}

		// Get internal order and verify it hasn't already been processed
		if( !$pnError && !$pnDone )
		{
			// Get order data
			$sql =
				"SELECT * FROM `". WPSC_TABLE_PURCHASE_LOGS ."`
				WHERE `id` = ". $pnOrderId ."
				LIMIT 1";
			$purchase = $wpdb->get_row( $sql, ARRAY_A );

			pnlog( "Purchase:\n". print_r( $purchase, true )  );

			// Check if order has already been processed
			// It has been "processed" if it has a status above "Order Received"
			if( $purchase['processed'] > get_option( 'netcash_paynow_pending_status' ) )
			{
				pnlog( "Order has already been processed" );
				$pnDone = true;
			}
		}

		// Check data against internal order
		if( !$pnError && !$pnDone )
		{
			pnlog( 'Check data against internal order' );


			// Lookup the currency codes and local price
			$sql =
				"SELECT `code`
				FROM `". WPSC_TABLE_CURRENCY_LIST ."`
				WHERE `id` = '". get_option( 'currency_type' ) ."'
				LIMIT 1";
			$local_curr_code = $wpdb->get_var( $sql );
			$pn_curr_code = get_option( 'netcash_paynow_currcode' );

			// Set default currency
			if( $pn_curr_code == '' )
				$pn_curr_code = 'ZAR';

			// Convert from the currency of the users shopping cart to the currency
			// which the user has specified in their Netcash Pay Now preferences.
			$curr = new CURRENCYCONVERTER();

			// If Netcash Pay Now currency differs from local currency
			if( $pn_curr_code != $local_curr_code )
			{
				$compare_price = $curr->convert( $purchase['totalprice'], $pn_curr_code, $local_curr_code );
				pnlog( 'IPN: Currency conversion required ' . $compare_price );
			} else {
				pnlog( 'IPN: Currency conversion NOT required' );
				$compare_price = $purchase['totalprice'];
			}

			// pnlog( "IPN: POSTED price {$pnData['Amount']} | COMPARE price {$compare_price} | TOTAL price {$purchase['totalprice']}" );

			// Check order amount
			if( !pnAmountsEqual( $pnData['Amount'], $compare_price ) )
			{
				$pnError = true;
				pnlog( "Received amount: {$pnData['Amount']} | Actual amount: {$compare_price}" );
				$pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
			}
			// Check session ID
			// elseif( strcasecmp( $pnData['Extra2'], $purchase['sessionid'] ) != 0 )
			// {
			//     $pnError = true;
			//     $pnErrMsg = PN_ERR_SESSIONID_MISMATCH;
			// }
		}

		//// Check status and update order
		if( !$pnError && !$pnDone )
		{
			pnlog( 'Check status and update order' );

			$sessionid = $pnData['Extra2'];
			$transaction_id = $pnData['RequestTrace'];
			$vendor_name = get_option( 'blogname');
			$vendor_url = get_option( 'siteurl');

			switch( $pnData['TransactionAccepted'] )
			{
				case 'true':
					pnlog( '- Complete' );

					// Update the purchase status
					$data = array(
						'processed' => get_option( 'netcash_paynow_complete_status'),
						'transactid' => $transaction_id,
						'date' => time(),
					);

					wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
					transaction_results( $sessionid, false, $transaction_id );
					$admin_email = get_option('admin_email');

						$subject = "Netcash Pay Now IPN on your site";
						$body =
							"Hi,\n\n".
							"A Netcash Pay Now transaction has been completed on your website\n".
							"------------------------------------------------------------\n".
							"Site: ". $vendor_name ." (". $vendor_url .")\n".
							"Purchase ID: ". $pnOrderId ."\n".
							"Netcash Pay Now Transaction ID: ". $pnData['RequestTrace'] ."\n".
							"Netcash Pay Now Payment Status: ". $pnData['TransactionAccepted'] ."\n"."";
							// "Order Status Code: ". $d['order_status'];

					if(get_option('netcash_paynow_email_admin_on_success') == 1)
					{
						mail( get_option('netcash_paynow_success_email'), $subject, $body );
					}
					if( PN_DEBUG )
					{
						mail( $pnDebugEmail, $subject, $body );
					}
					break;

				case 'false':
					pnlog( '- Failed' );

					// If payment fails, delete the purchase log
					$sql =
						"SELECT * FROM `". WPSC_TABLE_CART_CONTENTS ."`
						WHERE `purchaseid`='". $pnOrderId ."'";
					$cart_content = $wpdb->get_results( $sql, ARRAY_A );
					foreach( (array)$cart_content as $cart_item )
						$cart_item_variations = $wpdb->query(
							"DELETE FROM `". WPSC_TABLE_CART_ITEM_VARIATIONS ."`
							WHERE `cart_id` = '". $cart_item['id'] ."'", ARRAY_A );

					$wpdb->query( "DELETE FROM `". WPSC_TABLE_CART_CONTENTS ."` WHERE `purchaseid`='". $pnOrderId ."'" );
					$wpdb->query( "DELETE FROM `". WPSC_TABLE_SUBMITED_FORM_DATA ."` WHERE `log_id` IN ('". $pnOrderId ."')" );
					$wpdb->query( "DELETE FROM `". WPSC_TABLE_PURCHASE_LOGS ."` WHERE `id`='". $pnOrderId ."' LIMIT 1" );

					$subject = "Netcash Pay Now IPN Transaction on your site";
					$body =
						"Hi,\n\n".
						"A failed Netcash Pay Now transaction on your website requires attention\n".
						"------------------------------------------------------------\n".
						"Site: ". $vendor_name ." (". $vendor_url .")\n".
						"Purchase ID: ". $purchase['id'] ."\n".
						"User ID: ". $purchase['user_ID'] ."\n".
						"Netcash Pay Now Transaction ID: ". $pnData['RequestTrace'] ."\n".
						"Netcash Pay Now Payment Status: ". $pnData['TransactionAccepted'];
					mail( $pnDebugEmail, $subject, $body );
					break;

				case 'PENDING':
					pnlog( '- Pending' );

					// Need to wait for "Completed" before processing
					$sql =
						"UPDATE `". WPSC_TABLE_PURCHASE_LOGS ."`
						SET `transactid` = '". $transaction_id ."', `date` = '". time() ."'
						WHERE `sessionid` = ". $sessionid ."
						LIMIT 1";
					$wpdb->query($sql) ;
					break;

				default:
					// If unknown status, do nothing (safest course of action)
				break;
			}
		}


		// If an error occurred
		if( $pnError )
		{
			pnlog( 'Error occurred: '. $pnErrMsg );
			pnlog( 'Sending email notification' );

			 // Send an email
			$subject = "Netcash Pay Now IPN error: ". $pnErrMsg;
			$body =
				"Hi,\n\n".
				"An invalid Netcash Pay Now transaction on your website requires attention\n".
				"----------------------------------------------------------------------\n".
				"Site: ". $vendor_name ." (". $vendor_url .")\n".
				"Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
				"Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
				"Purchase ID: ". $purchase['id'] ."\n".
				"User ID: ". $purchase['user_ID'] ."\n";
			if( isset( $pnData['RequestTrace'] ) )
				$body .= "Netcash Pay Now Transaction ID: ". $pnData['RequestTrace'] ."\n";
			if( isset( $pnData['payment_status'] ) )
				$body .= "Netcash Pay Now Payment Status: ". $pnData['TransactionAccepted'] ."\n";
			$body .=
				"\nError: ". $pnErrMsg ."\n";

			switch( $pnErrMsg )
			{
				case PN_ERR_AMOUNT_MISMATCH:
					$body .=
						"Value received : ". $pnData['Amount'] ."\n".
						"Value should be: ". $purchase['totalprice'];
					break;

				case PN_ERR_ORDER_ID_MISMATCH:
					$body .=
						"Value received : ". $pnOrderId ."\n".
						"Value should be: ". $purchase['id'];
					break;

				case PN_ERR_SESSION_ID_MISMATCH:
					$body .=
						"Value received : ". $pnData['Extra2'] ."\n".
						"Value should be: ". $purchase['sessionid'];
					break;

				// For all other errors there is no need to add additional information
				default:
					break;
			}

			mail( $pnDebugEmail, $subject, $body );
		}

		// Close log
		// pnlog( '', true );
		return !$pnError;
		// exit();
	// }
}

/**
 * submit_netcash_paynow()
 *
 * Updates the options submitted by the config form (function "form_netcash_paynow"}
 *
 * @return
 */
function submit_netcash_paynow()
{

	$account_number = isset( $_POST['netcash_paynow_account_number'] ) ? $_POST['netcash_paynow_account_number'] : '';
	$service_key = isset( $_POST['netcash_paynow_service_key'] ) ? $_POST['netcash_paynow_service_key'] : '';

	if( $account_number && $service_key ) {
		$Validator = new netcash\PayNowValidator();
		$Validator->setVendorKey('7f7a86f8-5642-4595-8824-aa837fc584f2');

		try {
			$result = $Validator->validate_paynow_service_key($account_number, $service_key);
			if( $result !== true ) {

				pn_flash_error_notice((isset($result[$service_key]) ? $result[$service_key] : "<strong>Service Key</strong> {$result}"));
				return false;

			} else {
				// Success
				update_option( 'netcash_paynow_account_number', $account_number );
				update_option( 'netcash_paynow_service_key', $service_key );
			}
		} catch(\Exception $e) {
			pn_flash_error_notice($e->getMessage());
			return false;
		}
	} else {
		pn_flash_error_notice('Account number and service key required.');
		return false;
	}

	if( isset( $_POST['netcash_paynow_currcode'] ) && !empty( $_POST['netcash_paynow_currcode'] ) )
		update_option( 'netcash_paynow_currcode', $_POST['netcash_paynow_currcode'] );


	if( isset( $_POST['netcash_paynow_pending_status'] )  )
		update_option( 'netcash_paynow_pending_status', (int)$_POST['netcash_paynow_pending_status'] );

	if( isset( $_POST['netcash_paynow_complete_status'] )  )
		update_option( 'netcash_paynow_complete_status', (int)$_POST['netcash_paynow_complete_status'] );


	if( isset( $_POST['netcash_paynow_debug'] ) )
		update_option( 'netcash_paynow_debug', (int)$_POST['netcash_paynow_debug'] );

	if( isset( $_POST['netcash_paynow_debug_email'] ) )
		update_option( 'netcash_paynow_debug_email', $_POST['netcash_paynow_debug_email'] );

	 if( isset( $_POST['netcash_paynow_success_email'] ) )
		update_option( 'netcash_paynow_success_email', $_POST['netcash_paynow_success_email'] );

	if( isset( $_POST['netcash_paynow_email_admin_on_success'] ) )
		update_option( 'netcash_paynow_email_admin_on_success', $_POST['netcash_paynow_email_admin_on_success'] );

	foreach( (array)$_POST['netcash_paynow_form'] as $form => $value )
		update_option( ( 'netcash_paynow_form_'.$form ), $value );

	return( true );
}

/**
 * form_netcash_paynow()
 *
 * Displays the configuration form for the admin area.
 *
 * @return
 */
function form_netcash_paynow()
{
	// Variable declaration
	global $wpdb, $wpsc_gateways;

	// Set defaults
	$options = array();

	$options['account_number'] = ( get_option( 'netcash_paynow_account_number' ) != '' ) ?
		get_option( 'netcash_paynow_account_number' ) : '';

	$options['service_key'] = ( get_option( 'netcash_paynow_service_key' ) != '' ) ?
		get_option( 'netcash_paynow_service_key' ) : 'c668242b-2dd7-46c6-b153-3f572d531be';

	$options['pending_status'] = ( get_option( 'netcash_paynow_pending_status' ) != '' ) ?
		get_option( 'netcash_paynow_pending_status' ) : 1;
	$options['complete_status'] = ( get_option( 'netcash_paynow_complete_status' ) != '' ) ?
		get_option( 'netcash_paynow_complete_status' ) : 3;

	$options['debug'] = ( (int)get_option( 'netcash_paynow_debug' ) != '' ) ?
		get_option( 'netcash_paynow_debug' ) : 0;
	$options['debug_email'] = ( get_option( 'netcash_paynow_debug_email' ) != '' ) ?
		get_option( 'netcash_paynow_debug_email' ) : get_option('admin_email');

	$options['email_admin_on_success'] = ( get_option( 'netcash_paynow_email_admin_on_success' ) != '' ) ?
		get_option( 'netcash_paynow_email_admin_on_success' ) : 1;

	$options['success_email'] = ( get_option( 'netcash_paynow_success_email' ) != '' ) ?
		get_option( 'netcash_paynow_success_email' ) : get_option('admin_email');

	$options['form_name_first'] = ( get_option( 'netcash_paynow_form_name_first' ) != '' ) ?
		get_option( 'netcash_paynow_form_name_first' ) : 2;
	$options['form_name_last'] = ( get_option( 'netcash_paynow_form_name_last' ) != '' ) ?
		get_option( 'netcash_paynow_form_name_last' ) : 3;

	if (isset($_SESSION['pn_error'] )) { ?>
		<div class="error notice">
			<p class=""><?php echo $_SESSION['pn_error']; ?></p>
		</div>
		<?php unset($_SESSION['pn_error']); ?>
	<?php }

	// Generate output
	$output = '
		<tr>
		  <td>Account Number:</td>
		  <td>
			<input type="text" size="40" value="'. $options['account_number'] .'" name="netcash_paynow_account_number" /> <br />
		  </td>
		</tr>'."\n";

	$output .= '
		<tr>
		  <td colspan="2">
			<span  class="wpscsmall description">
			Please register with <a href="https://netcash.co.za/" target="_blank">Netcash Pay Now</a> to use this module. You will require a Pay Now Service Key which is available from your Netcash Connect -> Pay Now section on your Netcash Pay Now <a href="https://merchant.netcash.co.za/" target="_blank">Merchant Account</a>.
			</span>
		  </td>
		</tr>

		<tr>
		  <td>Service Key:</td>
		  <td>
			<input type="text" size="40" value="'. $options['service_key'] .'" name="netcash_paynow_service_key" /> <br />
		  </td>
		</tr>'."\n";

	// Get list of purchase statuses
	global $wpsc_purchlog_statuses;
	// pnlog( "Purchase Statuses:\n". print_r( $wpsc_purchlog_statuses, true ) );

	$output .= '
		<tr>
		  <td>Status for Pending Payments:</td>
		  <td>
			<select name="netcash_paynow_pending_status">';

	foreach( $wpsc_purchlog_statuses as $status )
		$output .= '<option value="'. $status['order'] .'" '. ( ( $status['order'] == $options['pending_status'] ) ? 'selected' : '' ) .'>'. $status['label'] .'</option>';

	$output .= '
			</select>
		  </td>
		</tr>
		<tr>
		  <td>Status for Successful Payments:</td>
		  <td>
			<select name="netcash_paynow_complete_status">';

	foreach( $wpsc_purchlog_statuses as $status )
		$output .= '<option value="'. $status['order'] .'" '. ( ( $status['order'] == $options['complete_status'] ) ? 'selected' : '' ) .'>'. $status['label'] .'</option>';

	$output .= '
			</select>
		  </td>
		</tr>
		<tr>
		<td>Send Email on Payment Success:</td>
		<td> <input type="radio" value="1" name="netcash_paynow_email_admin_on_success" id="email_admin_on_success1" '. ( $options['email_admin_on_success'] == 1 ? 'checked' : '' ) .' />
			  <label for="email_admin_on_success1">On</label>&nbsp;
			<input type="radio" value="0" name="netcash_paynow_email_admin_on_success" id="email_admin_on_success2" '. ( $options['email_admin_on_success'] == 0 ? 'checked' : '' ) .' />
			  <label for="email_admin_on_success2">Off</label></td>
		</tr>
		<tr>
		   <td>Payment Success Email:</td>
		   <td> <input type="text" size="40" name="netcash_paynow_success_email" value="'. $options['success_email'] .'" /></td>
		</tr>
		<tr>
		  <td>Debugging:</td>
		  <td>
			<input type="radio" value="1" name="netcash_paynow_debug" id="netcash_paynow_debug1" '. ( $options['debug'] == 1 ? 'checked' : '' ) .' />
			  <label for="netcash_paynow_debug1">On</label>&nbsp;
			<input type="radio" value="0" name="netcash_paynow_debug" id="netcash_paynow_debug2" '. ( $options['debug'] == 0 ? 'checked' : '' ) .' />
			  <label for="netcash_paynow_debug2">Off</label>
		 </td>
		</tr>
		<tr>
		  <td>Debug Email:</td>
		  <td>
			<input type="text" size="40" name="netcash_paynow_debug_email" value="'. $options['debug_email'] .'" />
		  </td>
		</tr>';

	// Get current store currency
	$sql =
		"SELECT `code`, `currency`
		FROM `". WPSC_TABLE_CURRENCY_LIST ."`
		WHERE `id` IN ('". absint( get_option( 'currency_type' ) ) ."')";
	$store_curr_data = $wpdb->get_row( $sql, ARRAY_A );

	$current_curr = get_option( 'netcash_paynow_currcode' );

	if( empty( $current_curr ) && in_array( $store_curr_data['code'], $wpsc_gateways['netcash_paynow']['supported_currencies']['currency_list'] ) )
	{
		update_option( 'netcash_paynow_currcode', $store_curr_data['code'] );
		$current_curr = $store_curr_data['code'];
	}

	if( $current_curr != $store_curr_data['code'] )
	{
		$output .= '
			<tr>
				<td colspan="2"><br></td>
			</tr>
			<tr>
				<td colspan="2"><strong class="form_group">Currency Converter</td>
			</tr>
			<tr>
				<td colspan="2">Your website uses <strong>'. $store_curr_data['currency'] .'</strong>.
					This currency is not supported by Netcash Pay Now, please select a currency using the drop
					down menu below. Buyers on your site will still pay in your local currency however
					we will send the order through to Netcash Pay Now using the currency below:</td>
			</tr>
			<tr>
				<td>Select Currency:</td>
				<td>
					<select name="netcash_paynow_currcode">';

		$pn_curr_list = $wpsc_gateways['netcash_paynow']['supported_currencies']['currency_list'];

		$sql =
			"SELECT DISTINCT `code`, `currency`
			FROM `". WPSC_TABLE_CURRENCY_LIST ."`
			WHERE `code` IN ('". implode( "','", $pn_curr_list )."')";
		$curr_list = $wpdb->get_results( $sql, ARRAY_A );

		foreach( $curr_list as $curr_item )
		{
			$output .= "<option value='". $curr_item['code'] ."' ".
				( $current_curr == $curr_item['code'] ? 'selected' : '' ) .">". $curr_item['currency'] ."</option>";
		}

		$output .=
			'   </select>
				</td>
			</tr>';
	}

	$output .= '
		<tr class="update_gateway" >
			<td colspan="2">
				<div class="submit">
				<input type="submit" value="'. TXT_WPSC_UPDATE_BUTTON .'" name="updateoption"/>
			</div>
			</td>
		</tr>

		<tr class="firstrowth">
			<td style="border-bottom: medium none;" colspan="2">
				<strong class="form_group">Fields Sent to Netcash Pay Now</strong>
			</td>
		</tr>

		<tr>
			<td>First Name Field</td>
			<td>
			  <select name="netcash_paynow_form[name_first]">
			  '. nzshpcrt_form_field_list( $options['form_name_first'] ) .'
			  </select>
			</td>
		</tr>
		<tr>
			<td>Last Name Field</td>
			<td>
			  <select name="netcash_paynow_form[name_last]">
			  '. nzshpcrt_form_field_list( $options['form_name_last'] ) .'
			  </select>
			</td>
		</tr>';

	return $output;
}

// Add IPN check to WordPress init
// add_action( 'init', 'pn_netcash_paynow_ipn' );
