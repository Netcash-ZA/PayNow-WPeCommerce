<?php

define('WP_USE_THEMES', false);
require('./wp-load.php');

$home_url = get_option('siteurl');
$success = false;

if( isset($_POST) && $_POST['TransactionAccepted'] == true ) {
	// Success
	$success = pn_sagepaynow_ipn();
}

if( $success ) {
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