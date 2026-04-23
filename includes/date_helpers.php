<?php

if (!function_exists('zz_format_date')) {
    /**
     * Format a date string into a human-friendly format.
     *
     * @param string|null $dateStr Y-m-d or datetime string.
     * @param string $mode 'relative' | 'calendar' | 'smart'
     * @return string
     */
    function zz_format_date(?string $dateStr, string $mode = 'smart'): string
    {
        if ($dateStr === null || trim($dateStr) === '') {
            return '—';
        }

        $timestamp = strtotime($dateStr);
        if ($timestamp === false) {
            return htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
        }

        $today = strtotime('today');
        $diffDays = (int) round(($timestamp - $today) / 86400);
        $sameYear = (date('Y', $timestamp) === date('Y'));

        if ($mode === 'relative' || ($mode === 'smart' && abs($diffDays) <= 7)) {
            if ($diffDays === 0) {
                return 'Today';
            }

            if ($diffDays === 1) {
                return 'Tomorrow';
            }

            if ($diffDays === -1) {
                return 'Yesterday';
            }

            if ($diffDays > 1 && $diffDays <= 7) {
                return 'in ' . $diffDays . ' days';
            }

            if ($diffDays < -1 && $diffDays >= -7) {
                return abs($diffDays) . ' days ago';
            }
        }

        if ($sameYear) {
            return date('M j', $timestamp);
        }

        return date('M j, Y', $timestamp);
    }
}

if (!function_exists('zz_format_date_range')) {
    /**
     * Format a date range for goals (start -> end).
     */
    function zz_format_date_range(?string $start, ?string $end): string
    {
        $s = zz_format_date($start, 'calendar');
        $e = zz_format_date($end, 'calendar');

        if ($s === '—' && $e === '—') {
            return 'No dates set';
        }

        if ($s === '—') {
            return 'Until ' . $e;
        }

        if ($e === '—') {
            return 'From ' . $s;
        }

        return $s . ' — ' . $e;
    }
}

if (!function_exists('zz_format_datetime')) {
    /**
     * Format a datetime string with time included.
     */
    function zz_format_datetime(?string $dateTimeStr): string
    {
        if ($dateTimeStr === null || trim($dateTimeStr) === '') {
            return '—';
        }

        $timestamp = strtotime($dateTimeStr);
        if ($timestamp === false) {
            return htmlspecialchars($dateTimeStr, ENT_QUOTES, 'UTF-8');
        }

        $diffDays = (int) round(($timestamp - strtotime('today')) / 86400);

        if ($diffDays === 0) {
            return 'Today at ' . date('g:i A', $timestamp);
        }

        if ($diffDays === -1) {
            return 'Yesterday at ' . date('g:i A', $timestamp);
        }

        $sameYear = (date('Y', $timestamp) === date('Y'));
        if ($sameYear) {
            return date('M j \a\t g:i A', $timestamp);
        }

        return date('M j, Y \a\t g:i A', $timestamp);
    }
}
