<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

use WC_Subscriptions_Product;
use WP_Post;

defined('ABSPATH') || exit;

class ProductData
{
    /**
     * ProductData constructor.
     */
    public function __construct()
    {
        add_action('lmfwc_simple_product_data_panel',   array($this, 'simpleProductDataPanel'),   10, 1);
        add_action('lmfwc_simple_product_save',         array($this, 'simpleProductSave'),        10, 1);
        add_action('lmfwc_variable_product_data_panel', array($this, 'variableProductDataPanel'), 10, 3);
        add_action('lmfwc_variable_product_save',       array($this, 'variableProductSave'),      10, 2);
    }

    /**
     * Adds the integration-specific fields to the plugin data panel.
     *
     * @param WP_Post $post
     */
    public function simpleProductDataPanel($post)
    {
        if (!WC_Subscriptions_Product::is_subscription($post->ID)) {
            return;
        }

        $renewalAction              = get_post_meta($post->ID, 'lmfwc_subscription_renewal_action', true);
        $renewalResetAction         = get_post_meta($post->ID, 'lmfwc_subscription_renewal_reset_action', true);
        $renewalCostPerActivation   = get_post_meta($post->ID, 'lmfwc_subscription_cost_per_activation_action', true);
        $renewalIntervalType        = get_post_meta($post->ID, 'lmfwc_subscription_renewal_interval_type', true);
        $customInterval             = get_post_meta($post->ID, 'lmfwc_subscription_renewal_custom_interval', true) ?: 1;
        $customPeriod               = get_post_meta($post->ID, 'lmfwc_subscription_renewal_custom_period', true);

        $disableOnRenewalActionNewLicense = array();
        if ($renewalAction === 'issue_new_license') {
            $disableOnRenewalActionNewLicense['disabled'] = 'true';
        }

        echo '</div><div class="options_group">';

        // Dropdown "lmfwc_subscription_renewal_action"
        woocommerce_wp_select(
            array(
                'id'      => 'lmfwc_subscription_renewal_action',
                'label'   => __('On subscription renewal', 'license-manager-for-woocommerce'),
                'options' => array(
                    'issue_new_license'       => __('Issue a new license key on each subscription renewal', 'license-manager-for-woocommerce'),
                    'extend_existing_license' => __('Extend the existing license on each subscription renewal', 'license-manager-for-woocommerce')
                ),
                'value' => $renewalAction
            )
        );

        // Dropdown "lmfwc_subscription_renewal_reset_action"
        woocommerce_wp_select(
            array(
                'id'      => 'lmfwc_subscription_renewal_reset_action',
                'label'   => __('Reset times activated', 'license-manager-for-woocommerce'),
                'options' => array(
                    'do_not_reset_on_renewal'   => __('Do no reset times activated on key with maximum activations', 'license-manager-for-woocommerce'),
                    'reset_license_on_renewal'  => __('Reset times activated to 0 on keys that have a maximum activation count', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalResetAction
            )
        );

        // Dropdown "lmfwc_subscription_cost_per_activation_action"
        woocommerce_wp_select(
            array(
                'id'      => 'lmfwc_subscription_cost_per_activation_action',
                'label'   => __('Price per activation', 'license-manager-for-woocommerce'),
                'options' => array(
                    'cost_per_subscription_period'   => __('The reoccurring price is the subscription price defined in the general section', 'license-manager-for-woocommerce'),
                    'cost_per_activation'  => __('The subscription price defined above will be multiplied by the license activations in the given subscription period', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalCostPerActivation
            )
        );

        // Dropdown "lmfwc_subscription_renewal_interval_type"
        woocommerce_wp_select(
            array(
                'id'      => 'lmfwc_subscription_renewal_interval_type',
                'label'   => __('Extend by', 'license-manager-for-woocommerce'),
                'options' => array(
                    'subscription' => __('WooCommerce Subscription interval', 'license-manager-for-woocommerce'),
                    'custom'       => __('Custom interval', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalIntervalType
            )
        );

        // Number "lmfwc_subscription_renewal_custom_interval"
        woocommerce_wp_text_input(
            array(
                'id'                => 'lmfwc_subscription_renewal_custom_interval',
                'label'             => __('Interval', 'license-manager-for-woocommerce'),
                'value'             => $customInterval,
                'type'              => 'number',
                'custom_attributes' => array_merge(array(
                    'step' => '1',
                    'min'  => '1'
                ), $disableOnRenewalActionNewLicense)
            )
        );

        // Dropdown "lmfwc_subscription_renewal_custom_period"
        woocommerce_wp_select(
            array(
                'id'      => 'lmfwc_subscription_renewal_custom_period',
                'label'   => __('Period', 'license-manager-for-woocommerce'),
                'options' => array(
                    'hour'  => __('Hour(s)', 'license-manager-for-woocommerce'),
                    'day'   => __('Day(s)', 'license-manager-for-woocommerce'),
                    'week'  => __('Week(s)', 'license-manager-for-woocommerce'),
                    'month' => __('Month(s)', 'license-manager-for-woocommerce'),
                    'year'  => __('Year(s)', 'license-manager-for-woocommerce'),
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $customPeriod
            )
        );
    }

    /**
     * Stores the additionally created input fields in the database.
     *
     * @param int $postId
     */
    public function simpleProductSave($postId)
    {
        // Update the extend subscription flag, according to checkbox.
        if (array_key_exists('lmfwc_license_expiration_extendable_for_subscriptions', $_POST)) {
            update_post_meta($postId, 'lmfwc_license_expiration_extendable_for_subscriptions', 1);
        } else {
            update_post_meta($postId, 'lmfwc_license_expiration_extendable_for_subscriptions', 0);
        }

        // Update the subscription renewal action
        update_post_meta(
            $postId,
            'lmfwc_subscription_renewal_action',
            sanitize_text_field($_POST['lmfwc_subscription_renewal_action'])
        );

        // Update the subscription renewal reset action
        if (isset($_POST['lmfwc_subscription_renewal_reset_action'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_reset_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_reset_action'])
            );
        }

        // Update the cost per activation action
        if (isset($_POST['lmfwc_subscription_cost_per_activation_action'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_cost_per_activation_action',
                sanitize_text_field($_POST['lmfwc_subscription_cost_per_activation_action'])
            );
        }

        // Update the subscription renewal interval type
        if (isset($_POST['lmfwc_subscription_renewal_interval_type'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_interval_type',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_interval_type'])
            );
        }

        // Update the subscription renewal custom interval
        if (isset($_POST['lmfwc_subscription_renewal_custom_interval'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_custom_interval',
                intval($_POST['lmfwc_subscription_renewal_custom_interval'])
            );
        }

        // Update the subscription renewal custom period
        if (isset($_POST['lmfwc_subscription_renewal_custom_period'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_custom_period',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_custom_period'])
            );
        }
    }

    /**
     * Adds integration specific fields to the variable product.
     *
     * @param int     $loop
     * @param array   $variationData
     * @param WP_Post $variation
     */
    public function variableProductDataPanel($loop, $variationData, $variation)
    {
        $productId = $variation->ID;

        if (!WC_Subscriptions_Product::is_subscription($productId)) {
            return;
        }

        $renewalAction              = get_post_meta($productId, 'lmfwc_subscription_renewal_action', true);
        $renewalResetAction         = get_post_meta($productId, 'lmfwc_subscription_renewal_reset_action', true);
        $renewalCostPerActivation   = get_post_meta($productId, 'lmfwc_subscription_cost_per_activation_action', true);
        $renewalIntervalType        = get_post_meta($productId, 'lmfwc_subscription_renewal_interval_type', true);
        $customInterval             = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_interval', true) ?: 1;
        $customPeriod               = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_period', true);

        $disableOnRenewalActionNewLicense = array();
        if ($renewalAction === 'issue_new_license') {
            $disableOnRenewalActionNewLicense['disabled'] = 'true';
        }

        echo '</div><div class="options_group">';

        // Dropdown "lmfwc_subscription_renewal_action"
        woocommerce_wp_select(
            array(
                'id'      => sprintf('lmfwc_subscription_renewal_action_%d', $loop),
                'name'    => sprintf('lmfwc_subscription_renewal_action[%d]', $loop),
                'label'   => __('On subscription renewal', 'license-manager-for-woocommerce'),
                'options' => array(
                    'issue_new_license'       => __('Issue a new license key on each subscription renewal', 'license-manager-for-woocommerce'),
                    'extend_existing_license' => __('Extend the existing license on each subscription renewal', 'license-manager-for-woocommerce')
                ),
                'value' => $renewalAction
            )
        );

        // Dropdown "lmfwc_subscription_renewal_reset_action"
        woocommerce_wp_select(
            array(
                'id'      => sprintf('lmfwc_subscription_renewal_reset_action_%d', $loop),
                'name'    => sprintf('lmfwc_subscription_renewal_reset_action[%d]', $loop),
                'label'   => __('Reset times activated', 'license-manager-for-woocommerce'),
                'options' => array(
                    'do_not_reset_on_renewal'   => __('Do no reset times activated on key with maximum activations', 'license-manager-for-woocommerce'),
                    'reset_license_on_renewal'  => __('Reset times activated to 0 on keys that have a maximum activation count', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalResetAction
            )
        );

        // Dropdown "lmfwc_subscription_cost_per_activation_action"
        woocommerce_wp_select(
            array(
                'id'      => sprintf('lmfwc_subscription_cost_per_activation_action_%d', $loop),
                'name'    => sprintf('lmfwc_subscription_cost_per_activation_action[%d]', $loop),
                'label'   => __('Price per activation', 'license-manager-for-woocommerce'),
                'options' => array(
                    'cost_per_subscription_period'   => __('The reoccurring price is the subscription price defined in the general section', 'license-manager-for-woocommerce'),
                    'cost_per_activation'  => __('The subscription price defined above will be multiplied by the license activations in the given subscription period', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalCostPerActivation
            )
        );

        // Dropdown "lmfwc_subscription_renewal_interval_type"
        woocommerce_wp_select(
            array(
                'id'      => sprintf('lmfwc_subscription_renewal_interval_type_%d', $loop),
                'name'    => sprintf('lmfwc_subscription_renewal_interval_type[%d]', $loop),
                'label'   => __('Extend by', 'license-manager-for-woocommerce'),
                'options' => array(
                    'subscription' => __('WooCommerce Subscription interval', 'license-manager-for-woocommerce'),
                    'custom'       => __('Custom interval', 'license-manager-for-woocommerce')
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $renewalIntervalType
            )
        );

        // Number "lmfwc_subscription_renewal_custom_interval"
        woocommerce_wp_text_input(
            array(
                'id'                => sprintf('lmfwc_subscription_renewal_custom_interval_%d', $loop),
                'name'              => sprintf('lmfwc_subscription_renewal_custom_interval[%d]', $loop),
                'label'             => __('Interval', 'license-manager-for-woocommerce'),
                'value'             => $customInterval,
                'type'              => 'number',
                'custom_attributes' => array_merge(array(
                    'step' => '1',
                    'min'  => '1'
                ), $disableOnRenewalActionNewLicense)
            )
        );

        // Dropdown "lmfwc_subscription_renewal_custom_period"
        woocommerce_wp_select(
            array(
                'id'      => sprintf('lmfwc_subscription_renewal_custom_period_%d', $loop),
                'name'    => sprintf('lmfwc_subscription_renewal_custom_period[%d]', $loop),
                'label'   => __('Period', 'license-manager-for-woocommerce'),
                'options' => array(
                    'hour'  => __('Hour(s)', 'license-manager-for-woocommerce'),
                    'day'   => __('Day(s)', 'license-manager-for-woocommerce'),
                    'week'  => __('Week(s)', 'license-manager-for-woocommerce'),
                    'month' => __('Month(s)', 'license-manager-for-woocommerce'),
                    'year'  => __('Year(s)', 'license-manager-for-woocommerce'),
                ),
                'custom_attributes' => $disableOnRenewalActionNewLicense,
                'value' => $customPeriod
            )
        );
    }

    /**
     * Saves the data from the product variation fields.
     *
     * @param int $variationId
     * @param int $i
     */
    public function variableProductSave($variationId, $i)
    {
        // Update the subscription renewal action
        update_post_meta(
            $variationId,
            'lmfwc_subscription_renewal_action',
            sanitize_text_field($_POST['lmfwc_subscription_renewal_action'][$i])
        );
    
        // Update the subscription renewal reset action
        if (isset($_POST['lmfwc_subscription_renewal_reset_action'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_reset_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_reset_action'][$i])
            );
        }
    
        // Update the cost per activation action
        if (isset($_POST['lmfwc_subscription_cost_per_activation_action'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_cost_per_activation_action',
                sanitize_text_field($_POST['lmfwc_subscription_cost_per_activation_action'][$i])
            );
        }

        // Update the subscription renewal interval type
        if (isset($_POST['lmfwc_subscription_renewal_interval_type'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_interval_type',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_interval_type'][$i])
            );
        }

        // Update the subscription renewal custom interval
        if (isset($_POST['lmfwc_subscription_renewal_custom_interval'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_custom_interval',
                intval($_POST['lmfwc_subscription_renewal_custom_interval'][$i])
            );
        }

        // Update the subscription renewal custom period
        if (isset($_POST['lmfwc_subscription_renewal_custom_period'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_custom_period',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_custom_period'][$i])
            );
        }
    }
}