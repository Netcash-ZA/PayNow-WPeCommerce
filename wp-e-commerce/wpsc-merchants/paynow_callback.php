<?php

define('WP_USE_THEMES', false);
require('./wp-load.php');

$home_url = get_option('siteurl');
$success = false;

function pn_do_transaction(){

    $accepted = isset($_POST['TransactionAccepted']) && $_POST['TransactionAccepted'] == true;
    if( $accepted && pn_sagepaynow_ipn() ) {

        // Transaction successfull
        do_action('wpsc_payment_successful');
        //die('SUCCESS!');

        echo "<p>Payment was successful.</p>";
        echo "<p>You will be redirected to the site homepage in 5 seconds. <a href='$home_url'>Click here</a> to return immediately.</p>";
        ?>

        <script type="text/javascript">
            setTimeout(function () {
               window.location.href = '<?php echo $home_url; ?>';
            }, 5000);
        </script>
        <?php
        pnLog( 'Returning to clientarea.' );
    ?>

    <?php
    } else {
        // Fail
        do_action('wpsc_payment_failed');
        // die('FAILED');
        pnLog( 'Transaction Unsuccessful' );

        echo "<p>Payment was declined. Reason: " . $_POST['Reason'] . "</p>";
        echo "<p><a href='$home_url'>Click here</a> to return to the store.</p>";
    }
}

/**
 * Check if this is a 'callback' stating the transaction is pending.
 */
function pn_is_pending() {
    return isset($_POST['TransactionAccepted'])
        && $_POST['TransactionAccepted'] == 'false'
        && stristr($_POST['Reason'], 'pending');
}

// $url_for_redirect = $home_url . '/your-account/';
$url_for_redirect = ( get_option( 'user_account_url' ) );

if( isset($_POST) && !empty($_POST) && !pn_is_pending() ) {

    // This is the notification coming in OR the CC callback!
    // Act as an IPN request and forward request to Credit Card method.
    // Logic is exactly the same

    pn_do_transaction();

    die();

} else {
    // Probably calling the "redirect" URL

    pnlog(__FILE__ . ' Probably calling the "redirect" URL');

    if( $url_for_redirect ) {
        header ( "Location: {$url_for_redirect}" );
    } else {
        die( "No 'redirect' URL set." );
    }
}

die( PN_ERR_BAD_ACCESS );