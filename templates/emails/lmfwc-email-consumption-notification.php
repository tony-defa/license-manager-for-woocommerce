<?php
/**
 * Deliver Order license key(s) to Customer.
 */
defined('ABSPATH') || exit;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);

/**
 * @hooked \LicenseManagerForWooCommerce\Emails\Main Output consumption details.
 */
do_action('lmfwc_email_consumption_details', $user, $license_count, $threshold, $sent_to_admin, $plain_text, $email);

/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);