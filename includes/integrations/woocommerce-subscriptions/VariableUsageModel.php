<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use WC_Tax;
use WC_Cart;
use WC_Order;
use Exception;
use WC_Product;
use WC_Subscription;
use WC_Stripe_Helper;
use WC_Order_Item_Product;
use WC_Subscriptions_Product;
use WC_Subscriptions_Manager;
use LicenseManagerForWooCommerce\Settings;
use LicenseManagerForWooCommerce\Settings\Subscription;
use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;

defined('ABSPATH') || exit;

/** 
 * Transforms a subscription into a variable usage model subscription. Meaning that a subscription 
 * has a fixed price per period, with an additional amount charged for each additional activation.
 * This will also change the subscription to a post-paid subscription.
 */
class VariableUsageModel
{
    /**
     * VariableUsageModel constructor.
     */
    public function __construct()
    {
        // filter for price strings throughout the shops and e-mails 
        add_filter('woocommerce_subscriptions_recurring_cart_key', array($this, 'maybeExtendRecurringCartKey'), 90, 2);                     // Change recurring cart key for variable usage model subscription
        add_filter('woocommerce_subscriptions_product_price_string_inclusions', array($this, 'maybeAddIncludeOptions'), 10, 2);             // modify include array for 'woocommerce_subscriptions_product_price_string' and 'woocommerce_subscription_price_string' hook
        add_filter('woocommerce_subscriptions_product_price_string', array($this, 'maybeCreateSubscriptionsProductPriceString'), 10, 3);    // Product Price: shop, product, cart, checkout
        add_filter('woocommerce_subscription_price_string', array($this, 'maybeCreateSubscriptionRecurringPriceString'), 10, 2);            // Recurring Price: cart, checkout, order received
        add_filter('woocommerce_cart_subscription_string_details', array($this, 'maybeAddSubscriptionDetailFromCart'), 10, 2);              // Recurring Price: cart, checkout
        add_filter('woocommerce_subscription_price_string_details', array($this, 'maybeAddSubscriptionDetailFromSubscription'), 10, 2);     // Recurring Price: subscriptions (admin), order received, email

        // actions to activate post-paid subscriptions
        add_action('woocommerce_can_subscription_be_updated_to_on-hold', array($this, 'maybeChangeSubscriptionStatusToOnHoldOrExpired'), 10, 2);
        add_action('woocommerce_can_subscription_be_updated_to_expired', array($this, 'maybeChangeSubscriptionStatusToOnHoldOrExpired'), 10, 2);
        add_action('woocommerce_scheduled_subscription_end_of_prepaid_term', array($this, 'maybeStartProcessRenewalAtEndOfSubscription'), 10, 1);
        add_action('woocommerce_cart_calculate_fees', array($this, 'maybeAddUpfrontSavings'), 20, 1);

        // calculate the subscription period total, including an extra line for additional activations if necessary
        add_filter('wcs_renewal_order_items', array($this, 'maybeAddNewLineItem'), 10, 3);  // intercepts all items just before they are converted to order items

        // FOR DEBUG PURPOSES
        //add_action('woocommerce_subscription_status_pending-cancel', array($this, 'fakeEarlyEndOfSubscription'), 1, 1); 
    }

