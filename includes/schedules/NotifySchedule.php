<?php

namespace LicenseManagerForWooCommerce\Schedules;

use DateTime;
use Exception;
use LicenseManagerForWooCommerce\Settings;
use LicenseManagerForWooCommerce\Enums\LicenseStatus as LicenseStatusEnum;
use LicenseManagerForWooCommerce\Models\Resources\License as LicenseResourceModel;
use LicenseManagerForWooCommerce\Repositories\Resources\License as LicenseResourceRepository;
use LicenseManagerForWooCommerce\Models\Resources\LicenseMeta as LicenseMetaResourceModel;
use LicenseManagerForWooCommerce\Repositories\Resources\LicenseMeta as LicenseMetaResourceRepository;
use WP_User;

use WC_Order;

defined('ABSPATH') || exit;

class NotifySchedule
{
    /**
     * Name of the hook that should be scheduled
     */
    const HOOK_NAME = 'lmfwc_consumption_almost_reached_action';

    /**
     * Name of the meta key to save the info with
     */
    const META_NAME = 'consumption_notification_already_send';

    /**
     * The start time of the scheduled event
     */
    const START_TIME = 'now';

    /**
     * The recurrence of the event
     */
    const RECURRENCE = 'hourly';

    /**
     * NotifySchedule constructor.
     */
    public function __construct()
    {
        // email schedule action
        add_action(self::HOOK_NAME, array($this, 'maybeNotifyConsumptionAlmostReached'), 10);

        $notifyThreshold = Settings::get('lmfwc_email_notification_consumption');
        ($notifyThreshold !== null && !empty($notifyThreshold)) ? $this->schedule() : $this->remove();
    }

    /**
     * Sends out consumption notifications if required.
     */
    public function maybeNotifyConsumptionAlmostReached()
    {

        $notifyThreshold = Settings::get('lmfwc_email_notification_consumption');

        /** @var bool|LicenseResourceModel[] $licenses */
        $licenses = lmfwc_get_licenses(array(
            'status' => array(LicenseStatusEnum::DELIVERED, LicenseStatusEnum::ACTIVE)
        ));

        if (!$licenses) {
            return;
        }

        $licenseCount = count($licenses);
        $notificationSummary = array();

        /** @var LicenseResourceModel $license */
        foreach ($licenses as $license) {

            // Skip license that has expired
            try {
                $dateExpiresAt = new DateTime($license->getExpiresAt());

                $interval = $dateExpiresAt->diff(new DateTime())->format('%r%a');
                if (intval($interval) > 0) {
                    continue;
                }
            } catch (Exception $e) {
                continue;
            }

            // Skip license if there is no activation limit
            $timesActivatedMax = $license->getTimesActivatedMax();
            if (!$timesActivatedMax || $timesActivatedMax === 0) {
                continue;
            }

            // Skip license if it has not reached the required consumption threshold
            $timesActivated = $license->getTimesActivated();
            if (($timesActivated / $timesActivatedMax * 100) < $notifyThreshold) {
                continue;
            }

            // Skip if notification was already send
            $licenseId = $license->getId();

            /** @var bool $metaValue */
            $metaValue = boolval(lmfwc_get_license_meta($licenseId, self::META_NAME, true));
            if ($metaValue) {
                continue;
            }

            $userId = $license->getUserId();

            // license does not have a user assigned
            if (!$userId) {
                continue;
            }

            $notificationSummary[$userId][] = $licenseId;
        }

        /** @var int $user */
        /** @var int[] $ids */
        foreach ($notificationSummary as $userId => $ids) {

            $licenseCount = count($ids);

            /** @var WP_User $user */
            $user = get_user_by('id', $userId);

            // The given user does not exist
            if (!$user) {
                continue;
            }

            // send e-mail
            $sent = WC()->mailer()->emails['LMFWC_Customer_Consumption_Notification']->trigger($user, $notifyThreshold, $licenseCount);

            if ($sent) {
                foreach ($ids as $id) {
                    // update or add meta value
                    if (!lmfwc_update_license_meta($id, self::META_NAME, intval(true))) {
                        lmfwc_add_license_meta($id, self::META_NAME, intval(true));
                    }
                }
            }
        }
    }

    private function schedule($args = array())
    {
        if (!wp_next_scheduled(self::HOOK_NAME))
            wp_schedule_event(strtotime(self::START_TIME), self::RECURRENCE, self::HOOK_NAME, $args);
    }

    private function remove($args = array())
    {
        if (wp_next_scheduled(self::HOOK_NAME))
            wp_clear_scheduled_hook(self::HOOK_NAME, $args);
    }

}