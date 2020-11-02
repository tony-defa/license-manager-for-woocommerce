<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;
use WC_Subscription;
use WC_Order;
use WC_Order_Item_Product;
use WC_Stripe_Helper;
use WC_Product;
use WC_Subscriptions_Product;
use WC_Cart;

defined('ABSPATH') || exit;

class PricePerActivation
{

    /**
     * PricePerActivation constructor.
     */
    public function __construct()
    {
        add_filter('woocommerce_subscription_price_string', array($this, 'maybeCreateSubscriptionPriceString'), 10, 2);                     // cart, checkout, order received
        add_filter('woocommerce_subscriptions_product_price_string', array($this, 'maybeCreateSubscriptionsProductPriceString'), 10, 3);    // shop, product, cart, checkout
        add_filter('woocommerce_cart_subscription_string_details', array($this, 'maybeAddSubscriptionDetailString'), 10, 2);                // cart, checkout
        add_filter('wcs_new_order_created', array($this, 'maybeChangeSubscriptionOrderQuantities'), 10, 3);                                 // intercepts the newly created order
    }

    function maybeCreateSubscriptionsProductPriceString($subscriptionString, $product, $include)
    {
        $productId = $product->get_id(); // product id or variation id
        $productString = $product->get_parent_id() === 0;

        // get the variation id with the lowest price in case this is called for product with variations
        if ($productString && strpos($subscriptionString, '<span class="from">') !== false) {
            $minMaxVarData = get_post_meta($productId, '_min_max_variation_data', true);
            $productId = $minMaxVarData['min']['variation_id'];
        }

        if (!$this->isCostPerActivationProduct($productId)) {
            return $subscriptionString;
        }

        $signUpFee          = WC_Subscriptions_Product::get_sign_up_fee( $product );
		$billingInterval    = WC_Subscriptions_Product::get_interval( $product );
		$billingPeriod      = WC_Subscriptions_Product::get_period( $product );
		$subscriptionLength = WC_Subscriptions_Product::get_length( $product );
		$trialLength        = WC_Subscriptions_Product::get_trial_length( $product );
        $trialPeriod        = WC_Subscriptions_Product::get_trial_period( $product );

        $d = 'license-manager-for-woocommerce';

        $price = $include['price'];
        $sub = $include['subscription_price']
                    ? sprintf(__('%s / activation', $d), $price, wcs_get_subscription_period_strings($billingInterval, $billingPeriod)) 
                    : '';
        $period = $include['subscription_period'] 
                    ? sprintf(_n('%s, billed each %s', '%s, billed every %s', $billingInterval, $d), $sub, wcs_get_subscription_period_strings($billingInterval, $billingPeriod)) 
                    : '';
        $length = $include['subscription_length'] && $subscriptionLength != 0 
                    ? sprintf(_n('for a %s', 'for %s', $subscriptionLength, $d), wcs_get_subscription_period_strings($subscriptionLength, $billingPeriod)) 
                    : '';
        $trial = $include['trial_length'] && $trialLength != 0 
                    ? sprintf(_n('with %s %s free trial', 'with a %s-%s free trial', $trialLength, $d), $trialLength, $trialPeriod) 
                    : '';
        $fee = $include['sign_up_fee'] && $signUpFee != 0 
                    ? sprintf(__('and a %s sign-up fee', $d),  wc_price($signUpFee)) 
                    : '';

        $subscriptionString = sprintf('%s %s %s %s', $period, $length, $trial, $fee);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    function maybeCreateSubscriptionPriceString($subscriptionString, $subscriptionDetails) 
    {
        if (!isset($subscriptionDetails['use_cost_per_activation']) || $subscriptionDetails['use_cost_per_activation'] !== true) {
            return $subscriptionString;
        }

        $amount = $subscriptionDetails['recurring_amount'];
        $interval = $subscriptionDetails['subscription_interval'];
        $period = $subscriptionDetails['subscription_period'];
        $length = $subscriptionDetails['subscription_length'];

        $d = 'license-manager-for-woocommerce';

        $interval = sprintf(_n('/ activation, billed each %s', '/ activation, billed every %s', $interval, $d), wcs_get_subscription_period_strings($interval, $period));
        $length = $length != 0 
                    ? sprintf(_n('for a %s', 'for %s', $length, $d), wcs_get_subscription_period_strings($length, $period)) 
                    : '';

        $subscriptionString = sprintf('%s %s %s', $amount, $interval, $length);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    function maybeAddSubscriptionDetailString($subscriptionDetails, $cart) 
    {
        /** @var array $cartItem */
        $cartItems = $cart->get_cart();

        if (!$cartItems) {
            error_log("LMFWC: Skipped because there is no item in the cart.");
            return $subscriptionDetails;
        }

        $useCostPerActivation = false;

        /** @var array $item */
        foreach ($cartItems as $item) {
            /** @var int $productId */
            if (!$productId = $item['variation_id']) {
                $productId = $item['product_id'];
            }

            if (!$this->isCostPerActivationProduct($productId)) {
                error_log("LMFWC: Skipped product with id #{$productId} is not a licensed product, does issue a new license or does not reset activation count on renewal.");
                continue;
            }

            error_log("LMFWC: Product with id #{$productId} has cost per activation.");
            $useCostPerActivation = true;
            if ($useCostPerActivation) {
                break;
            }
        }

        $subscriptionDetails['use_cost_per_activation'] = $useCostPerActivation;
        return $subscriptionDetails;
    }

    public function maybeChangeSubscriptionOrderQuantities($newOrder, $subscription, $type)
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

            if (!$this->isCostPerActivationProduct($productId)) {
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

    private function isCostPerActivationProduct($productId) {
        return lmfwc_is_licensed_product($productId)
                    && lmfwc_get_subscription_cost_per_activation_action($productId) === 'cost_per_activation'
                    && lmfwc_get_subscription_renewal_action($productId) === 'extend_existing_license'
                    && lmfwc_get_subscription_renewal_reset_action($productId) === 'reset_license_on_renewal';
    }
}