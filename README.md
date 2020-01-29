Netcash Now WP e-Commerce Payment Gateway Module
=================================================

Revision 2.0.0

Introduction
------------

Netcash South Africa's Pay Now third party gateway integration for WP e-Commerce.

This module gives you to access the Netcash Pay Now gateway which in lets you process credit card transactions online. VISA and MasterCard are supported.

Installation Instructions
-------------------------

Download the files from Github and copy them to your catalog directory:
* https://github.com/Netcash-ZA/PayNow-WPeCommerce/archive/master.zip

The downloaded files contains the source but you will only require paynow-wp-e-commerce.zip later when you upload the new plugin.

Configuration
-------------

Prerequisites:

You will need:
* Netcash login credentials
* Netcash Pay Now Service key
* OpenCart admin login credentials

A. Netcash Now Gateway Configuration Steps:

1. Log into your Netcash account:
	https://merchant.netcash.co.za/SiteLogin.aspx
2. Type in your Netcash Username, Password, and PIN
2. Click on Account Profile
3. Click on NetConnector
4. Click on Pay Now
5. Click "Active:"
6. Type in your Email address
7. Click "Allow credit card payments:"

8. The Accept, Decline, Notify and Redirect URLs should all be:
	https://YOUR_wp_ecommerce_DOMAIN.com/paynow_callback.php

9. It is highly recommended that you "Make test mode active:" while you are still testing your site.

B. WP e-Commerce Steps:

1. Unzip the module to a temporary location on your computer
2. Copy the “wp-e-commerce” folder in the archive to your WordPress plugins folder (/wp-content/plugns)
3. This should NOT overwrite any existing files or folders and merely supplement them with the PayNow files
4. This is however, dependent on the FTP program you use
5. Log into WP e-commerce as admin (https://wpecommerce_installation/wp-admin)
6. Navigate to Plugins / Add New
7. Click Upload / Choose file and select 'paynow-wp-e-commerce.zip'
8. Click 'Install Now' and wait for the process to complete.
9. Click 'Activate Plugin'
10. Navigate to Settings / Store / Payments Tab
11. Click to enable 'Netcash Pay Now' and then type in your Service Key
12. Click Update when you are done.

You are now ready to transact. Remember to turn of "Make test mode active:" when you are ready to go live.

Issues & Feature Requests
-------------------------

We welcome your feedback.

Please contact Netcash South Africa with any questions or issues.
