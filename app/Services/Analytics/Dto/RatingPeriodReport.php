<?php

declare(strict_types=1);

namespace App\Services\Analytics\Dto;

use Carbon\CarbonImmutable;

/**
 * Laporan distribusi bintang untuk sebuah tempat pada satu rentang tanggal.
 */
final class RatingPeriodReport
{
    /**
     * @param  array<int, int>  $distribution  [5 => n, 4 => n, ..., 1 => n]
     */
    public function __construct(
        public readonly string $placeName,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly array $distribution,
        public readonly int $total,
        public readonly ?float $average,
    ) {}

    /**
     * Persentase per bintang.
     *
     * @return array<int, float>
     */
    public function percentages(): array
    {
        if ($this->total === 0) {
            return array_fill_keys([5, 4, 3, 2, 1], 0.0);
        }

        $out = [];
        foreach ($this->distribution as $star => $count) {
            $out[$star] = round($count / $this->total * 100, 1);
        }

        return $out;
    }

    /**
     * Ringkas untuk ditampilkan.
     *
     * @return array{place: string, from: string, to: string, total: int, average: ?float, distribution: array<int, int>, percentages: array<int, float>}
     */
    public function toArray(): array
    {
        return [
            'place' => $this->placeName,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'total' => $this->total,
            'average' => $this->average,
            'distribution' => $this->distribution,
            'percentages' => $this->percentages(),
        ];
    }
}
