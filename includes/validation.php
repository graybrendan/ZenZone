<?php

function cleanInput($value) {
    return trim($value);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isStrongEnoughPassword($password) {
    return strlen($password) >= 8;
}

function isValidScaleRating($value, $min = 1, $max = 7) {
    if (!is_numeric($value)) {
        return false;
    }

    $value = (int)$value;
    return $value >= $min && $value <= $max;
}

function isValidDateYmd($value) {
    if (!is_string($value) || $value === '') {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;
}
