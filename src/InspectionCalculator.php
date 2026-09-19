<?php

namespace Glider;

class InspectionCalculator
{
    public static function nextDate(string $lastInspectionDate, int $intervalDays): string
    {
        if ($lastInspectionDate === '' || $intervalDays <= 0) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $lastInspectionDate);
        if ($date === false || $date->format('Y-m-d') !== $lastInspectionDate) {
            return '';
        }

        return $date->modify('+' . $intervalDays . ' days')->format('Y-m-d');
    }
}
