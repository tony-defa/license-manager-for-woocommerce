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

        $extendSubscription = get_post_meta($post->ID, 'lmfwc_license_expiration_extendable_for_subscriptions', true);
        
        echo '</div><div class="options_group">';

        // Checkbox "lmfwc_extend_subscription"
        woocommerce_wp_checkbox(
            array(
                'id'          => 'lmfwc_license_expiration_extendable_for_subscriptions',
                'label'       => __('Extend the subscription', 'license-manager-for-woocommerce'),
                'description' => __('Extends the expiry date of license keys instead of generating new license keys for each subscription renewal.', 'license-manager-for-woocommerce'),
                'value'       => $extendSubscription,
                'cbvalue'     => 1,
                'desc_tip'    => false
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
        }

        else {
            update_post_meta($postId, 'lmfwc_license_expiration_extendable_for_subscriptions', 0);
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

        $extendSubscription = get_post_meta($productId, 'lmfwc_license_expiration_extendable_for_subscriptions', true);

        echo '</div><div class="options_group">';

        // Checkbox "lmfwc_license_expiration_extendable_for_subscriptions"
        woocommerce_wp_checkbox(
            array(
                'id'          => sprintf('lmfwc_license_expiration_extendable_for_subscriptions_%d', $loop),
                'name'        => sprintf('lmfwc_license_expiration_extendable_for_subscriptions[%d]', $loop),
                'label'       => __('Extend the subscription', 'license-manager-for-woocommerce'),
                'description' => __('Extends the expiry date of license keys instead of generating new license keys for each subscription renewal.', 'license-manager-for-woocommerce'),
                'value'       => $extendSubscription,
                'cbvalue'     => 1,
                'desc_tip'    => false
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
        // Update the extend subscription flag, according to checkbox.
        if (array_key_exists('lmfwc_license_expiration_extendable_for_subscriptions', $_POST)
            && array_key_exists($i, $_POST['lmfwc_license_expiration_extendable_for_subscriptions'])
        ) {
            update_post_meta($variationId, 'lmfwc_license_expiration_extendable_for_subscriptions', 1);
        } else {
            update_post_meta($variationId, 'lmfwc_license_expiration_extendable_for_subscriptions', 0);
        }
    }
}