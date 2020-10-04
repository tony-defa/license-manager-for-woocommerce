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