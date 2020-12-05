<?php
/**
 * LicenseManager for WooCommerce Core Functions
 *
 * General core functions available on both the front-end and admin.
 */

use LicenseManagerForWooCommerce\Repositories\Resources\License as LicenseResourceRepository;
use LicenseManagerForWooCommerce\Settings;

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
 * Provides a backwards-compatible solution for the array_key_first() method.
 *
 * @param array $array
 * @return int|string|null
 */
function lmfwc_array_key_first($array)
{
    if (function_exists('array_key_first')) {
        return array_key_first($array);
    }

    reset($array);

    return key($array);
}

/**
 * Converts valid_for into expires_at.
 *
 * @param string $validFor
 * @return null|string
 */
function lmfwc_convert_valid_for_to_expires_at($validFor, $format = 'Y-m-d H:i:s')
{
    if (!empty($validFor)) {
        try {
            $date = new DateTime('now', new DateTimeZone('GMT'));
            $dateInterval = new DateInterval('P' . $validFor . 'D');
        } catch (Exception $e) {
            return null;
        }

        return $date->add($dateInterval)->format($format);
    }

    return null;
}

/**
 * Updates the expiration of downloads in orders
 * @param $expiresAt
 * @param $orderId
 */
function lmfwc_update_order_downloads_expiration($expiresAt, $orderId)
{
    if (!empty($expiresAt) && !empty($orderId) && Settings::get('lmfwc_download_expires')) {
        try {
            $dataStore           = WC_Data_Store::load('customer-download');
            $downloadPermissions = $dataStore->get_downloads(
                array(
                    'order_id' => $orderId
                )
            );
        } catch (Exception $e) {
            return;
        }

        // Validate expiresAt is given in the right format (time check) - otherwise add current GMT time
        if (!DateTime::createFromFormat('Y-m-d H:i:s', $expiresAt) !== false) {
            try {
                $date  = new DateTime($expiresAt, new DateTimeZone('GMT'));
                $now   = new DateTime('now', new DateTimeZone('GMT'));
                $today = new DateTime(date('Y-m-d'), new DateTimeZone('GMT'));
                $time  = $today->diff($now);

                $date->add($time);

                $expiresAt = $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                return;
            }
        }

        if ($downloadPermissions && count($downloadPermissions) > 0) {
            foreach ($downloadPermissions as $download) {
                $download = new WC_Customer_Download($download->get_id());
                $download->set_access_expires($expiresAt);
                $download->save();
            }
        }
    }
}