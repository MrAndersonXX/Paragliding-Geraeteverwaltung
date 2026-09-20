<?php

namespace Glider;

class InspectionCalculator
{
    public static function nextDate(string $baseDate, int $intervalMonths): string
    {
        if ($intervalMonths <= 0) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $baseDate);
        if ($date === false || $date->format('Y-m-d') !== $baseDate) {
            return '';
        }

        $day = (int) $date->format('d');
        $targetMonth = $date->modify('first day of this month')->modify('+' . $intervalMonths . ' months');
        $day = min($day, (int) $targetMonth->format('t'));

        return $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('m'),
            $day
        )->format('Y-m-d');
    }
}
