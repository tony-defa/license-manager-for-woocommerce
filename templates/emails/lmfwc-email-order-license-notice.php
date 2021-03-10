<?php
/**
 * Template part which adds the license delivery notice instead of the
 * licenses to the "Completed order" email (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/lmfwc-email-order-license-notice.php.
 *
 * HOWEVER, on occasion I will need to update template files and you
 * (the developer) will need to copy the new files to your theme to
 * maintain compatibility. I try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @var WC_Order $order The WooCommerce order object
 * @var bool $sent_to_admin True if email was sent to admin as well
 * @var bool $plain_text True if email is plain text
 * @var WC_Email $email The WooCommerce email object
 * @var array $data Customer licenses
 * @var string $date_format Website's date format
 *
 * @version 2.3.0
 */

defined( 'ABSPATH' ) || exit; ?>

<h2><?php _e( 'Licenses', 'license-manager-for-woocommerce' ); ?></h2>

<p><?php esc_html_e( "Your licenses will be delivered shortly. It can take up to 24 hours, but usually doesn't take longer than a few minutes. Thank you for your patience.", 'license-manager-for-woocommerce' ); ?></p>
