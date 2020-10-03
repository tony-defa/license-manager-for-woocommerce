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
        add_action('lmfwc_product_data_panel',     array($this, 'simpleSubscriptionFields'), 8);
        add_action('lmfwc_product_data_save_post', array($this, 'savePost'));
    }

    /**
     * Adds the integration-specific fields to the plugin data panel.
     *
     * @param WP_Post $post
     */
    public function simpleSubscriptionFields($post)
    {
        if (!WC_Subscriptions_Product::is_subscription($post->ID)) {
            return;
        }

        $extendSubscription = get_post_meta($post->ID, 'lmfwc_extend_subscription', true);
        
        echo '</div><div class="options_group">';

        // Checkbox "lmfwc_extend_subscription"
        woocommerce_wp_checkbox(
            array(
                'id'          => 'lmfwc_extend_subscription',
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
    public function savePost($postId)
    {
        // Update the extend subscription flag, according to checkbox.
        if (array_key_exists('lmfwc_extend_subscription', $_POST)) {
            update_post_meta($postId, 'lmfwc_extend_subscription', 1);
        }

        else {
            update_post_meta($postId, 'lmfwc_extend_subscription', 0);
        }
    }
}