<?php
/*
 * TODO Add direct link to Sage Pay Now
  Plugin Name: sagepaynow.php
  Plugin URI: www.sagepay.co.za
  Description: This plugin enables WP e-Commerce to interact with the Sage Pay Now payment gateway
 */
 /** 
 * 
 */

// Set gateway variables for WP e-Commerce
$nzshpcrt_gateways[$num]['name'] = 'Sage Pay Now';
$nzshpcrt_gateways[$num]['internalname'] = 'sagepaynow';
$nzshpcrt_gateways[$num]['function'] = 'gateway_sagepaynow';
$nzshpcrt_gateways[$num]['form'] = "form_sagepaynow";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_sagepaynow";
$nzshpcrt_gateways[$num]['payment_type'] = "sagepaynow";
$nzshpcrt_gateways[$num]['supported_currencies']['currency_list'] = array( 'ZAR' );
$nzshpcrt_gateways[$num]['supported_currencies']['option_name'] = 'sagepaynow_currcode';

// Include the Sage Pay Now common file
define( 'PN_DEBUG', ( get_option('sagepaynow_debug') == 1  ? true : false ) );
require_once( 'sagepaynow_common.inc' );

/**
 * gateway_sagepaynow()
 *
 * Create the form/information which is submitted to the gateway.
 *
 * @param mixed $sep Separator
 * @param mixed $sessionid Session ID for user session
 * @return
 */
function gateway_sagepaynow( $sep, $sessionid )
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
    $pn_curr_code = get_option( 'sagepaynow_currcode' );

    // Set default currency
    if( $pn_curr_code == '' )
    	$pn_curr_code = 'ZAR';

    // Convert from the currency of the users shopping cart to the currency
    // which the user has specified in their Sage Pay Now preferences.
    $curr = new CURRENCYCONVERTER();

	$total = $wpsc_cart->calculate_total_price();
	$discount = $wpsc_cart->coupons_amount;

	// If Sage Pay Now currency differs from local currency
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

    $serviceKey = get_option( 'sagepaynow_service_key' );
    $sagepaynow_url = 'https://paynow.sagepay.co.za/site/paynow.aspx';
    
    // Create URLs
    $returnUrl = get_option( 'transact_url' ) . $sep ."sessionid=". $sessionid ."&gateway=sagepaynow";
    $cancelUrl = get_option( 'transact_url' );
    $notifyUrl = get_option( 'siteurl' ) .'/?ipn_request=true';

    // Construct variables for post
    $data = array(
        // Merchant details        
        'service_key' => $serviceKey,
        'return_url' => $returnUrl,
        'cancel_url' => $cancelUrl,
        'notify_url' => $notifyUrl,

        // Item details
    	'item_name' => get_option( 'blogname' ) .' purchase, Order #'. $purchase['id'],
    	'item_description' => $pnDescription,
    	'amount' => number_format( sprintf( "%01.2f", $pnAmount ), 2, '.', '' ),
        'm_payment_id' => $purchase['id'],
        'currency_code' => $pn_curr_code,
        'custom_str1' => $sessionid,
        
        // Other details
        'user_agent' => PN_USER_AGENT,
        );

    // Buyer details
    if( !empty( $_POST['collected_data'][get_option('sagepaynow_form_name_first')] ) )
        $data['name_first'] = $_POST['collected_data'][get_option('sagepaynow_form_name_first')];

    if( !empty( $_POST['collected_data'][get_option('sagepaynow_form_name_last')] ) )
        $data['name_last'] = $_POST['collected_data'][get_option('sagepaynow_form_name_last')];

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
      	echo "<a href='". $sagepaynow_url ."?". $pnOutput ."'>Test the URL here</a>";
      	echo "<pre>". print_r( $data, true ) ."</pre>";
      	exit();
	}

    // Send to Sage Pay Now (GET)
    header( "Location: ". $sagepaynow_url ."?". $pnOutput );
    exit();
}

/**
 * nzshpcrt_sagepaynow_ipn()
 *
 * Handle IPN callback from Sage Pay Now
 *
 * @return
 */
