<?php
function timeAgo($timestamp, $user_timezone = null) {
    if (!$user_timezone) {
        $user_timezone = 'Europe/Istanbul'; // Varsayılan saat dilimi
    }

    $user_dt = new DateTime('now', new DateTimeZone($user_timezone));
    $timestamp_dt = new DateTime($timestamp, new DateTimeZone('UTC'));
    $timestamp_dt->setTimezone(new DateTimeZone($user_timezone));

    $time_difference = $user_dt->getTimestamp() - $timestamp_dt->getTimestamp();

    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);

    if ($seconds <= 60) {
        return __("Az önce");
    } elseif ($minutes <= 60) {
        return $minutes == 1 ? __("1 dakika önce") : sprintf(__("%s dakika önce"), $minutes);
    } elseif ($hours <= 24) {
        return $hours == 1 ? __("1 saat önce") : sprintf(__("%s saat önce"), $hours);
    } elseif ($days <= 7) {
        return $days == 1 ? __("Dün") : sprintf(__("%s gün önce"), $days);
    } elseif ($weeks <= 4.3) {
        return $weeks == 1 ? __("1 hafta önce") : sprintf(__("%s hafta önce"), $weeks);
    } elseif ($months <= 12) {
        return $months == 1 ? __("1 ay önce") : sprintf(__("%s ay önce"), $months);
    } else {
        return $years == 1 ? __("1 yıl önce") : sprintf(__("%s yıl önce"), $years);
    }
}
?>