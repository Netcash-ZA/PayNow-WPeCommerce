Sage Pay Now WP e-Commerce Payment Gateway Module
=================================================

Revision 2.0.0

Introduction
------------

Sage Pay South Africa's Pay Now third party gateway integration for WP e-Commerce.

This module gives you to access the Sage Pay Now gateway which in lets you process credit card transactions online. VISA and MasterCard are supported.

Installation Instructions
-------------------------

Download the files from Github and copy them to your catalog directory:
* https://github.com/SagePay/PayNow-WPeCommerce/archive/master.zip

The downloaded files contains the source but you will only require paynow-wp-e-commerce.zip later when you upload the new plugin.

Configuration
-------------

Prerequisites:

You will need:
* Sage Pay Now login credentials
* Sage Pay Now Service key
* OpenCart admin login credentials

A. Sage Pay Now Gateway Configuration Steps:

1. Log into your Sage Pay Now Gateway configuration page:
	https://merchant.sagepay.co.za/SiteLogin.aspx
2. Type in your Sage Pay Username, Password, and PIN
2. Click on Account Profile
3. Click Sage Connect
4. Click on Pay Now
5. Click "Active:"
6. Type in your Email address
7. Click "Allow credit card payments:"

8. The Accept, Decline, Notify and Redirect URLs should all be:
	http://YOUR_wp_ecommerce_DOMAIN.com/paynow.php

9. It is highly recommended that you "Make test mode active:" while you are still testing your site.

B. WP e-Commerce Steps:

1. Unzip the module to a temporary location on your computer
2. Copy the “wp-content” folder in the archive to your base “wordpress” folder
3. This should NOT overwrite any existing files or folders and merely supplement them with the PayNow files
4. This is however, dependent on the FTP program you use
5. Log into WP e-commerce as admin (http://wpecommerce_installation/wp-admin)
6. Navigate to Plugins / Add New
7. Click Upload / Choose file and select 'paynow-wp-e-commerce.zip'
8. Click 'Install Now' and wait for the process to complete.
9. Click 'Activate Plugin'
10. Navigate to Settings / Store / Payments Tab
11. Click to enable 'Sage Pay Now' and then type in your Service Key
12. Click Update when you are done.

You are now ready to transact. Remember to turn of "Make test mode active:" when you are ready to go live.

Revision History
----------------

* 07 Aug 2015/1.1.0	 Fix errors
* 11 May 2014/1.0.0	 First production release
* 16 Apr 2014/0.9 (BETA) First beta

Tested with WP e-Commerce versions:
3.8.14.1

Feedback, issues & feature requests
-----------------------------------

We welcome you comments and suggestions. If you have any feedback please contact Sage Pay South Africa or log an issue on GitHub.
