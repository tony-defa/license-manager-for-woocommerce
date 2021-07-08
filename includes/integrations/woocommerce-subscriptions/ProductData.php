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
        $subscriptionModelType      = get_post_meta($post->ID, 'lmfwc_subscription_model_type', true);
        $maximumIncludedActivations = get_post_meta($post->ID, 'lmfwc_maximum_included_activations', true);
        $renewalIntervalType        = get_post_meta($post->ID, 'lmfwc_subscription_renewal_interval_type', true);
        $customInterval             = get_post_meta($post->ID, 'lmfwc_subscription_renewal_custom_interval', true) ?: 1;
        $customPeriod               = get_post_meta($post->ID, 'lmfwc_subscription_renewal_custom_period', true);

        $wrapperClass = array(
            'lmfwc_subscription_renewal_interval_type'   => '',
            'lmfwc_subscription_renewal_reset_action' => '',
            'lmfwc_subscription_model_type' => '',
            'lmfwc_maximum_included_activations' => '',
            'lmfwc_subscription_renewal_custom_interval' => '',
            'lmfwc_subscription_renewal_custom_period'   => ''
        );

        if ($renewalAction === 'extend_existing_license') {
            if ($renewalResetAction === 'do_not_reset_on_renewal') {
                $wrapperClass['lmfwc_subscription_model_type']      .= ' hidden';
                $wrapperClass['lmfwc_maximum_included_activations'] .= ' hidden';
            } else {
                if ($subscriptionModelType === 'fixed_usage_type') {
                    $wrapperClass['lmfwc_maximum_included_activations'] .= ' hidden';
                }
            }

            if ($renewalIntervalType === 'subscription') {
                $wrapperClass['lmfwc_subscription_renewal_custom_interval'] .= ' hidden';
                $wrapperClass['lmfwc_subscription_renewal_custom_period']   .= ' hidden';
            }
        } else {
            $wrapperClass['lmfwc_subscription_renewal_reset_action']    .= ' hidden';
            $wrapperClass['lmfwc_subscription_model_type']              .= ' hidden';
            $wrapperClass['lmfwc_maximum_included_activations']         .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_interval_type']   .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_custom_interval'] .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_custom_period']   .= ' hidden';
        }

        echo '</div><div class="options_group">';

        // Dropdown "lmfwc_subscription_renewal_action"
        woocommerce_wp_select(
            array(
                'id'            => 'lmfwc_subscription_renewal_action',
                'class'         => 'lmfwc_subscription_renewal_action',
                'label'         => __('On subscription renewal', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'issue_new_license'       => __('Issue a new license key on each subscription renewal', 'license-manager-for-woocommerce'),
                    'extend_existing_license' => __('Extend the existing license on each subscription renewal', 'license-manager-for-woocommerce')
                ),
                'value'         => $renewalAction
            )
        );

        // Dropdown "lmfwc_subscription_renewal_reset_action"
        woocommerce_wp_select(
            array(
                'id'            => 'lmfwc_subscription_renewal_reset_action',
                'class'         => 'lmfwc_subscription_renewal_reset_action',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_reset_action'],
                'label'         => __('Reset times activated', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'do_not_reset_on_renewal'   => __('Do no reset times activated on license keys', 'license-manager-for-woocommerce'),
                    'reset_license_on_renewal'  => __('Reset times activated to 0 on license keys', 'license-manager-for-woocommerce')
                ),
                'value'         => $renewalResetAction
            )
        );

        // Dropdown "lmfwc_subscription_model_type"
        woocommerce_wp_select(
            array(
                'id'            => 'lmfwc_subscription_model_type',
                'class'         => 'lmfwc_subscription_model_type',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_model_type'],
                'label'         => __('Subscription model type', 'license-manager-for-woocommerce'),
                'description'   => __(
                    'In a <b>fixed usage model</b> the reoccurring price of the subscription is the subscription price defined in the general section (default WooCommerce behaviour).'.
                    '<br><br>'.
                    'With the <b>variable usage model</b> the price for each additional activation is added to the regular subscription price at the end of the subscription period. '.
                    'Use this in combination with a license key that allows more activations than the maximum included amount. '.
                    'This will automatically change the pre-paid subscription to a post-paid subscription.',
                    'license-manager-for-woocommerce'
                ),
                'desc_tip'      => true,
                'options'       => array(
                    'fixed_usage_type'   => __('Fixed usage model', 'license-manager-for-woocommerce'),
                    'variable_usage_type'  => __('Variable usage model', 'license-manager-for-woocommerce')
                ),
                'value'         => $subscriptionModelType
            )
        );

        // Number "lmfwc_maximum_included_activations"
        woocommerce_wp_text_input(
            array(
                'id'                => 'lmfwc_maximum_included_activations',
                'class'             => 'lmfwc_maximum_included_activations',
                'wrapper_class'     => $wrapperClass['lmfwc_maximum_included_activations'],
                'label'             => __('Maximum included activations', 'license-manager-for-woocommerce'),
                'description'       => __(
                    'The number of activations that are included in the regular subscription price.',
                    'license-manager-for-woocommerce'
                ),
				'desc_tip'          => true,
                'value'             => ($maximumIncludedActivations) ? $maximumIncludedActivations : 1,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '1'
                )
            )
        );

        // Dropdown "lmfwc_subscription_renewal_interval_type"
        woocommerce_wp_select(
            array(
                'id'            => 'lmfwc_subscription_renewal_interval_type',
                'class'         => 'lmfwc_subscription_renewal_interval_type',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_interval_type'],
                'label'         => __('Extend by', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'subscription' => __('WooCommerce Subscription interval', 'license-manager-for-woocommerce'),
                    'custom'       => __('Custom interval', 'license-manager-for-woocommerce')
                ),
                'value'         => $renewalIntervalType
            )
        );

        // Number "lmfwc_subscription_renewal_custom_interval"
        woocommerce_wp_text_input(
            array(
                'id'                => 'lmfwc_subscription_renewal_custom_interval',
                'class'             => 'lmfwc_subscription_renewal_custom_interval',
                'wrapper_class'     => $wrapperClass['lmfwc_subscription_renewal_custom_interval'],
                'label'             => __('Interval', 'license-manager-for-woocommerce'),
                'value'             => $customInterval,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                )
            )
        );

        // Dropdown "lmfwc_subscription_renewal_custom_period"
        woocommerce_wp_select(
            array(
                'id'            => 'lmfwc_subscription_renewal_custom_period',
                'class'         => 'lmfwc_subscription_renewal_custom_period',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_custom_period'],
                'label'         => __('Period', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'hour'  => __('Hour(s)', 'license-manager-for-woocommerce'),
                    'day'   => __('Day(s)', 'license-manager-for-woocommerce'),
                    'week'  => __('Week(s)', 'license-manager-for-woocommerce'),
                    'month' => __('Month(s)', 'license-manager-for-woocommerce'),
                    'year'  => __('Year(s)', 'license-manager-for-woocommerce'),
                ),
                'value'         => $customPeriod
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
        if (isset($_POST['lmfwc_subscription_renewal_action'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_action'])
            );
        }

        // Update the subscription renewal reset action
        if (isset($_POST['lmfwc_subscription_renewal_reset_action'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_renewal_reset_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_reset_action'])
            );
        }

        // Update the invoice per activation action
        if (isset($_POST['lmfwc_subscription_model_type'])) {
            update_post_meta(
                $postId,
                'lmfwc_subscription_model_type',
                sanitize_text_field($_POST['lmfwc_subscription_model_type'])
            );
        }

        // Update the minimum activations per subscription period
        if (isset($_POST['lmfwc_maximum_included_activations'])) {
            update_post_meta(
                $postId,
                'lmfwc_maximum_included_activations',
                sanitize_text_field($_POST['lmfwc_maximum_included_activations'])
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
                (int)$_POST['lmfwc_subscription_renewal_custom_interval']
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
        $subscriptionModelType      = get_post_meta($productId, 'lmfwc_subscription_model_type', true);
        $maximumIncludedActivations = get_post_meta($productId, 'lmfwc_maximum_included_activations', true);
        $renewalIntervalType        = get_post_meta($productId, 'lmfwc_subscription_renewal_interval_type', true);
        $customInterval             = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_interval', true) ?: 1;
        $customPeriod               = get_post_meta($productId, 'lmfwc_subscription_renewal_custom_period', true);

        $wrapperClass = array(
            'lmfwc_subscription_renewal_reset_action'    => 'form-row form-row-full',
            'lmfwc_subscription_model_type'              => 'form-row form-row-full',
            'lmfwc_maximum_included_activations'         => 'form-row form-row-full',
            'lmfwc_subscription_renewal_interval_type'   => 'form-row form-row-full',
            'lmfwc_subscription_renewal_custom_interval' => 'form-field form-row form-row-first',
            'lmfwc_subscription_renewal_custom_period'   => 'form-field form-row form-row-last'
        );

        if ($renewalAction === 'extend_existing_license') {
            if ($renewalResetAction === 'do_not_reset_on_renewal') {
                $wrapperClass['lmfwc_subscription_model_type']      .= ' hidden';
                $wrapperClass['lmfwc_maximum_included_activations'] .= ' hidden';
            } else {
                if ($subscriptionModelType === 'fixed_usage_type') {
                    $wrapperClass['lmfwc_maximum_included_activations'] .= ' hidden';
                }
            }

            if ($renewalIntervalType === 'subscription') {
                $wrapperClass['lmfwc_subscription_renewal_custom_interval'] .= ' hidden';
                $wrapperClass['lmfwc_subscription_renewal_custom_period']   .= ' hidden';
            }
        } else {
            $wrapperClass['lmfwc_subscription_renewal_reset_action']   .= ' hidden';
            $wrapperClass['lmfwc_subscription_model_type']              .= ' hidden';
            $wrapperClass['lmfwc_maximum_included_activations']         .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_interval_type']   .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_custom_interval'] .= ' hidden';
            $wrapperClass['lmfwc_subscription_renewal_custom_period']   .= ' hidden';
        }

        //echo '</div><div class="options_group">';

        // Dropdown "lmfwc_subscription_renewal_action"
        woocommerce_wp_select(
            array(
                'id'            => sprintf('lmfwc_subscription_renewal_action_%d', $loop),
                'class'         => 'lmfwc_subscription_renewal_action',
                'name'          => sprintf('lmfwc_subscription_renewal_action[%d]', $loop),
                'label'         => __('On subscription renewal', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'issue_new_license'       => __('Issue a new license key on each subscription renewal', 'license-manager-for-woocommerce'),
                    'extend_existing_license' => __('Extend the existing license on each subscription renewal', 'license-manager-for-woocommerce')
                ),
                'value' => $renewalAction,
                'wrapper_class' => 'form-row form-row-full'
            )
        );

        // Dropdown "lmfwc_subscription_renewal_reset_action"
        woocommerce_wp_select(
            array(
                'id'            => sprintf('lmfwc_subscription_renewal_reset_action_%d', $loop),
                'class'         => 'lmfwc_subscription_renewal_reset_action',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_reset_action'],
                'name'          => sprintf('lmfwc_subscription_renewal_reset_action[%d]', $loop),
                'label'         => __('Reset times activated', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'do_not_reset_on_renewal'   => __('Do no reset times activated on license keys', 'license-manager-for-woocommerce'),
                    'reset_license_on_renewal'  => __('Reset times activated to 0 on license keys', 'license-manager-for-woocommerce')
                ),
                'value'         => $renewalResetAction
            )
        );

        // Dropdown "lmfwc_subscription_model_type"
        woocommerce_wp_select(
            array(
                'id'            => sprintf('lmfwc_subscription_model_type_%d', $loop),
                'class'         => 'lmfwc_subscription_model_type',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_model_type'],
                'name'          => sprintf('lmfwc_subscription_model_type[%d]', $loop),
                'label'         => __('Subscription model type', 'license-manager-for-woocommerce'),
                'description'   => __(
                    'In a <b>fixed usage model</b> the reoccurring price of the subscription is the subscription price defined in the general section (default WooCommerce behaviour).'.
                    '<br><br>'.
                    'With the <b>variable usage model</b> the price for each additional activation is added to the regular subscription price at the end of the subscription period. '.
                    'Use this in combination with a license key that allows more activations than the maximum included amount. '.
                    'This will automatically change the pre-paid subscription to a post-paid subscription.',
                    'license-manager-for-woocommerce'
                ),
                'desc_tip'      => true,
                'options'       => array(
                    'fixed_usage_type'   => __('Fixed usage model', 'license-manager-for-woocommerce'),
                    'variable_usage_type'  => __('Variable usage model', 'license-manager-for-woocommerce')
                ),
                'value'         => $subscriptionModelType
            )
        );

        // Number "lmfwc_maximum_included_activations"
        woocommerce_wp_text_input(
            array(
                'id'                => sprintf('lmfwc_maximum_included_activations_%d', $loop),
                'class'             => 'lmfwc_maximum_included_activations',
                'wrapper_class'     => $wrapperClass['lmfwc_maximum_included_activations'],
                'name'              => sprintf('lmfwc_maximum_included_activations[%d]', $loop),
                'label'             => __('Maximum included activations', 'license-manager-for-woocommerce'),
                'description'       => __(
                    'The number of activations that are included in the regular subscription price.',
                    'license-manager-for-woocommerce'
                ),
				'desc_tip'          => true,
                'value'             => ($maximumIncludedActivations) ? $maximumIncludedActivations : 1,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '1'
                )
            )
        );

        // Dropdown "lmfwc_subscription_renewal_interval_type"
        woocommerce_wp_select(
            array(
                'id'            => sprintf('lmfwc_subscription_renewal_interval_type_%d', $loop),
                'class'         => 'lmfwc_subscription_renewal_interval_type',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_interval_type'],
                'name'          => sprintf('lmfwc_subscription_renewal_interval_type[%d]', $loop),
                'label'         => __('Extend by', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'subscription' => __('WooCommerce Subscription interval', 'license-manager-for-woocommerce'),
                    'custom'       => __('Custom interval', 'license-manager-for-woocommerce')
                ),
                'value'         => $renewalIntervalType
            )
        );

        echo '<div>';

        // Number "lmfwc_subscription_renewal_custom_interval"
        woocommerce_wp_text_input(
            array(
                'id'                => sprintf('lmfwc_subscription_renewal_custom_interval_%d', $loop),
                'class'             => 'lmfwc_subscription_renewal_custom_interval',
                'wrapper_class'     => $wrapperClass['lmfwc_subscription_renewal_custom_interval'],
                'name'              => sprintf('lmfwc_subscription_renewal_custom_interval[%d]', $loop),
                'label'             => __('Interval', 'license-manager-for-woocommerce'),
                'value'             => $customInterval,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                )
            )
        );

        // Dropdown "lmfwc_subscription_renewal_custom_period"
        woocommerce_wp_select(
            array(
                'id'            => sprintf('lmfwc_subscription_renewal_custom_period_%d', $loop),
                'class'         => 'lmfwc_subscription_renewal_custom_period',
                'wrapper_class' => $wrapperClass['lmfwc_subscription_renewal_custom_period'],
                'name'          => sprintf('lmfwc_subscription_renewal_custom_period[%d]', $loop),
                'label'         => __('Period', 'license-manager-for-woocommerce'),
                'options'       => array(
                    'hour'  => __('Hour(s)', 'license-manager-for-woocommerce'),
                    'day'   => __('Day(s)', 'license-manager-for-woocommerce'),
                    'week'  => __('Week(s)', 'license-manager-for-woocommerce'),
                    'month' => __('Month(s)', 'license-manager-for-woocommerce'),
                    'year'  => __('Year(s)', 'license-manager-for-woocommerce'),
                ),
                'value'         => $customPeriod
            )
        );

        echo '</div>';
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
        if (isset($_POST['lmfwc_subscription_renewal_action'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_action'][$i])
            );
        }
    
        // Update the subscription renewal reset action
        if (isset($_POST['lmfwc_subscription_renewal_reset_action'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_renewal_reset_action',
                sanitize_text_field($_POST['lmfwc_subscription_renewal_reset_action'][$i])
            );
        }
    
        // Update the invoice per activation action
        if (isset($_POST['lmfwc_subscription_model_type'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_subscription_model_type',
                sanitize_text_field($_POST['lmfwc_subscription_model_type'][$i])
            );
        }

        // Update the minimum activations per subscription period
        if (isset($_POST['lmfwc_maximum_included_activations'][$i])) {
            update_post_meta(
                $variationId,
                'lmfwc_maximum_included_activations',
                sanitize_text_field($_POST['lmfwc_maximum_included_activations'][$i])
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
                (int)$_POST['lmfwc_subscription_renewal_custom_interval'][$i]
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