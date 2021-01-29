<?php
/**
 * The template for the overview of all customer license keys, across all orders, inside "My Account"
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/lmfwc-license.php.
 *
 * HOWEVER, on occasion I will need to update template files and you
 * (the developer) will need to copy the new files to your theme to
 * maintain compatibility. I try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @version 2.3.0
 *
 * Default variables
 *
 * @var $dateFormat       string The website's default date format.
 * @var $customerLicenses array  Array containing the customer's licenses
 * @var $page             int    Current page
 * @var $canActivate      bool   Value of: Settings - General - My account - User activation
 * @var $canDeactivate    bool   Value of: Settings - General - My account - User deactivation
 */

use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;

defined('ABSPATH') || exit; ?>

<?php foreach ($customerLicenses as $customerLicense): ?>
    <?php $order = $customerLicense['order']; ?>
    <?php $orderId = $customerLicense['orderId']; ?>
    <?php $licenses = $customerLicense['licenses']; ?>

    <h3 class="lmfwc-myaccount-order">
        <?php if ($order): ?>
            <a href="<?php echo esc_url(get_edit_post_link($orderId)); ?>">
                <span><?php _e('Order', 'license-manager-for-woocommerce'); ?></span>
                <span>#<?php echo esc_html($orderId); ?></span>
            </a>
        <?php else: ?>
            <span><?php echo __('Order', 'license-manager-for-woocommerce') . ' #' . $order; ?></span>
        <?php endif; ?>
    </h3>

    <table class="shop_table shop_table_responsive my_account_orders">
        <thead>
        <tr>
            <th class="license-key"><?php _e('License key', 'license-manager-for-woocommerce'); ?></th>
            <th class="license-key"><?php _e('Product', 'license-manager-for-woocommerce'); ?></th>
            <th class="activation"><?php _e('Activation status', 'license-manager-for-woocommerce'); ?></th>
            <th class="valid-until"><?php _e('Valid until', 'license-manager-for-woocommerce'); ?></th>
            <th class="actions"></th>
        </tr>
        </thead>

        <tbody>

        <?php
        /** @var LicenseResourceModel $license */
        foreach ($licenses as $license):
            $timesActivated    = $license->getTimesActivated() ? $license->getTimesActivated() : '0';
            $timesActivatedMax = $license->getTimesActivatedMax() ? $license->getTimesActivatedMax() : '&infin;';
            $product           = wc_get_product($license->getProductId());
            ?>
            <tr>
                <td class="lmfwc-myaccount-license-key">
                    <?php echo $license->getDecryptedLicenseKey(); ?>
                </td>
                <td class="lmfwc-myaccount-product-name">
                    <?php if ($product): ?>
                        <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                            <span><?php echo $product->get_name(); ?></span>
                        </a>
                    <?php else: ?>
                        <span><?php _e('Unknown product', 'license-manager-for-woocommerce'); ?></span>
                    <?php endif; ?>
                </td>
                <td class="lmfwc-myaccount-activation-status">
                    <span><?php echo esc_html($timesActivated); ?></span>
                    <span>/</span>
                    <span><?php echo esc_html($timesActivatedMax); ?></span>
                </td>
                <td class="lmfwc-myaccount-valid-until">
                    <?php
                    if ($license->getExpiresAt()) {
                        try {
                            printf('<b>%s</b>', (new DateTime($license->getExpiresAt()))->format($dateFormat));
                        } catch (Exception $e) {
                            echo esc_html($license->getExpiresAt());
                        }
                    }
                    ?>
                </td>
                <td class="lmfwc-myaccount-actions">
                    <?php if ($canActivate): ?>
                        <form method="post">
                            <input type="hidden"
                                   name="license"
                                   value="<?php echo esc_attr($license->getDecryptedLicenseKey());?>"/>
                            <input type="hidden" name="action" value="activate">
                            <?php wp_nonce_field('lmfwc_myaccount_activate_license'); ?>
                            <button class="button" type="submit">
                                <?php _e('Activate', 'license-manager-for-woocommerce');?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($canDeactivate): ?>
                        <form method="post">
                            <input type="hidden"
                                   name="license"
                                   value="<?php echo esc_attr($license->getDecryptedLicenseKey());?>"/>
                            <input type="hidden" name="action" value="deactivate">
                            <?php wp_nonce_field('lmfwc_myaccount_deactivate_license'); ?>
                            <button class="button" type="submit">
                                <?php _e('Deactivate', 'license-manager-for-woocommerce');?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
<?php endforeach; ?>
