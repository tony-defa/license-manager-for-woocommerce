<?php


namespace LicenseManagerForWooCommerce\Integrations\WooCommerceSubscriptions;

defined('ABSPATH') || exit;

class Controller
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
        new Order();
        new ProductData();
    }
}