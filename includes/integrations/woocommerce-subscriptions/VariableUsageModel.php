<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use WC_Cart;
use WC_Order;
use Exception;
use WC_Product;
use WC_Subscription;
use WC_Stripe_Helper;
use WC_Order_Item_Product;
use WC_Subscriptions_Product;
use LicenseManagerForWooCommerce\Settings;
use LicenseManagerForWooCommerce\Settings\Subscription;
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
class VariableUsageModel
{
    /**
     * VariableUsageModel constructor.
     */
    public function __construct()
    {
        add_filter('woocommerce_subscriptions_product_price_string_inclusions', array($this, 'maybeAddIncludeOptions'), 10, 2);             // modify include array for 'woocommerce_subscriptions_product_price_string' hook
        add_filter('woocommerce_subscriptions_product_price_string', array($this, 'maybeCreateSubscriptionsProductPriceString'), 10, 3);    // shop, product, cart, checkout
        add_filter('woocommerce_subscription_price_string', array($this, 'maybeCreateSubscriptionPriceString'), 10, 2);                     // cart, checkout, order received
        add_filter('woocommerce_cart_subscription_string_details', array($this, 'maybeAddSubscriptionDetailFromCart'), 10, 2);              // cart, checkout
        add_filter('woocommerce_subscription_price_string_details', array($this, 'maybeAddSubscriptionDetailFromSubscription'), 10, 2);     // subscriptions (admin), order received, email?
        add_filter('wcs_new_order_created', array($this, 'maybeChangeSubscriptionOrderQuantities'), 10, 3);                                 // intercepts the newly created order
    }

    function maybeAddIncludeOptions($displayOptions, $product) 
    {
        $productId = $product->get_id();
        if (!lmfwc_is_variable_usage_model($productId)) {
            return $displayOptions;
        }

        $displayOptions['display_included_activations'] = (bool) Settings::get(Subscription::SHOW_MAXIMUM_INCLUDED_ACTIVATIONS_FIELD_NAME, Settings::SECTION_SUBSCRIPTION);
        $displayOptions['display_single_activation_price'] = (bool) Settings::get(Subscription::SHOW_SINGLE_ACTIVATION_PRICE_FIELD_NAME, Settings::SECTION_SUBSCRIPTION);
        $displayOptions['trial_length'] = false;

        $displayOptions['max_activations'] = lmfwc_get_maximum_included_activations($productId);

        // Calculate the price per activation
        $displayOptions['price_per_activation'] = wc_price(
            WC_Subscriptions_Product::get_price($product) / $displayOptions['max_activations'], 
            array('decimals' => lmfwc_get_activation_price_decimals())
        );

        // If on sale, calculate the regular price per activation
        if (WC_Subscriptions_Product::get_price( $product ) < WC_Subscriptions_Product::get_regular_price( $product )) {
            $displayOptions['regular_price_per_activation'] = '<del>' . wc_price(
                WC_Subscriptions_Product::get_regular_price($product) / $displayOptions['max_activations'], 
                array('decimals' => lmfwc_get_activation_price_decimals())
            ) . '</del>';
        }

        return $displayOptions;
    }

