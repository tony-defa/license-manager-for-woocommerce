<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use DateTime;
use Exception;
use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;
use WC_Subscriptions_Product;
use WC_Subscriptions_Renewal_Order;

defined('ABSPATH') || exit;

class Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        new ProductData();

        add_filter('lmfwc_maybe_skip_subscription_renewals', array($this, 'maybeSkipSubscriptionRenewal'), 10, 2);
    }

    /**
     * Checks if the order is a renewal of a WooCommerce Subscription, in which
     * case it extends the license(s) by the subscription duration.
     *
     * @param int $orderId   WooCommerce Order ID
     * @param int $productId WooCommerce Product ID
     * @return bool
     */
    public function maybeSkipSubscriptionRenewal($orderId, $productId)
    {
        // Return if this is not a renewal order
        if (!wcs_order_contains_renewal($orderId)) {
            return false;
        }

        // Return if the product hasn't been configured to extend subscriptions
        if (!get_post_meta($productId, 'lmfwc_extend_subscription', true)) {
            return false;
        }

        $subscriptionPeriod   = WC_Subscriptions_Product::get_period($productId);
        $subscriptionInterval = WC_Subscriptions_Product::get_interval($productId);

        /** @var false|LicenseResourceModel[] $licenses */
        $licenses = lmfwc_get_licenses(
            array(
                'order_id' => $orderId,
                'product_id' => $productId
            )
        );

        /** @var LicenseResourceModel $license */
        foreach ($licenses as $license) {
            try {
                $dateNewExpiresAt = new DateTime($license->getExpiresAt());
            } catch (Exception $e) {
                return false;
            }

            $dateNewExpiresAt->modify('+' . $subscriptionInterval . ' ' . $subscriptionPeriod . 's');

            try {
                lmfwc_update_license(
                    $license->getDecryptedLicenseKey(),
                    array(
                        'expires_at' => $dateNewExpiresAt->format('Y-m-d H:i:s')
                    )
                );
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }
}