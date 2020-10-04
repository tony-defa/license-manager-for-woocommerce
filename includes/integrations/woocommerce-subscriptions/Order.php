<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use DateTime;
use Exception;
use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;
use WC_Order;
use WC_Subscriptions_Product;

defined('ABSPATH') || exit;

class Order
{
    /**
     * Order constructor.
     */
    public function __construct()
    {
        add_filter('lmfwc_maybe_skip_subscription_renewals', array($this, 'maybeSkipSubscriptionRenewal'), 10, 2);
    }


    /**
     * Checks if the order is a renewal of a WooCommerce Subscription, in which
     * case it extends the license(s) by the subscription duration.
     *
     * @param int $orderId   Subscription Renewal Order ID
     * @param int $productId WooCommerce Product ID
     * @return bool
     */
    public function maybeSkipSubscriptionRenewal($orderId, $productId)
    {
        // Return if this is not a renewal order
        if (!wcs_order_contains_renewal($orderId)) {
            error_log("LMFWC: Skipped Order #{$orderId} because it does not contain a renewal.");
            return false;
        }

        // Return if the product hasn't been configured to extend subscriptions
        if (!get_post_meta($productId, 'lmfwc_extend_subscription', true)) {
            error_log("LMFWC: Skipped Order #{$orderId} because product #{$productId} should not be extended.");
            return false;
        }

        /** @var WC_Order $parentOrder */
        $parentOrder = \WC_Subscriptions_Renewal_Order::get_parent_order($orderId);

        if (!$parentOrder) {
            error_log("LMFWC: Skipped Order #{$orderId} because parent order could not be found.");
            return false;
        }

        $subscriptionPeriod   = WC_Subscriptions_Product::get_period($productId);
        $subscriptionInterval = intval(WC_Subscriptions_Product::get_interval($productId));

        /** @var false|LicenseResourceModel[] $licenses */
        $licenses = lmfwc_get_licenses(
            array(
                'order_id' => $parentOrder->get_id(),
                'product_id' => $productId
            )
        );

        $licenseCount = count($licenses);

        error_log("LMFWC: License count is: {$licenseCount}");

        /** @var LicenseResourceModel $license */
        foreach ($licenses as $license) {
            try {
                $dateNewExpiresAt = new DateTime($license->getExpiresAt());
            } catch (Exception $e) {
                error_log("LMFWC: Exception 1 - " . $e->getMessage());
                return false;
            }

            // Singular form, i.e. "+1 week"
            $modify = '+' . $subscriptionInterval . ' ' . $subscriptionPeriod;

            // Plural form, i.e. "+3 weeks"
            if ($subscriptionInterval > 1) {
                $modify .= 's';
            }

            $licenseExpiresAt = $license->getExpiresAt();

            error_log("LMFWC: Interval: {$subscriptionInterval}");
            error_log("LMFWC: Period: {$subscriptionPeriod}");
            error_log("LMFWC: Modify String: {$modify}");
            error_log("LMFWC: ExpiresAt OLD - {$licenseExpiresAt}");

            $dateNewExpiresAt->modify($modify);

            error_log("LMFWC: ExpiresAt NEW - {$dateNewExpiresAt->format('Y-m-d H:i:s')}");

            try {
                lmfwc_update_license(
                    $license->getDecryptedLicenseKey(),
                    array(
                        'expires_at' => $dateNewExpiresAt->format('Y-m-d H:i:s')
                    )
                );
            } catch (Exception $e) {
                error_log("LMFWC: Exception 2 - " . $e->getMessage());
                return false;
            }
        }

        error_log("LMFWC: Success, returning TRUE");

        return true;
    }

}