    /**
     * Changes the recurring cart key, if the cart item is a variable usage model subscription.
     * '_<productId>' is appended to display each recurring total of variable usage model subscriptions
     * as an individual line.
     *
     * @param string $cartKey   the original cart key.
     * @param array $cartItem   an array containing certain details of the cart item.
     * @return string           the modified key if it is a variable usage model subscription.
     */
    function maybeExtendRecurringCartKey($cartKey, $cartItem) 
    {
        /** @var int $productId */
        if (!$productId = $cartItem['variation_id'])
            $productId = $cartItem['product_id'];

        if (!lmfwc_is_variable_usage_model($productId))
            return $cartKey;

        return $cartKey . '_' . $productId;
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
        $displayOptions['initial_description'] = _x('at the end of the subscription period', 'initial payment on a subscription', 'license-manager-for-woocommerce');

        // Trying to find out if the recurring amount is the string for the tax line or the recurring total with tax
        $extractedPrice = $this->extractFloatFromPriceString($displayOptions['recurring_amount']);
        if (isset($displayOptions['recurring_amount']) && $extractedPrice != 0 && WC()->cart) {
            foreach(WC()->cart->cart_contents as $value) {
                if ($value['product_id'] !== $productId)
                    continue;
                $displayOptions['is_recurring_tax_line'] = $value['line_tax'] === $extractedPrice;
                $displayOptions['is_recurring_price_including_tax'] = $value['line_total'] + $value['line_tax'] === $extractedPrice;
            }
        }

        // Get tax calculation setting if missing
        if (!isset($displayOptions['tax_calculation']))
            $displayOptions['tax_calculation'] = get_option($this->getTaxDisplayOptionName());

        // Get prices
        if ($displayOptions['tax_calculation'] === 'excl' && !$displayOptions['is_recurring_price_including_tax']) {
            $displayOptions['price'] = wcs_get_price_excluding_tax($product);
            $displayOptions['regular_price'] = wcs_get_price_excluding_tax(
                $product,
                array('price' => WC_Subscriptions_Product::get_regular_price($product))
            );
        } else {
            $displayOptions['price'] = wcs_get_price_including_tax($product);
            $displayOptions['regular_price'] = wcs_get_price_including_tax(
                $product,
                array('price' => WC_Subscriptions_Product::get_regular_price($product))
            );
        }

        // Generate price string. With sale price if on sale
        $isOnSale = $displayOptions['price'] < $displayOptions['regular_price'];
        if ($isOnSale)
            $displayOptions['price_string'] = wc_format_sale_price($displayOptions['regular_price'] * $displayOptions['quantity'], $displayOptions['price'] * $displayOptions['quantity']);
        else
            $displayOptions['price_string'] = wc_price($displayOptions['price']);

        // get quantity of cart content if we are in cart and the display string is for multiple quantities
        if ($displayOptions['quantity'] = $this->getQuantityFromPriceString($displayOptions['price_string'], $displayOptions['price']) === 1) {
            ;
        } else {
            foreach(WC()->cart->cart_contents as $value) {
                if ($value['product_id'] !== $productId)
                    continue;
                $displayOptions['quantity'] = $value['quantity'];
            }
        }

        // Get included activations from product
        $displayOptions['max_activations'] = lmfwc_get_maximum_included_activations($productId);

        // Calculate the regular and sale price per activation
        $displayOptions['price_per_activation'] = $displayOptions['price'] / $displayOptions['max_activations'];
        $displayOptions['regular_price_per_activation'] = $displayOptions['regular_price'] / $displayOptions['max_activations'];

        $pricePerActivationString = wc_price(
            $displayOptions['price_per_activation'], 
            array('decimals' => lmfwc_get_activation_price_decimals())
        );
        $regularPricePerActivationString = '<del>' . wc_price(
            $displayOptions['regular_price_per_activation'], 
            array('decimals' => lmfwc_get_activation_price_decimals())
        ) . '</del>';

        // Price per activation to display
        $displayOptions['price_per_activation_string'] = $isOnSale
                ? $regularPricePerActivationString . ' ' . $pricePerActivationString 
                : $pricePerActivationString;

        // Recalculate max_activations based on product quantity
        $displayOptions['max_activations'] *= $displayOptions['quantity'];

        // generate max activation formatted string
        $displayOptions['max_activations_string'] = number_format($displayOptions['max_activations'], 0, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());

        // Get tax rate for product and calculate tax per activation
        $taxRates = WC_Tax::get_rates($product->get_tax_class());
        if (!empty($taxRates)) {
            $displayOptions['tax_rate'] = reset($taxRates);
            $displayOptions['tax_per_activation'] = $displayOptions['price_per_activation'] * ($displayOptions['tax_rate']['rate'] / 100);
            $displayOptions['tax_per_activation_string'] = wc_price(
                $displayOptions['tax_per_activation'], 
                array('decimals' => lmfwc_get_activation_price_decimals())
            );
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

        $signUpFee          = WC_Subscriptions_Product::get_sign_up_fee( $product );
		$billingInterval    = WC_Subscriptions_Product::get_interval( $product );
		$billingPeriod      = WC_Subscriptions_Product::get_period( $product );
		$subscriptionLength = WC_Subscriptions_Product::get_length( $product );
		$trialLength        = WC_Subscriptions_Product::get_trial_length( $product );
        $trialPeriod        = WC_Subscriptions_Product::get_trial_period( $product );

        /* translators: 1: amount to pay per period 2: subscription period (example: "9,95€ / month" or "19,95€ every 3 months") */
        $pricePerPeriod = $include['subscription_price'] && $include['subscription_period'] 
                    ? sprintf(_nx('%1$s / %2$s', '%1$s every %2$s', $billingInterval, 'Contains price per billing period', 'license-manager-for-woocommerce'), $include['price_string'], wcs_get_subscription_period_strings($billingInterval, $billingPeriod)) 
                    : '';
        /* translators: 1: included activations 2: activation name (example: "with 10.000 activations included") */
        $includedActivations = $include['max_activations'] > 1 && $include['display_included_activations']
                    ? sprintf(_x('with %1$s %2$s included', 'How many activations are included', 'license-manager-for-woocommerce'), $include['max_activations_string'], lmfwc_get_activation_name_string($include['max_activations']))
                    : '';
        /* translators: 1: amount to pay per activation 2: activation name (example: "+ € 0,003 per additional activation") */
        $singleActivationPrice = $include['display_single_activation_price']
                    ? sprintf(_x('+ %1$s per additional %2$s', 'Cost of additional activations', 'license-manager-for-woocommerce'), $include['price_per_activation_string'], lmfwc_get_activation_name_string())
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
    function maybeCreateSubscriptionRecurringPriceString($subscriptionString, $subscriptionDetails) 
    {
        if (!isset($subscriptionDetails['use_variable_usage_type']) || $subscriptionDetails['use_variable_usage_type'] === false) {
            return $subscriptionString;
        }

        // Return an other string if it is a recurring tax line
        if (isset($subscriptionDetails['is_recurring_tax_line']) && $subscriptionDetails['is_recurring_tax_line']) {
            return $this->createRecurringTaxLine($subscriptionString, $subscriptionDetails);
        }

        $amount = $subscriptionDetails['price_string'];
        $interval = $subscriptionDetails['subscription_interval'];
        $period = $subscriptionDetails['subscription_period'];
        $length = $subscriptionDetails['subscription_length'];

        /* translators: 1: amount to pay per period 2: subscription period (example: "9,95€ / month" or "19,95€ every 3 months") */
        $pricePerPeriod = sprintf(_nx('%1$s / %2$s', '%1$s every %2$s', $interval, 'Contains price per billing period', 'license-manager-for-woocommerce'), $amount, wcs_get_subscription_period_strings($interval, $period));
        /* translators: 1: included activations 2: activation name (example: "with 10000 activations included") */
        $includedActivations = $subscriptionDetails['max_activations'] > 1
                    ? sprintf(_x('with %1$s %2$s included', 'How many activations are included', 'license-manager-for-woocommerce'), $subscriptionDetails['max_activations_string'], lmfwc_get_activation_name_string($subscriptionDetails['max_activations']))
                    : '';
        /* translators: 1: amount to pay per activation 2: activation name (example: "+ € 0,003 per additional activation") */
        $singleActivationPrice = sprintf(_x('+ %1$s per additional %2$s', 'Cost of additional activations', 'license-manager-for-woocommerce'), $subscriptionDetails['price_per_activation_string'], lmfwc_get_activation_name_string());
        /* translators: %s: subscription period (example: "for a month" or "for 3 months") */
        $length = $length != 0 
                    ? sprintf(_nx('for a %s', 'for %s', $length, 'The length of the subscription', 'license-manager-for-woocommerce'), wcs_get_subscription_period_strings($length, $period)) 
                    : '';

        $subscriptionString = sprintf('%s %s %s %s', $pricePerPeriod, $includedActivations, $singleActivationPrice, $length);
        $subscriptionString = trim(str_replace('  ', ' ', $subscriptionString));

        return $subscriptionString;
    }

    function createRecurringTaxLine($subscriptionString, $include) {
        /* translators: 1: tax per activation 3: activation name (example: "+ € 0,001 per additional activation") */
        $singleActivationTax = isset($include['tax_per_activation_string']) 
                    ? sprintf(_x('+ %1$s per additional %2$s', 'Cost of additional activations', 'license-manager-for-woocommerce'), $include['tax_per_activation_string'], lmfwc_get_activation_name_string()) 
                    : '';

        $subscriptionString = sprintf('%s %s', $subscriptionString, $singleActivationTax);
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
     * Add additional activation item based on license activations, if the items 
     * contained in the order are setup with the 'variable_usage_type' meta.
     *
     * @param WC_Order_Item[] $items            a list of order items from the parent order
     * @param WC_Order $newOrder                the newly created order
     * @param WC_Subscription $subscription     the subscription of the created order
     * @return WC_Order_Item[]                  a list of order items that will be integrated in the new order
     */
    public function maybeAddNewLineItem($items, $newOrder, $subscription)
    {
        if (!wcs_is_subscription($subscription)) {
            error_log("LMFWC: Skipped because is not valid subscription renewal order.");
            return $newOrder;
        }

        /** @var int $parentOrderId */
        $parentOrderId = $subscription->get_parent_id();

        if (!$items) {
            error_log("LMFWC: Skipped order #{$newOrder->get_id()} because no items are contained.");
            return $items;
        }
        
        foreach ($items as $item) {
            if (!$item->is_type('line_item'))
                continue;

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

            if ($activationCount <= $includedActivations) {
                error_log("LMFWC: Skipped because activation count is less or equal to the included activations.");
                continue;
            }

            $activationDelta = $activationCount - $includedActivations;
            $activationPrice = $subscriptionPrice / $includedActivations;
            $newTotal = $activationPrice * $activationDelta;
            $newTotal = round($newTotal, wc_get_price_decimals());

            if ((bool) Settings::get(Subscription::DISPLAY_ADDITIONAL_ACTIVATION_COUNT_FIELD_NAME, Settings::SECTION_SUBSCRIPTION)) {
                $newQuantity = 1;
                /* translators: 1: additional activation count 2: activation name (example: "123 additional activations consumed at € 0,09/activation") */
                $nameAddition = sprintf(
                    _x('%1$s additional %2$s consumed at %3$s/%4$s', 'Line item name for additional activations with count', 'license-manager-for-woocommerce'), 
                    $activationDelta, 
                    lmfwc_get_activation_name_string($activationDelta),
                    wc_price($activationPrice, array('decimals' => lmfwc_get_activation_price_decimals())), 
                    lmfwc_get_activation_name_string());
            } else {
                $newQuantity = $activationDelta;
                /* translators: 1: activation name (example: "Additional activations consumed") */
                $nameAddition = sprintf(_x('Additional %1$s consumed', 'Line item name for additional activations', 'license-manager-for-woocommerce'), lmfwc_get_activation_name_string($activationDelta));
            }

            if (!$newOrder->meta_exists('_has_additional_activation_item'))
                $newOrder->add_meta_data('_has_additional_activation_item', true);

            // create a new item with additional activation
            $newItem = new WC_Order_Item_Product();
            $newItem->set_order_id($item->get_order_id());
            $newItem->set_product_id($item->get_product_id());
            $newItem->set_variation_id($item->get_variation_id());
            $newItem->set_tax_class($item->get_tax_class());
            $newItem->set_quantity($newQuantity);
            $newItem->set_name($nameAddition);
            $newItem->set_subtotal($newTotal);
            $newItem->set_total($newTotal);
            $newItem->add_meta_data('_is_additional_activation_item', true);

            // calculate taxes if necessary
            if ('0' !== $item->get_tax_class() && $item->get_tax_status() === 'taxable' && wc_tax_enabled()) {
                $rates = WC_Tax::get_rates($item->get_tax_class());
                $taxes['total'] = WC_Tax::calc_tax($newItem->get_total(), $rates);
                $taxes['subtotal'] = WC_Tax::calc_tax($newItem->get_subtotal(), $rates);
                $newItem->set_taxes($taxes);
            }
            
            $items[] = $newItem;

            error_log("LMFWC: The new total of the item #{$item->get_id()} is {$newTotal}.");
        }

        $lineItems = array_filter($items, function($it) {
            return $it->is_type('line_item');
        });

        $taxItems = array_filter($items, function($it) {
            return $it->is_type('tax');
        });

        $lineItemTotals = 0;
        $lineItemTotalsTax = 0;
        foreach ($lineItems as $item) {
            $lineItemTotals += $item->get_total();

            // calculate taxes if necessary
            if ('0' !== $item->get_tax_class() && $item->get_tax_status() === 'taxable' && wc_tax_enabled()) {
                $lineItemTotals += $item->get_total_tax();
                $lineItemTotalsTax += $item->get_total_tax();

                // add the tax totals to the corresponding tax order items
                if ($item->get_meta('_is_additional_activation_item')) {
                    $rates = WC_Tax::get_rates($item->get_tax_class());
                    foreach ($rates as $rateId => $ignore) {
                        foreach ($taxItems as $taxItem) {
                            if ($taxItem->get_rate_id() === $rateId) {
                                $tax = (float) $taxItem->get_tax_total();
                                $taxItem->set_tax_total($tax + $item->get_total_tax());
                            }
                        }
                    }
                }
            }
        }

        $newOrder->set_total($lineItemTotals);
        $newOrder->set_cart_tax($lineItemTotalsTax);

        return $items;
    }

    /**
     * Ignores the 'canStatusBeChanged' algorithm if necessary. This is required for 
     * the process_renewal method to work, since it needs to change the status from 
     * 'cancelled' to 'on-hold' and this is not allowed by default.
     *
     * @param bool $canBeUpdated                the original boolean value determined by wc subscription plugin
     * @param WC_Subscription $subscription     the subscription 
     * @return bool                             the updated value
     */
    public function maybeChangeSubscriptionStatusToOnHoldOrExpired($canBeUpdated, $subscription)
    {
        if ($subscription->get_meta('_ignore_can_status_be_updated'))
            return true;
        
        return $canBeUpdated;
    }

    /**
     * Starts a renewal process right at the end of the subscription (after the subscription
     * has been cancelled by the customer or admin). Also starts the payment of the newly
     * created renewal order.
     *
     * @param int $subscription_id  the id of the ending/cancelled subscription
     * @return void
     */
    public function maybeStartProcessRenewalAtEndOfSubscription($subscription_id)
    {
        $subscription = wcs_get_subscription($subscription_id);

        /** @var array $items */
        $items = $subscription->get_items();

        if (!$items) {
            error_log("LMFWC: Skipped because there is no item in the subscription.");
            return $subscriptionDetails;
        }

        $continue = false;

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
            $continue = true;
            break;
        }

        if (!$continue)
            return;

        $order_note = _x('Status changed due to post-paid subscription. Reached end of subscription:', 'used in order note as reason for why subscription status changed for post-paid subscriptions', 'woocommerce-subscriptions');
        
        // add a meta flag that we use to ignore the status during process renewal
        $subscription->add_meta_data('_ignore_can_status_be_updated', true);
        $subscription->save();
        
        // call static method because it is not possible to do_action on 'woocommerce_scheduled_subscription_payment',
        // since we do not have an active status at this point
        $renewal_order = WC_Subscriptions_Manager::process_renewal($subscription_id, 'cancelled', $order_note);
        
        // trigger the payment 
        do_action('woocommerce_scheduled_subscription_payment', $subscription_id);
        
        // set the status to expired, since we are not able to set it to cancelled.
        // but it seems to have the same result.
        $subscription->update_status('expired');

        // delete the flag we set at the beginning, because it is no longer needed.
        $subscription->delete_meta_data('_ignore_can_status_be_updated');
        $subscription->save();
    }

    /**
     * Since there is no functionality to make subscriptions post-paid, we will add a discount
     * at first payment for the amount of the variable usage model subscription.
     *
     * @param WC_Cart $cart     the current cart 
     * @return void
     */
    public function maybeAddUpfrontSavings($cart)
    {
        if (empty($cart->recurring_cart_key)) {
            $upFrontDiscount = 0;
            $taxable = false;
            $taxClass = '';
            
            // Loop Through cart items
            foreach ($cart->get_cart() as $key => $cartItem) {
                /** @var int $productId */
                if (!$productId = $cartItem['variation_id'])
                    $productId = $cartItem['product_id'];

                if (!lmfwc_is_variable_usage_model($productId))
                    continue;

                $upFrontDiscount += $cartItem['line_total'];
                if (get_option('woocommerce_prices_include_tax') === 'yes')
                    $upFrontDiscount += $cartItem['line_tax'];
                $taxable = $cartItem['data']->get_tax_status() === 'taxable';
                $taxClass = $cartItem['data']->get_tax_class();
            }

            $cart->add_fee(_x('Up front savings', 'license-manager-for-woocommerce'), -$upFrontDiscount, $taxable, $taxClass);
        }
    }

    private function addDetailBasedOnItems($subscriptionDetails, $items) 
    {
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
            if ($price > 0) // Avoid division by zero
                return (int) ($this->extractFloatFromPriceString($string) / $price) ?: 1;
            return 1;
        } catch(Exception $e) {
            return 1;
            error_log("Warning: (LMFWC) Could not determine quantity from total price string.");
        }
    }

    private function extractFloatFromPriceString($string) 
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
            return floatval($integer . '.' . $fraction);
        } catch(Exception $e) {
            error_log("Warning: (LMFWC) Could not extract float price from total price string.");
        }
    }

    private function getTaxDisplayOptionName() {
        if (is_cart() || is_checkout() || is_account_page())
            return 'woocommerce_tax_display_cart';
        else 
            return 'woocommerce_tax_display_shop';
    }

    // FOR DEBUG PURPOSES
    public function fakeEarlyEndOfSubscription($subscription)
    {
        // fake end
        $end = date('Y-m-d H:i:s', strtotime($subscription->get_date('cancelled') . ' + 3 seconds'));
        $subscription->update_dates(array('end' => $end));
    }
}