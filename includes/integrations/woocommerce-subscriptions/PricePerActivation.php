<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;
use WC_Subscription;
use WC_Order;
use WC_Order_Item_Product;
use WC_Stripe_Helper;

defined('ABSPATH') || exit;

class PricePerActivation
{

    /**
     * PricePerActivation constructor.
     */
    public function __construct()
    {
        add_filter('wcs_new_order_created', array($this, 'newOrderCreated'), 90, 3);
    }

    public function newOrderCreated($newOrder, $subscription, $type)
    {
        if ($type !== 'renewal_order' || !wcs_is_subscription( $subscription )) {
            error_log("LMFWC: Skipped because is not valid subscription renewal order.");
            return $newOrder;
        }

        /** @var int $parentOrderId */
        $parentOrderId = $subscription->get_parent_id();

        /** @var false|WC_Order $parentOrder */
        $parentOrder = wc_get_order($parentOrderId);

        $items = $newOrder->get_items();
        foreach ($items as $item) {

            /** @var int $productId */
            if (!$productId = $item->get_variation_id()) {
                $productId = $item->get_product_id();
            }

            if (!lmfwc_is_licensed_product($productId)
                    || lmfwc_get_subscription_renewal_action($productId) === 'issue_new_license'
                    || lmfwc_get_subscription_renewal_reset_action($productId) === 'do_not_reset_on_renewal') {
                error_log("LMFWC: Skipped item #{$item->get_id()} because product with id #{$productId} is not a licensed product, does issue a new license or does not reset activation count on renewal.");
                continue;
            }

            /** @var false|LicenseResourceModel[] $licenses */
            $licenses = lmfwc_get_licenses(
                array(
                    'order_id' => $parentOrder->get_id(),
                    'product_id' => $productId
                )
            );

            if (!$licenses) {
                error_log("LMFWC: Skipped parent Order #{$parentOrderId} because no licenses were found.");
                return false;
            }

            $licenseCount = count($licenses);
            error_log("LMFWC: License count is: {$licenseCount}");

            $newQuantity = 0;

            /** @var LicenseResourceModel $license */
            foreach ($licenses as $license) {
                $newQuantity += $license->getTimesActivated();
                error_log("LMFWC: License activated {$license->getTimesActivated()} times.");
            }

            $itemNewTotal = strval($newQuantity * floatval($item->get_subtotal()));
            $item->set_quantity($newQuantity);
            $item->set_total($itemNewTotal);
            if (!$newQuantity)
                $item->set_subtotal($itemNewTotal);

            $item->save();

            error_log("LMFWC: The new total of the item #{$item->get_id()} is {$itemNewTotal}.");
        }

        $newOrder->calculate_totals();

        // TODO: figure out what to do with orders where the total is below the minimum excepted expenditure but not 0 € (e.g. stripe minimum 0.50 €)
        if ($newOrder->get_total() > 0) {
            $gateway = $parentOrder->get_payment_method();
            switch ($gateway) {
                case 'stripe':
                    if ($newOrder->get_total() * 100 < WC_Stripe_Helper::get_minimum_amount()) { // multiply by 100 because WC_Stripe_Helper cent values
                        // payment is below minimum amount
                        error_log("LMFWC: stripe error: Total order amount lower than minimum allowed amount from stripe");
                    }
                    break;
                
                default:
                    break;
            }
        }

        return $newOrder;
    }
}