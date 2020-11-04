<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use WC_Cart;
use WC_Order;
use WC_Product;
use WC_Subscription;
use WC_Stripe_Helper;
use WC_Order_Item_Product;
use WC_Subscriptions_Product;
use LicenseManagerForWooCommerce\Settings;
use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;

defined('ABSPATH') || exit;

/** 
 * Transforms a subscription into a cost per activation subscription. 
 * 
 * WARNING: There are some errors concerning the cart total, when having 2 of the same 
 * subscription in the cart. Furthermore the cost per activation calculation is only 
 * done on renewal orders, this means that the activation price is charged once for the 
 * first order (parent order). To avoid this behaviour a trail period could be set on 
 * the product.
 */
class PricePerActivation
{

    /**
     * @var string
     */
    public const DEFAULT_ACTIVATION_NAME = 'activation';

    /**
     * @var string
     */
    public $activationName;

    /**
     * PricePerActivation constructor.
     */
    public function __construct()
    {
        $this->activationName = Settings::get('lmfwc_activation_name_string') ?: self::DEFAULT_ACTIVATION_NAME;

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

        $price = $include['price'];
        /* translators: 1: amount to pay per 2: activation name 3: subscription period (example: "9,95€ / activation, billed each month" or "19,95€ / activation, billed every 3 months") */
        $period = $include['subscription_price'] && $include['subscription_period'] 
                    ? sprintf(_nx('%1$s / %2$s, billed each %3$s', '%1$s / %2$s, billed every %3$s', $billingInterval, 'Contains price per activation and billing period', 'license-manager-for-woocommerce'), $price, $this->activationName, wcs_get_subscription_period_strings($billingInterval, $billingPeriod)) 
                    : '';
        /* translators: %s: subscription period (example: "for a month" or "for 3 months") */
        $length = $include['subscription_length'] && $subscriptionLength != 0 
                    ? sprintf(_nx('for a %s', 'for %s', $subscriptionLength, 'The length of the subscription', 'license-manager-for-woocommerce'), wcs_get_subscription_period_strings($subscriptionLength, $billingPeriod)) 
                    : '';
        /* translators: 1: trial length 2: trial period (example: "with 1 month free trial" or "with a 3-week free trial") */
        $trial = $include['trial_length'] && $trialLength != 0 
                    ? sprintf(_nx('with %1$s %2$s free trial', 'with a %1$s-%2$s free trial', $trialLength, 'Describes the length of the trail period of the subscription', 'license-manager-for-woocommerce'), $trialLength, $trialPeriod) 
                    : '';
        /* translators: %s: fee price (example: "and a 9,99€ sign-up fee") */
        $fee = $include['sign_up_fee'] && $signUpFee != 0 
                    ? sprintf(_x('and a %s sign-up fee', 'The sign up fee pricing', 'license-manager-for-woocommerce'),  wc_price($signUpFee)) 
                    : '';

        $subscriptionString = sprintf('%s %s %s %s', $period, $length, $trial, $fee);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    function maybeCreateSubscriptionPriceString($subscriptionString, $subscriptionDetails) 
    {
        $amount = $subscriptionDetails['recurring_amount'];
        $interval = $subscriptionDetails['subscription_interval'];
        $period = $subscriptionDetails['subscription_period'];
        $length = $subscriptionDetails['subscription_length'];
        $trialLength = $subscriptionDetails['trial_length'];

        if (isset($subscriptionDetails['use_cost_per_activation']) && $subscriptionDetails['use_cost_per_activation'] === false) {
            return $subscriptionString;
        } else if (!isset($subscriptionDetails['use_cost_per_activation'])) {

            // After the order is completed and the order e-mail is being generated, the 
            // 'woocommerce_cart_subscription_string_details' hook will not be executed, 
            // so we have a hart time finding out if the current price string should contain
            // a 'cost_per_activation' price string. We can try to find out by getting all 
            // product ids that match the current subscription details and will continue with 
            // our price string formatting, only if we have exactly one matching product
            // or product variation. This is not an ideal solution as this can not always
            // guarantees a save match. 
            // In the edit subscription admin panel this can also fail, if the product meta 
            // data has changed since the subscription was ordered or if the data does not 
            // match the subscription details anymore.
            $allIds = get_posts(array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'publish',
                'numberposts' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    array('key' => 'lmfwc_subscription_cost_per_activation_action',
                          'value' => 'cost_per_activation', 
                          'compare' => '=',
                    ),
                    array('key' => '_subscription_period_interval',
                          'value' => $interval, 
                          'compare' => '=',
                    ),
                    array('key' => '_subscription_period',
                          'value' => $period, 
                          'compare' => '=',
                    )
                ),
            ));

            $removeList = array();
            foreach ($allIds as $id) {
                $price = wc_get_product($id)->get_price();
                if (floatval($amount) !== floatval($price)) {
                    $removeList[] = $id;
                }
            }
            $allIds = array_diff($allIds, $removeList);

            // return the current formatted string if there are none or multiple ids 
            // that match the subscription details
            if (count($allIds) !== 1) {
                return $subscriptionString;
            }
        }

        /* translators: 1: amount to pay per 2: activation name 3: subscription period (example: "9,95€ / activation, billed each month" or "19,95€ / activation, billed every 3 months") */
        $interval = sprintf(_nx('%1$s / %2$s, billed each %3$s', '%1$s / %2$s, billed every %3$s', $interval, 'Contains price per activation and billing period', 'license-manager-for-woocommerce'), $amount, $this->activationName, wcs_get_subscription_period_strings($interval, $period));
        /* translators: %s: subscription period (example: "for a month" or "for 3 months") */
        $length = $length != 0 
                    ? sprintf(_nx('for a %s', 'for %s', $length, 'The length of the subscription', 'license-manager-for-woocommerce'), wcs_get_subscription_period_strings($length, $period)) 
                    : '';

        $subscriptionString = sprintf('%s %s', $interval, $length);
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

        $items = $newOrder->get_items();
        if (!$items) {
            error_log("LMFWC: Skipped order #{$newOrder->get_id()} because no items are contained.");
        }
        
        foreach ($items as $item) {

            /** @var int $productId */
            if (!$productId = $item->get_variation_id()) {
                $productId = $item->get_product_id();
            }

            if (!$this->isCostPerActivationProduct($productId)) {
                error_log("LMFWC: Skipped item #{$item->get_id()} because product with id #{$productId} is not a licensed product, does issue a new license or does not reset {$this->activationName} count on renewal.");
                continue;
            }

            /** @var false|LicenseResourceModel[] $licenses */
            $licenses = lmfwc_get_licenses(
                array(
                    'order_id' => $parentOrderId,
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
            
            /** @var false|WC_Order $parentOrder */
            $parentOrder = wc_get_order($parentOrderId);

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