<?php

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('coachSituationTypeLabel')) {
    function coachSituationTypeLabel(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = str_replace(['_', '-', '/'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = is_string($normalized) ? trim($normalized) : '';

        $labels = [
            'pre performance nerves' => 'Pre-Performance Nerves',
            'after mistake' => 'After a Mistake',
            'low focus' => 'Low Focus',
            'frustration anger' => 'Frustration / Anger',
            'confidence dip' => 'Confidence Dip',
            'post practice reset' => 'Post-Session Reset',
            'other' => 'Other',
        ];

        if ($normalized === '') {
            return 'Other';
        }

        return $labels[$normalized] ?? ucwords($normalized);
    }
}