    /**
     * Overwrite price string for a cost per activation subscription product.
     * This is executed on the following pages: shop, product, cart, checkout
     *
     * @param string $subscriptionString    a formatted price string form WooCommerce Subscription
     * @param WC_Product $product           the product
     * @param array $include                an array that contains switches for each segment
     * @return string                       the modified $subscriptionString
     */
    function maybeCreateSubscriptionsProductPriceString($subscriptionString, $product, $include)
    {
        $productId = $product->get_id(); // product id or variation id
        $isMinimalVariation = $product->get_parent_id() === 0 && strpos($subscriptionString, '<span class="from">') !== false;

        // get the variation id with the lowest price in case this is called for product with variations
        // and a "From: subscription string is shown"
        if ($isMinimalVariation) {
            $minMaxVarData = get_post_meta($productId, '_min_max_variation_data', true);
            $productId = $minMaxVarData['min']['variation_id'];
            $include = apply_filters( 'woocommerce_subscriptions_product_price_string_inclusions', $include, wc_get_product($productId) );
        }
        
        if (!lmfwc_is_variable_usage_model($productId)) {
            return $subscriptionString;
        }

        $price              = WC_Subscriptions_Product::get_price( $product );
        $signUpFee          = WC_Subscriptions_Product::get_sign_up_fee( $product );
		$billingInterval    = WC_Subscriptions_Product::get_interval( $product );
		$billingPeriod      = WC_Subscriptions_Product::get_period( $product );
		$subscriptionLength = WC_Subscriptions_Product::get_length( $product );
		$trialLength        = WC_Subscriptions_Product::get_trial_length( $product );
        $trialPeriod        = WC_Subscriptions_Product::get_trial_period( $product );

        $priceString = $include['price'];
        $maxActivations = $include['max_activations'] * $this->getQuantityFromPriceString($priceString, $price);
        $maxActivationsString = number_format($maxActivations, 0, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
        $pricePerActivation = isset($include['regular_price_per_activation']) 
                ? $include['regular_price_per_activation'] . ' ' . $include['price_per_activation'] 
                : $include['price_per_activation'];

        /* translators: 1: amount to pay per period 2: subscription period (example: "9,95€ / month" or "19,95€ every 3 months") */
        $pricePerPeriod = $include['subscription_price'] && $include['subscription_period'] 
                    ? sprintf(_nx('%1$s / %2$s', '%1$s every %2$s', $billingInterval, 'Contains price per billing period', 'license-manager-for-woocommerce'), $priceString, wcs_get_subscription_period_strings($billingInterval, $billingPeriod)) 
                    : '';
        /* translators: 1: included activations 2: activation name (example: "with 10000 activations included") */
        $includedActivations = $maxActivations > 1 && $include['display_included_activations']
                    ? sprintf(_x('with %1$s %2$s included', 'How many activations are included', 'license-manager-for-woocommerce'), $maxActivationsString, lmfwc_get_activation_name_string($maxActivations))
                    : '';
        /* translators: 1: amount to pay per activation 2: activation name (example: "+ € 0,003 per additional activation") */
        $singleActivationPrice = $include['display_single_activation_price']
                    ? sprintf(_x('+ %1$s per additional %2$s', 'Cost of additional activations', 'license-manager-for-woocommerce'), $pricePerActivation, lmfwc_get_activation_name_string())
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

        $subscriptionString = sprintf('%s %s %s %s %s %s', $pricePerPeriod, $includedActivations, $singleActivationPrice, $length, $trial, $fee);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    /**
     * Overwrites the shortened formatted price string, if the product is a cost per activation
     * subscription product.
     * This is executed on the following pages: cart, checkout, order received, email
     *
     * @param string $subscriptionString    a formatted price string form WooCommerce Subscription
     * @param array $subscriptionDetails    an array containing certain details of the subscription 
     * @return string                       the modified $subscriptionString
     */
    function maybeCreateSubscriptionPriceString($subscriptionString, $subscriptionDetails) 
    {
        if (!isset($subscriptionDetails['use_variable_usage_type']) || $subscriptionDetails['use_variable_usage_type'] === false) {
            return $subscriptionString;
        }

        $amount = $subscriptionDetails['recurring_amount'];
        $interval = $subscriptionDetails['subscription_interval'];
        $period = $subscriptionDetails['subscription_period'];
        $length = $subscriptionDetails['subscription_length'];

        /* translators: 1: amount to pay per period 2: subscription period (example: "9,95€ / month" or "19,95€ every 3 months") */
        $pricePerPeriod = sprintf(_nx('%1$s / %2$s', '%1$s every %2$s', $interval, 'Contains price per billing period', 'license-manager-for-woocommerce'), $amount, wcs_get_subscription_period_strings($interval, $period));
        /* translators: %s: subscription period (example: "for a month" or "for 3 months") */
        $length = $length != 0 
                    ? sprintf(_nx('for a %s', 'for %s', $length, 'The length of the subscription', 'license-manager-for-woocommerce'), wcs_get_subscription_period_strings($length, $period)) 
                    : '';

        $subscriptionString = sprintf('%s %s', $pricePerPeriod, $length);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    /**
     * Adds a boolean to the $subscriptionDetails array if the cart contains a 
     * subscription product that is setup with 'variable_usage_type' meta. The 
     * boolean is added to use on the 'woocommerce_subscription_price_string'
     * hook.
     * This is executed on the following pages: cart, checkout
     *
     * @param array $subscriptionDetails    an array containing certain details of the subscription 
     * @param WC_Cart $cart                 the cart 
     * @return array                        the modified $subscriptionDetails array
     */
    function maybeAddSubscriptionDetailFromCart($subscriptionDetails, $cart) 
    {
        /** @var array $cartItems */
        $cartItems = $cart->get_cart();

        if (!$cartItems) {
            error_log("LMFWC: Skipped because there is no item in the cart.");
            return $subscriptionDetails;
        }

        return $this->addDetailBasedOnItems($subscriptionDetails, $cartItems);
    }
    
    /**
     * Same as @see maybeAddSubscriptionDetailFromCart()
     * This is executed on the following pages: subscriptions (admin), order received, email?
     *
     * @param array $subscriptionDetails    an array containing certain details of the subscription 
     * @param WC_Subscription $subscription the subscription 
     * @return array                        the modified $subscriptionDetails array
     */
    function maybeAddSubscriptionDetailFromSubscription($subscriptionDetails, $subscription)
    {
        /** @var array $items */
        $items = $subscription->get_items();

        if (!$items) {
            error_log("LMFWC: Skipped because there is no item in the subscription.");
            return $subscriptionDetails;
        }

        return $this->addDetailBasedOnItems($subscriptionDetails, $items);
    }

    /**
     * Changes the quantity and recalculates totals based on license activations, if the items 
     * contained in the order are setup with the 'variable_usage_type' meta.
     *
     * @param WC_Order $newOrder                the newly created order
     * @param WC_Subscription $subscription     the subscription of the created order
     * @param string $type                      a string of order type, should be 'renewal_order'
     * @return WC_Order                         the modified order
     */
    public function maybeChangeSubscriptionOrderQuantities($newOrder, $subscription, $type)
    {
        if ($type !== 'renewal_order' || !wcs_is_subscription($subscription)) {
            error_log("LMFWC: Skipped because is not valid subscription renewal order.");
            return $newOrder;
        }

        /** @var int $parentOrderId */
        $parentOrderId = $subscription->get_parent_id();

        /** @var WC_Order_Item[] $items */
        $items = $newOrder->get_items();
        if (!$items) {
            error_log("LMFWC: Skipped order #{$newOrder->get_id()} because no items are contained.");
        }

        foreach ($items as $item) {

            /** @var int $productId */
            if (!$productId = $item->get_variation_id()) {
                $productId = $item->get_product_id();
            }

            if (!lmfwc_is_variable_usage_model($productId)) {
                error_log("LMFWC: Skipped item #{$item->get_id()} because product with id #{$productId} is not a licensed product, does issue a new license or does not reset activation count on renewal.");
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

            $subscriptionPrice = (float) $item->get_subtotal();
            error_log("LMFWC: Subscription cost is: {$subscriptionPrice}");

            $includedActivations = (int) lmfwc_get_maximum_included_activations($productId) * $item->get_quantity();
            error_log("LMFWC: Amount of included activations: {$includedActivations}");

            $activationCount = 0;

            /** @var LicenseResourceModel $license */
            foreach ($licenses as $license) {
                $activationCount += $license->getTimesActivated();
                error_log("LMFWC: License activated {$license->getTimesActivated()} times.");
            }

            if ($activationCount > $includedActivations) {
                // $item->set_quantity($activationCount); // TODO decide if the quantity should be the number of consumed credits

                $newTotal = ($subscriptionPrice / $includedActivations) * $activationCount;
                $item->set_subtotal($newTotal);
                $item->set_total($newTotal);

                $activationDelta = $activationCount - $includedActivations;
                /* translators: 1: number of additional activations used 2: activation name (example: "+ 3 additional activations") */
                $nameAddition = sprintf(_x('+ %1$s additional %2$s consumed.', 'Name appendage for additional activations', 'license-manager-for-woocommerce'), $activationDelta, lmfwc_get_activation_name_string($activationDelta));
                $item->set_name($item->get_name() . '<br>' . $nameAddition);

                $item->save();

                error_log("LMFWC: The new total of the item #{$item->get_id()} is {$newTotal}.");
            } else {
                error_log("LMFWC: The total of the item #{$item->get_id()} is not changed.");
            }
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

    private function addDetailBasedOnItems($subscriptionDetails, $items) {
        $useCostPerActivation = false;

        /** @var array $item */
        foreach ($items as $id => $item) {
            /** @var int $productId */
            if (!$productId = $item['variation_id']) {
                $productId = $item['product_id'];
            }

            if (!lmfwc_is_variable_usage_model($productId)) {
                error_log("LMFWC: Skipped product with id #{$productId} is not a licensed product, does issue a new license or does not reset activation count on renewal.");
                continue;
            }

            error_log("LMFWC: Product with id #{$productId} has cost per activation.");
            $useCostPerActivation = true;
            break;
        }

        $subscriptionDetails['use_variable_usage_type'] = $useCostPerActivation;

        return apply_filters('woocommerce_subscriptions_product_price_string_inclusions', $subscriptionDetails, wc_get_product($productId));
    }

    private function getQuantityFromPriceString($string, $price) 
    {
        try {
            $decimals = wc_get_price_decimals();

            $dom = new \DOMDocument;
            // To get rid of "DOMDocument::loadHTML(): Tag bdi invalid in Entity" warning.
            libxml_use_internal_errors(true); 
            $dom->loadHTML(str_replace('&nbsp;', '', $string));
            // clear errors
            libxml_clear_errors();

            $nodes = $dom->getElementsByTagName('bdi');
            if ($nodes->length == 0)
                throw new Exception();

            $p = null;
            foreach ($nodes->item($nodes->length - 1)->childNodes as $node) {
                if ($node->nodeName !== '#text')
                    continue;

                $p = trim($node->data);
            }

            if (empty($p))
                throw new Exception();

            $fraction = substr($p, -$decimals);
            $integer = substr($p, 0, -$decimals);
            $integer = str_replace(',', '', $integer);
            $integer = str_replace('.', '', $integer);
            $p = (float) $integer . '.' . $fraction;

            return (int) ($p / $price) ?: 1;
        } catch(Exception $e) {
            return 1;
            error_log("Warning: (LMFWC) Could not determine quantity from total price string.");
        }
    }
}