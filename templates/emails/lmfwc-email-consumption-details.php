<?php
/**
 * The template which warns the user that the consumption threshold has been reached (HTML).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/lmfwc-email-consumption-details.php.
 *
 * HOWEVER, on occasion I will need to update template files and you
 * (the developer) will need to copy the new files to your theme to
 * maintain compatibility. I try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 1.0.0
 */

use LicenseManagerForWooCommerce\Models\Resources\License;

defined('ABSPATH') || exit; ?>

<div style="margin-bottom: 40px;">
    <?php $name = $user->user_firstname ? $user->user_firstname : $user->display_name; ?>
    <p><?php printf(esc_html__('Hi %s,', 'license-manager-for-woocommerce'), $name); ?></p>
    <p><?php printf(esc_html__('You have now reached %d percent consumption on %d license key(s). Consider upgrading your license key or buy a new one.', 'license-manager-for-woocommerce'), $threshold, $license_count); ?></p>
    <p><?php esc_html_e('Best regards,', 'license-manager-for-woocommerce'); ?></p>
</div>
