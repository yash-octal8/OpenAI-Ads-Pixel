<?php

namespace App\Helpers;

use App\Models\Setting;
use App\Repositories\Internal\SettingRepository;

class SettingHelper
{
    public static function getNotificationSettings($shop, $settings = []) {
        if (!$settings) {
            $settings = (new SettingRepository())->getGroupSettings($shop, Setting::GROUP_EMAIL);
        }

        $settings = collect($settings)->pluck('value', 'key')->toArray();

        $fromName = data_get($settings, 'from');
        $from = data_get($settings, 'sender_email') ?: $shop->email;

        if ($fromName) {
            $from = "$fromName <$from>";
        }

        return array_filter([
            'from' => $from,
            'subject' => data_get($settings, 'subject'),
            'customMessage' => data_get($settings, 'custom_message'),
        ]);
    }
}
