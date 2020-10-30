<?php
/**
 * LicenseManager for WooCommerce Core Functions
 *
 * General core functions available on both the front-end and admin.
 */

use LicenseManagerForWooCommerce\Repositories\Resources\License as LicenseResourceRepository;

defined('ABSPATH') || exit;

/**
 * Checks if a license key already exists inside the database table.
 *
 * @param string   $licenseKey
 * @param null|int $licenseKeyId
 *
 * @return bool
 */
function lmfwc_duplicate($licenseKey, $licenseKeyId = null)
{
    $duplicate = false;
    $hash      = apply_filters('lmfwc_hash', $licenseKey);

    // Add action
    if ($licenseKeyId === null) {
        $query = array('hash' => $hash);

        if (LicenseResourceRepository::instance()->findBy($query)) {
            $duplicate = true;
        }
    }

    // Update action
    elseif (is_numeric($licenseKeyId)) {
        $table = LicenseResourceRepository::instance()->getTable();

        $query = "
            SELECT
                id
            FROM
                {$table}
            WHERE
                1=1
                AND hash = '{$hash}'
                AND id NOT LIKE {$licenseKeyId}
            ;
        ";

        if (LicenseResourceRepository::instance()->query($query)) {
            $duplicate = true;
        }
    }

    return $duplicate;
}
add_filter('lmfwc_duplicate', 'lmfwc_duplicate', 10, 2);

/**
 * Generates a random hash.
 *
 * @return string
 */
function lmfwc_rand_hash()
{
    if ($hash = apply_filters('lmfwc_rand_hash', null)) {
        return $hash;
    }

    if (function_exists('wc_rand_hash')) {
        return wc_rand_hash();
    }

    if (!function_exists('openssl_random_pseudo_bytes')) {
        return sha1(wp_rand());
    }

    return bin2hex(openssl_random_pseudo_bytes(20));
}

/**
 * Converts dashes to camel case with first capital letter.
 *
 * @param string $input
 * @param string $separator
 *
 * @return string|string[]
 */
function lmfwc_camelize($input, $separator = '_')
{
    return str_replace($separator, '', ucwords($input, $separator));
}

/**
 * Checks whether a product is licensed.
 *
 * @param int $productId
 * @return bool
 */
function lmfwc_is_licensed_product($productId) {
    if (get_post_meta($productId, 'lmfwc_licensed_product', true)) {
        return true;
    }

    return false;
}

/**
 * Checks whether a product should have the expiry date of associated licenses
 * extended.
 *
 * @param $productId
 * @return bool
 */
function lmfwc_is_license_expiration_extendable_for_subscriptions($productId) {
    if (get_post_meta($productId, 'lmfwc_license_expiration_extendable_for_subscriptions', true)) {
        return true;
    }

    return false;
}

/**
 * Checks whether an order has already been completed or not.
 *
 * @param int $orderId
 * @return bool
 */
function lmfwc_is_order_complete($orderId) {
    if (!get_post_meta($orderId, 'lmfwc_order_complete')) {
        return false;
    }

    return true;
}

/**
 * Returns the configured action to perform on the given product in case of a
 * WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return string
 */
function lmfwc_get_subscription_renewal_action($productId) {
    $action = get_post_meta($productId, 'lmfwc_subscription_renewal_action', true);

    if ($action && is_string($action)) {
        return $action;
    }

    return 'issue_new_license';
}

/**
 * Returns the configured reset action to perform on the given product in case of a
 * WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return string
 */
function lmfwc_get_subscription_renewal_reset_action($productId) {
    $action = get_post_meta($productId, 'lmfwc_subscription_renewal_reset_action', true);

    if ($action && is_string($action)) {
        return $action;
    }

    return 'do_not_reset_on_renewal';
}

/**
 * Returns the configured cost per activation action to perform on the given product 
 * in case of a WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return string
 */
function lmfwc_get_subscription_cost_per_activation_action($productId) {
    $action = get_post_meta($productId, 'lmfwc_subscription_cost_per_activation_action', true);

    if ($action && is_string($action)) {
        return $action;
    }

    return 'cost_per_subscription_period';
}

/**
 * Returns the configured interval for the given product in case of a
 * WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return string
 */
function lmfwc_get_subscription_renewal_interval_type($productId) {
    $intervalType = get_post_meta($productId, 'lmfwc_subscription_renewal_interval_type', true);

    if ($intervalType && is_string($intervalType)) {
        return $intervalType;
    }

    return 'subscription';
}

/**
 * Returns the configured custom interval for the given product in case of a
 * WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return int
 */
function lmfwc_get_subscription_renewal_custom_interval($productId) {
    $customerInterval = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_interval', true);

    if ($customerInterval && is_numeric($customerInterval)) {
        return intval($customerInterval);
    }

    return 1;
}

/**
 * Returns the configured custom period for the given product in case of a
 * WooCommerce Subscriptions renewal order.
 *
 * @param int $productId
 * @return string
 */
function lmfwc_get_subscription_renewal_custom_period($productId) {
    $intervalType = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_period', true);
    $allowedIntervalTypes = array('hour', 'day', 'week', 'month', 'year');

    if ($intervalType && is_string($intervalType) && in_array($intervalType, $allowedIntervalTypes)) {
        return sanitize_text_field($intervalType);
    }

    return 'day';
}
