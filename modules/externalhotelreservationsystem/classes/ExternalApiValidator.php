<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ExternalApiValidator
{
    public static function validateDateRange($checkIn, $checkOut)
    {
        if (!Validate::isDate($checkIn) || !Validate::isDate($checkOut)) {
            throw new InvalidArgumentException('Invalid date format');
        }
        
        if (strtotime($checkIn) < strtotime(date('Y-m-d'))) {
            throw new InvalidArgumentException('Check-in date cannot be in the past');
        }
        
        if (strtotime($checkOut) <= strtotime($checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in');
        }
    }
    
    public static function validateOccupancy($adults, $children = 0)
    {
        if (!Validate::isUnsignedInt($adults) || $adults < 1 || $adults > 10) {
            throw new InvalidArgumentException('Adults must be between 1 and 10');
        }
        
        if (!Validate::isUnsignedInt($children) || $children < 0 || $children > 6) {
            throw new InvalidArgumentException('Children must be between 0 and 6');
        }
    }
}