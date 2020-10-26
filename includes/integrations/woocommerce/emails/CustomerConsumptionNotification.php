<?php

namespace LicenseManagerForWooCommerce\Integrations\WooCommerce\Emails;

use WC_Email;
use WP_User;

defined('ABSPATH') || exit;

class CustomerConsumptionNotification extends WC_Email
{
    /**
     * CustomerConsumptionNotification constructor.
     */
    function __construct()
    {
        // Email slug we can use to filter other data.
        $this->id          = 'lmfwc_email_customer_consumption_notification';
        $this->title       = __('License Key consumption notification', 'license-manager-for-woocommerce');
        $this->description = __('An automatic email that is send out to the user, when a consumption threshold is reached.', 'license-manager-for-woocommerce');

        // For admin area to let the user know we are sending this email to customers.
        $this->customer_email = true;
        $this->heading        = __('Notification', 'license-manager-for-woocommerce');

        // translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
        $this->subject = sprintf(
            _x(
                '[%s] - License key notification!',
                'Default email subject for resent consumption notification sent to the customer',
                'license-manager-for-woocommerce'
            ),
            '{blogname}'
        );

        // Template paths.
        $this->template_html  = 'emails/lmfwc-email-consumption-notification.php';
        $this->template_plain = 'emails/plain/lmfwc-email-consumption-notification.php';
        $this->template_base  = LMFWC_TEMPLATES_DIR;

        // Action to which we hook onto to send the email.
        add_action('lmfwc_email_customer_consumption_notification', array($this, 'trigger'));

        parent::__construct();
    }

    /**
     * Retrieves the HTML content of the email.
     *
     * @return string
     */
    public function get_content_html()
    {
        return wc_get_template_html(
            $this->template_html,
            array(
                'user'          => $this->object,
                'license_count' => $this->placeholders['{license_count}'],
                'threshold'     => $this->placeholders['{threshold}'],
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Retrieves the plain text content of the email.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'user'          => $this->object,
                'license_count' => $this->placeholders['{license_count}'],
                'threshold'     => $this->placeholders['{threshold}'],
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Trigger the sending of the consumption data.
     *
     * @param WP_User $user         the email of the user associated with the license
     * @param int     $count        the sum of licenses that have reached the consumption threshold
     * @param int     $threshold    the threshold
     */
    public function trigger($user, $threshold, $count)
    {
        $this->setup_locale();

        $ok = false;
        $sent = false;

        if (is_a($user, 'WP_User') && $count && $threshold) {
            $this->object                           = $user;
            $this->recipient                        = $user->user_email;
            $this->placeholders['{license_count}']  = $count;
            $this->placeholders['{threshold}']      = $threshold;

            $ok = true;
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
        }

        $this->restore_locale();

        return $ok && $sent;
    }
}