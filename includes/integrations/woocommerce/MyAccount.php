<?php

namespace LicenseManagerForWooCommerce\Integrations\WooCommerce;

use Exception;
use LicenseManagerForWooCommerce\Settings;

defined('ABSPATH') || exit;

class MyAccount
{
    /**
     * MyAccount constructor.
     */
    public function __construct()
    {
        add_rewrite_endpoint('licenses', EP_ROOT | EP_PAGES);

        add_filter('woocommerce_account_menu_items',        array($this, 'accountMenuItems'));
        add_action('woocommerce_account_licenses_endpoint', array($this, 'licensesEndpoint'));
        add_filter('the_title',                             array($this, 'title'));
    }

    /**
     * Adds the plugin pages to the "My account" section.
     *
     * @param array $items
     *
     * @return array
     */
    public function accountMenuItems($items)
    {
        $customItems = array(
            'licenses' => __('Licenses', 'license-manager-for-woocommerce')
        );

        $customItems = array_slice($items, 0, 2, true)
            + $customItems + array_slice($items, 2, count($items), true);

        return $customItems;
    }

    /**
     * Creates an overview of all purchased license keys.
     */
    public function licensesEndpoint()
    {
        $user = wp_get_current_user();

        if (!$user) {
            return;
        }

        if (array_key_exists('action', $_POST)) {
            $licenseKey = sanitize_text_field($_POST['license']);

            if ($_POST['action'] === 'activate' && Settings::get('lmfwc_allow_users_to_activate')) {
                $nonce = wp_verify_nonce($_POST['_wpnonce'], 'lmfwc_myaccount_activate_license');

                if ($nonce) {
                    try {
                        lmfwc_activate_license($licenseKey);
                    } catch (Exception $e) {
                    }
                }
            }

            if ($_POST['action'] === 'deactivate' && Settings::get('lmfwc_allow_users_to_deactivate')) {
                $nonce = wp_verify_nonce($_POST['_wpnonce'],'lmfwc_myaccount_deactivate_license');

                if ($nonce) {
                    try {
                        lmfwc_deactivate_license($licenseKey);
                    } catch (Exception $e) {
                    }
                }
            }
        }

        wp_enqueue_style('lmfwc_admin_css', LMFWC_CSS_URL . 'main.css');

        global $wp_query;

        $page = 1;

        if ($wp_query->query['licenses']) {
            $page = (int)$wp_query->query['licenses'];
        }

        echo wc_get_template_html(
            'myaccount/lmfwc-licenses.php',
            array(
                'dateFormat'       => get_option('date_format'),
                'customerLicenses' => apply_filters('lmfwc_get_all_customer_license_keys', $user->ID),
                'page'             => $page,
                'canActivate'      => Settings::get('lmfwc_allow_users_to_activate'),
                'canDeactivate'    => Settings::get('lmfwc_allow_users_to_deactivate'),
            ),
            '',
            LMFWC_TEMPLATES_DIR
        );
    }

    /**
     * Sets the page title for the "My account -> Licenses" page.
     *
     * @param string $title
     * @return string
     */
    public function title($title)
    {
        global $wp;
        global $wp_query;

        if ($wp_query !== null
            && !is_admin()
            && is_main_query()
            && in_the_loop()
            && is_page()
            && isset($wp->query_vars['pagename'])
            && $wp->query_vars['pagename'] === apply_filters('lmfwc_myaccount_pagename', 'my-account')
            && array_key_exists('licenses', $wp->query_vars)
        ) {
            $title = apply_filters(
                'lmfwc_myaccount_licenses_page_title',
                __('Licenses', 'license-manager-for-woocommerce')
            );

            remove_filter('the_title', array($this, 'title'));
        }

        return $title;
    }
}