function nzshpcrt_sagepaynow_ipn()
{
    // Check if this is an IPN request
    // Has to be done like this (as opposed to "exit" as processing needs
    // to continue after this check.
    if( ( $_GET['ipn_request'] == 'true' ) )
    {
        // Variable Initialization
        global $wpdb;
        $pnError = false;
        $pnErrMsg = '';
        $pnDone = false;
        $pnData = array();
        $pnHost = 'https://paynow.sagepay.co.za/site/paynow.aspx';
        $pnOrderId = '';
        $pnParamString = '';
        
        // Set debug email address
        if( get_option( 'sagepaynow_debug_email' ) != '' )
            $pnDebugEmail = get_option( 'sagepaynow_debug_email' );
        elseif( get_option( 'purch_log_email' ) != '' )
            $pnDebugEmail = get_option( 'purch_log_email' );
        else
            $pnDebugEmail = get_option( 'admin_email' );

        pnlog( 'Sage Pay Now IPN call received' );
        
        //// Notify Sage Pay Now that information has been received
        if( !$pnError && !$pnDone )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        //// Get data sent by Sage Pay Now
        if( !$pnError && !$pnDone )
        {
            pnlog( 'Get posted data' );
        
            // Posted variables from IPN
            $pnData = pnGetData();
        
            pnlog( 'Sage Pay Now Data: '. print_r( $pnData, true ) );
        
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
                WHERE `id` = ". $pnData['m_payment_id'] ."
                LIMIT 1";
            $purchase = $wpdb->get_row( $sql, ARRAY_A );

            pnlog( "Purchase:\n". print_r( $purchase, true )  );

            // Check if order has already been processed
            // It has been "processed" if it has a status above "Order Received"
            if( $purchase['processed'] > get_option( 'sagepaynow_pending_status' ) )
            {
                pnlog( "Order has already been processed" );
                $pnDone = true;
            }
        }
            
        // Check data against internal order
        if( !$pnError && !$pnDone )
        {
            pnlog( 'Check data against internal order' );

            // Check order amount
            if( !pnAmountsEqual( $pnData['amount_gross'], $purchase['totalprice'] ) )
            {
                $pnError = true;
                $pnErrMsg = PN_ERR_AMOUNT_MISMATCH;
            }
            // Check session ID
            elseif( strcasecmp( $pnData['custom_str1'], $purchase['sessionid'] ) != 0 )
            {
                $pnError = true;
                $pnErrMsg = PN_ERR_SESSIONID_MISMATCH;
            }
        }

        //// Check status and update order
        if( !$pnError && !$pnDone )
        {
            pnlog( 'Check status and update order' );

            $sessionid = $pnData['custom_str1'];
            $transaction_id = $pnData['Trace'];
            $vendor_name = get_option( 'blogname');
            $vendor_url = get_option( 'siteurl');

    		switch( $pnData['payment_status'] )
            {
                case 'COMPLETE':
                    pnlog( '- Complete' );

                    // Update the purchase status
					$data = array(
						'processed' => get_option( 'sagepaynow_complete_status'),
						'transactid' => $transaction_id,
						'date' => time(),
					);

					wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
        			transaction_results( $sessionid, false, $transaction_id );
                    $admin_email = get_settings('admin_email');
                    
                        $subject = "Sage Pay Now IPN on your site";
                        $body =
                            "Hi,\n\n".
                            "A Sage Pay Now transaction has been completed on your website\n".
                            "------------------------------------------------------------\n".
                            "Site: ". $vendor_name ." (". $vendor_url .")\n".
                            "Purchase ID: ". $pnData['m_payment_id'] ."\n".
                            "Sage Pay Now Transaction ID: ". $pnData['Trace'] ."\n".
                            "Sage Pay Now Payment Status: ". $pnData['TransactionAccepted'] ."\n".
                            "Order Status Code: ". $d['order_status'];
                            
                    if(get_option('sagepaynow_email_admin_on_success') == 1)
                    {
                        mail( get_option('sagepaynow_success_email'), $subject, $body );
                    }
                    if( PN_DEBUG )
                    {
                        mail( $pnDebugEmail, $subject, $body );
                    }
                    break;

    			case 'FAILED':
                    pnlog( '- Failed' );

                    // If payment fails, delete the purchase log
        			$sql =
                        "SELECT * FROM `". WPSC_TABLE_CART_CONTENTS ."`
                        WHERE `purchaseid`='". $pnData['m_payment_id'] ."'";
        			$cart_content = $wpdb->get_results( $sql, ARRAY_A );
        			foreach( (array)$cart_content as $cart_item )
        				$cart_item_variations = $wpdb->query(
                            "DELETE FROM `". WPSC_TABLE_CART_ITEM_VARIATIONS ."`
                            WHERE `cart_id` = '". $cart_item['id'] ."'", ARRAY_A );

                    $wpdb->query( "DELETE FROM `". WPSC_TABLE_CART_CONTENTS ."` WHERE `purchaseid`='". $pnData['m_payment_id'] ."'" );
        			$wpdb->query( "DELETE FROM `". WPSC_TABLE_SUBMITED_FORM_DATA ."` WHERE `log_id` IN ('". $pnData['m_payment_id'] ."')" );
        			$wpdb->query( "DELETE FROM `". WPSC_TABLE_PURCHASE_LOGS ."` WHERE `id`='". $pnData['m_payment_id'] ."' LIMIT 1" );

                    $subject = "Sage Pay Now IPN Transaction on your site";
                    $body =
                        "Hi,\n\n".
                        "A failed Sage Pay Now transaction on your website requires attention\n".
                        "------------------------------------------------------------\n".
                        "Site: ". $vendor_name ." (". $vendor_url .")\n".
                        "Purchase ID: ". $purchase['id'] ."\n".
                        "User ID: ". $purchase['user_ID'] ."\n".
                        "Sage Pay Now Transaction ID: ". $pnData['Trace'] ."\n".
                        "Sage Pay Now Payment Status: ". $pnData['TransactionAccepted'];
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
            $subject = "Sage Pay Now IPN error: ". $pnErrMsg;
            $body =
                "Hi,\n\n".
                "An invalid Sage Pay Now transaction on your website requires attention\n".
                "----------------------------------------------------------------------\n".
                "Site: ". $vendor_name ." (". $vendor_url .")\n".
                "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
                "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
                "Purchase ID: ". $purchase['id'] ."\n".
                "User ID: ". $purchase['user_ID'] ."\n";
            if( isset( $pnData['Trace'] ) )
                $body .= "Sage Pay Now Transaction ID: ". $pnData['Trace'] ."\n";
            if( isset( $pnData['payment_status'] ) )
                $body .= "Sage Pay Now Payment Status: ". $pnData['TransactionAccepted'] ."\n";
            $body .=
                "\nError: ". $pnErrMsg ."\n";

            switch( $pnErrMsg )
            {
                case PN_ERR_AMOUNT_MISMATCH:
                    $body .=
                        "Value received : ". $pnData['amount_gross'] ."\n".
                        "Value should be: ". $purchase['totalprice'];
                    break;

                case PN_ERR_ORDER_ID_MISMATCH:
                    $body .=
                        "Value received : ". $pnData['m_payment_id'] ."\n".
                        "Value should be: ". $purchase['id'];
                    break;

                case PN_ERR_SESSION_ID_MISMATCH:
                    $body .=
                        "Value received : ". $pnData['custom_str1'] ."\n".
                        "Value should be: ". $purchase['sessionid'];
                    break;

                // For all other errors there is no need to add additional information
                default:
                    break;
            }

            mail( $pnDebugEmail, $subject, $body );
        }

        // Close log
        pnlog( '', true );
    	exit();
   	}
}

/**
 * submit_sagepaynow()
 *
 * Updates the options submitted by the config form (function "form_sagepaynow"}
 *
 * @return
 */
function submit_sagepaynow()
{    
    if( isset( $_POST['sagepaynow_service_key'] ) )
        update_option( 'sagepaynow_service_key', $_POST['sagepaynow_service_key'] );

    if( isset( $_POST['sagepaynow_currcode'] ) && !empty( $_POST['sagepaynow_currcode'] ) )
        update_option( 'sagepaynow_currcode', $_POST['sagepaynow_currcode'] );


    if( isset( $_POST['sagepaynow_pending_status'] )  )
        update_option( 'sagepaynow_pending_status', (int)$_POST['sagepaynow_pending_status'] );

    if( isset( $_POST['sagepaynow_complete_status'] )  )
        update_option( 'sagepaynow_complete_status', (int)$_POST['sagepaynow_complete_status'] );


    if( isset( $_POST['sagepaynow_debug'] ) )
        update_option( 'sagepaynow_debug', (int)$_POST['sagepaynow_debug'] );

    if( isset( $_POST['sagepaynow_debug_email'] ) )
        update_option( 'sagepaynow_debug_email', $_POST['sagepaynow_debug_email'] );
        
     if( isset( $_POST['sagepaynow_success_email'] ) )
        update_option( 'sagepaynow_success_email', $_POST['sagepaynow_success_email'] );
        
    if( isset( $_POST['sagepaynow_email_admin_on_success'] ) )
        update_option( 'sagepaynow_email_admin_on_success', $_POST['sagepaynow_email_admin_on_success'] );    

    foreach( (array)$_POST['sagepaynow_form'] as $form => $value )
        update_option( ( 'sagepaynow_form_'.$form ), $value );

    return( true );
}

/**
 * form_sagepaynow()
 *
 * Displays the configuration form for the admin area.
 *
 * @return
 */
function form_sagepaynow()
{
    // Variable declaration
    global $wpdb, $wpsc_gateways;

    // Set defaults
    $options = array();
    
    $options['service_key'] = ( get_option( 'sagepaynow_service_key' ) != '' ) ?
        get_option( 'sagepaynow_service_key' ) : 'c668242b-2dd7-46c6-b153-3f572d531be';

    $options['pending_status'] = ( get_option( 'sagepaynow_pending_status' ) != '' ) ?
        get_option( 'sagepaynow_pending_status' ) : 1;
    $options['complete_status'] = ( get_option( 'sagepaynow_complete_status' ) != '' ) ?
        get_option( 'sagepaynow_complete_status' ) : 3;

    $options['debug'] = ( (int)get_option( 'sagepaynow_debug' ) != '' ) ?
        get_option( 'sagepaynow_debug' ) : 0;
    $options['debug_email'] = ( get_option( 'sagepaynow_debug_email' ) != '' ) ?
        get_option( 'sagepaynow_debug_email' ) : get_settings('admin_email');
        
    $options['email_admin_on_success'] = ( get_option( 'sagepaynow_email_admin_on_success' ) != '' ) ?
        get_option( 'sagepaynow_email_admin_on_success' ) : 1;
          
    $options['success_email'] = ( get_option( 'sagepaynow_success_email' ) != '' ) ?
        get_option( 'sagepaynow_success_email' ) : get_settings('admin_email');

    $options['form_name_first'] = ( get_option( 'sagepaynow_form_name_first' ) != '' ) ?
        get_option( 'sagepaynow_form_name_first' ) : 2;
    $options['form_name_last'] = ( get_option( 'sagepaynow_form_name_last' ) != '' ) ?
        get_option( 'sagepaynow_form_name_last' ) : 3;

    // Generate output
    $output = '
        <tr>
      	  <td colspan="2">
      	    <span  class="wpscsmall description">
            Please <a href="https://www.sagepay.co.za/user/register" target="_blank">register</a> on
            <a href="https://www.sagepay.co.za" target="_blank">Sage Pay Now</a> to use this module.
            Your <em>Service Key</em> are available on your
            <a href="http://www.sagepay.co.za/acc/integration" target="_blank">Integration page</a> on the Sage Pay Now website.</span>
      	  </td>
        </tr>
               
        <tr>
          <td>Service Key:</td>
          <td>
            <input type="text" size="40" value="'. $options['service_key'] .'" name="sagepaynow_service_key" /> <br />
          </td>
        </tr>'."\n";

    // Get list of purchase statuses
    global $wpsc_purchlog_statuses;
    pnlog( "Purchase Statuses:\n". print_r( $wpsc_purchlog_statuses, true ) );

    $output .= '
        <tr>
          <td>Status for Pending Payments:</td>
          <td>
            <select name="sagepaynow_pending_status">';

    foreach( $wpsc_purchlog_statuses as $status )
        $output .= '<option value="'. $status['order'] .'" '. ( ( $status['order'] == $options['pending_status'] ) ? 'selected' : '' ) .'>'. $status['label'] .'</option>';

    $output .= '
            </select>
          </td>
        </tr>
        <tr>
          <td>Status for Successful Payments:</td>
          <td>
            <select name="sagepaynow_complete_status">';

    foreach( $wpsc_purchlog_statuses as $status )
        $output .= '<option value="'. $status['order'] .'" '. ( ( $status['order'] == $options['complete_status'] ) ? 'selected' : '' ) .'>'. $status['label'] .'</option>';

    $output .= '
            </select>
          </td>
        </tr>
        <tr>
        <td>Send Email on Payment Success:</td>
        <td> <input type="radio" value="1" name="sagepaynow_email_admin_on_success" id="email_admin_on_success1" '. ( $options['email_admin_on_success'] == 1 ? 'checked' : '' ) .' />
              <label for="email_admin_on_success1">On</label>&nbsp;
            <input type="radio" value="0" name="sagepaynow_email_admin_on_success" id="email_admin_on_success2" '. ( $options['email_admin_on_success'] == 0 ? 'checked' : '' ) .' />
              <label for="email_admin_on_success2">Off</label></td>
        </tr>
        <tr>
           <td>Payment Success Email:</td>
           <td> <input type="text" size="40" name="sagepaynow_success_email" value="'. $options['success_email'] .'" /></td>
        </tr>
        <tr>
          <td>Debugging:</td>
          <td>
            <input type="radio" value="1" name="sagepaynow_debug" id="sagepaynow_debug1" '. ( $options['debug'] == 1 ? 'checked' : '' ) .' />
              <label for="sagepaynow_debug1">On</label>&nbsp;
            <input type="radio" value="0" name="sagepaynow_debug" id="sagepaynow_debug2" '. ( $options['debug'] == 0 ? 'checked' : '' ) .' />
              <label for="sagepaynow_debug2">Off</label>
         </td>
        </tr>
        <tr>
          <td>Debug Email:</td>
          <td>
            <input type="text" size="40" name="sagepaynow_debug_email" value="'. $options['debug_email'] .'" />
          </td>
        </tr>';

    // Get current store currency
    $sql =
        "SELECT `code`, `currency`
        FROM `". WPSC_TABLE_CURRENCY_LIST ."`
        WHERE `id` IN ('". absint( get_option( 'currency_type' ) ) ."')";
    $store_curr_data = $wpdb->get_row( $sql, ARRAY_A );

    $current_curr = get_option( 'sagepaynow_currcode' );

    if( empty( $current_curr ) && in_array( $store_curr_data['code'], $wpsc_gateways['sagepaynow']['supported_currencies']['currency_list'] ) )
    {
        update_option( 'sagepaynow_currcode', $store_curr_data['code'] );
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
                    This currency is not supported by Sage Pay Now, please select a currency using the drop
                    down menu below. Buyers on your site will still pay in your local currency however
                    we will send the order through to Sage Pay Now using the currency below:</td>
            </tr>
            <tr>
                <td>Select Currency:</td>
                <td>
                    <select name="sagepaynow_currcode">';

		$pn_curr_list = $wpsc_gateways['sagepaynow']['supported_currencies']['currency_list'];

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
        		<strong class="form_group">Fields Sent to Sage Pay Now</strong>
        	</td>
        </tr>

        <tr>
            <td>First Name Field</td>
            <td>
              <select name="sagepaynow_form[name_first]">
              '. nzshpcrt_form_field_list( $options['form_name_first'] ) .'
              </select>
            </td>
        </tr>
        <tr>
            <td>Last Name Field</td>
            <td>
              <select name="sagepaynow_form[name_last]">
              '. nzshpcrt_form_field_list( $options['form_name_last'] ) .'
              </select>
            </td>
        </tr>';

    return $output;
}

// Add IPN check to WordPress init
add_action( 'init', 'nzshpcrt_sagepaynow_ipn' );
?>