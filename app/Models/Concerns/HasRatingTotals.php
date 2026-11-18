<?php

namespace App\Models\Concerns;

/**
 * For models with running review totals (rating_count, rating_sum):
 * products and stores. Kept in one place so "4,3 from 12" is computed
 * the same way everywhere.
 */
trait HasRatingTotals
{
    /**
     * Null when nobody reviewed it yet: show no stars rather than "0".
     *
     * @return array{average: float, count: int}|null
     */
    public function ratingSummary(): ?array
    {
        $count = (int) $this->rating_count;

        if ($count === 0) {
            return null;
        }

        return [
            'average' => round($this->rating_sum / $count, 1),
            'count' => $count,
        ];
    }
}
