<?php

namespace LicenseManagerForWooCommerce\Settings;

use LicenseManagerForWooCommerce\Settings;

defined('ABSPATH') || exit;

class Subscription
{

    /**
     * @var string
     */
    public const DEFAULT_ACTIVATION_NAME_SINGULAR = 'activation';

    /**
     * @var string
     */
    public const DEFAULT_ACTIVATION_NAME_PLURAL = 'activations';

    /**
     * @var string
     */
    public const ACTIVATION_NAME_FIELD_NAME = 'lmfwc_activation_name_string';

    /**
     * @var string
     */
    public const SHOW_MAXIMUM_INCLUDED_ACTIVATIONS_FIELD_NAME = 'lmfwc_display_included_activations';

    /**
     * @var string
     */
    public const SHOW_SINGLE_ACTIVATION_PRICE_FIELD_NAME = 'lmfwc_display_single_activation_price';

    /**
     * @var string
     */
    public const ACTIVATION_PRICE_DECIMALS_FIELD_NAME = 'lmfwc_activation_price_decimals';

    /**
     * @var array
     */
    private $settings;

    /**
     * General constructor.
     */
    public function __construct()
    {
        $this->settings = get_option(Settings::SECTION_SUBSCRIPTION, array());

        /**
         * @see https://developer.wordpress.org/reference/functions/register_setting/#parameters
         */
        $args = array(
            'sanitize_callback' => array($this, 'sanitize')
        );

        // Register the initial settings group.
        register_setting('lmfwc_settings_group_subscription', Settings::SECTION_SUBSCRIPTION, $args);

        // Initialize the individual sections
        $this->initSectionVariableUsage();
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function sanitize($settings)
    {
        if ($settings === null) {
            return array();
        }

        return $settings;
    }

    /**
     * Initializes the "variable_usage_section" section.
     *
     * @return void
     */
    private function initSectionVariableUsage()
    {        
        add_settings_section(
            'variable_usage_section',
            __('Variable Usage Model', 'license-manager-for-woocommerce'),
            null,
            'lmfwc_variable_usage_model_type'
        );

        add_settings_field(
            self::ACTIVATION_NAME_FIELD_NAME,
            __('Activation name', 'license-manager-for-woocommerce'),
            array($this, 'fieldActivationNameString'),
            'lmfwc_variable_usage_model_type',
            'variable_usage_section'
        );

        add_settings_field(
            self::SHOW_MAXIMUM_INCLUDED_ACTIVATIONS_FIELD_NAME,
            __('Display included activations', 'license-manager-for-woocommerce'),
            array($this, 'fieldDisplayIncludedActivations'),
            'lmfwc_variable_usage_model_type',
            'variable_usage_section'
        );

        add_settings_field(
            self::SHOW_SINGLE_ACTIVATION_PRICE_FIELD_NAME,
            __('Display activation price', 'license-manager-for-woocommerce'),
            array($this, 'fieldDisplayActivationPrice'),
            'lmfwc_variable_usage_model_type',
            'variable_usage_section'
        );

        add_settings_field(
            self::ACTIVATION_PRICE_DECIMALS_FIELD_NAME,
            __('Activation price decimals', 'license-manager-for-woocommerce'),
            array($this, 'fieldActivationPriceDecimals'),
            'lmfwc_variable_usage_model_type',
            'variable_usage_section'
        );
    }

    /**
     * Callback for the "lmfwc_activation_name_string" field.
     *
     * @return void
     */
    public function fieldActivationNameString()
    {
        $fieldSingular = self::ACTIVATION_NAME_FIELD_NAME . '_singular';
        $fieldPlural = self::ACTIVATION_NAME_FIELD_NAME . '_plural';
        (array_key_exists($fieldSingular, $this->settings)) ? $singular = $this->settings[$fieldSingular] : $singular = self::DEFAULT_ACTIVATION_NAME_SINGULAR;
        (array_key_exists($fieldPlural, $this->settings)) ? $plural = $this->settings[$fieldPlural] : $plural = self::DEFAULT_ACTIVATION_NAME_PLURAL;

        $singular = __("" . $singular, 'license-manager-for-woocommerce');
        $plural = __("" . $plural, 'license-manager-for-woocommerce');

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $fieldSingular);
        $html .= sprintf(
            '<input id="%s" type="text" name="'.Settings::SECTION_SUBSCRIPTION.'[%s]" value="%s" />',
            $fieldSingular,
            $fieldSingular,
            $singular
        );
        $html .= ' <em>Singular</em>';
        $html .= '<br>';
        $html .= sprintf(
            '<input id="%s" type="text" name="'.Settings::SECTION_SUBSCRIPTION.'[%s]" value="%s" />',
            $fieldPlural,
            $fieldPlural,
            $plural
        );
        $html .= ' <em>Plural</em>';
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('How an activation should be called in the front end (for customers). Used on variable subscription model products were the cost is multiplied by the activation count.', 'license-manager-for-woocommerce')
        );
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Callback for the "lmfwc_display_included_activations" field.
     *
     * @return void
     */
    public function fieldDisplayIncludedActivations()
    {
        $field = self::SHOW_MAXIMUM_INCLUDED_ACTIVATIONS_FIELD_NAME;
        (array_key_exists($field, $this->settings)) ? $value = true : $value = false;

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="checkbox" name="'.Settings::SECTION_SUBSCRIPTION.'[%s]" value="1" %s/>',
            $field,
            $field,
            checked(true, $value, false)
        );
        $html .= sprintf('<span>%s</span>', __('Show maximum included activations.', 'license-manager-for-woocommerce'));
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('Displays the maximum included activations in the price string on the product page and cart.', 'license-manager-for-woocommerce')
        );
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Callback for the "lmfwc_display_single_activation_price" field.
     *
     * @return void
     */
    public function fieldDisplayActivationPrice()
    {
        $field = self::SHOW_SINGLE_ACTIVATION_PRICE_FIELD_NAME;
        (array_key_exists($field, $this->settings)) ? $value = true : $value = false;

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="checkbox" name="'.Settings::SECTION_SUBSCRIPTION.'[%s]" value="1" %s/>',
            $field,
            $field,
            checked(true, $value, false)
        );
        $html .= sprintf('<span>%s</span>', __('Show single activation price.', 'license-manager-for-woocommerce'));
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('Displays the cost of a single in the price string on the product page and cart.', 'license-manager-for-woocommerce')
        );
        $html .= '</fieldset>';

        echo $html;
    }

    /**
     * Callback for the "lmfwc_activation_price_decimals" field.
     *
     * @return void
     */
    public function fieldActivationPriceDecimals()
    {
        $field = self::ACTIVATION_PRICE_DECIMALS_FIELD_NAME;
        (array_key_exists($field, $this->settings)) ? $value = $this->settings[$field] : $value = wc_get_price_decimals();

        $html = '<fieldset>';
        $html .= sprintf('<label for="%s">', $field);
        $html .= sprintf(
            '<input id="%s" type="number" name="'.Settings::SECTION_SUBSCRIPTION.'[%s]" value="%s" step="1" min="1"/>',
            $field,
            $field,
            $value
        );
        $html .= '</label>';
        $html .= sprintf(
            '<p class="description">%s</p>',
            __('Define how many decimals will be shown on the price of a single activation.', 'license-manager-for-woocommerce')
        );
        $html .= '</fieldset>';

        echo $html;
    }
}