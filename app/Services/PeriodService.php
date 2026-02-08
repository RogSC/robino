<?php

namespace App\Services;

use App\Models\Period;
use App\Models\TelegramUser;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PeriodService
{
    /**
     * Start a new period for a user.
     */
    public function startPeriod(TelegramUser $user, Carbon $startDate, ?string $notes = null): Period
    {
        // End any currently active period first
        $activePeriod = $user->getCurrentPeriod();
        if ($activePeriod) {
            $this->endPeriod($activePeriod, $startDate->copy()->subDay());
        }

        return Period::create([
            'telegram_user_id' => $user->id,
            'start_date' => $startDate,
            'notes' => $notes,
        ]);
    }

    /**
     * End an active period.
     */
    public function endPeriod(Period $period, Carbon $endDate): Period
    {
        $period->update([
            'end_date' => $endDate,
        ]);

        // Update user's average cycle and period length
        $this->updateUserAverages($period->telegramUser);

        return $period->fresh();
    }

    /**
     * Update user's average cycle and period lengths based on historical data.
     */
    public function updateUserAverages(TelegramUser $user): void
    {
        $periods = $user->periods()
            ->whereNotNull('end_date')
            ->orderBy('start_date', 'desc')
            ->limit(6) // Use last 6 periods for calculation
            ->get();

        if ($periods->count() >= 2) {
            // Calculate average period length
            $periodLengths = $periods->pluck('duration')->filter();
            if ($periodLengths->isNotEmpty()) {
                $user->average_period_length = (int) round($periodLengths->average());
            }

            // Calculate average cycle length
            $cycleLengths = [];
            for ($i = 0; $i < $periods->count() - 1; $i++) {
                $current = $periods[$i];
                $next = $periods[$i + 1];
                $cycleLengths[] = $next->start_date->diffInDays($current->start_date);
            }

            if (!empty($cycleLengths)) {
                $user->average_cycle_length = (int) round(array_sum($cycleLengths) / count($cycleLengths));
            }

            $user->save();
        }
    }

    /**
     * Predict next period start date.
     */
    public function predictNextPeriod(TelegramUser $user): ?Carbon
    {
        $lastPeriod = $user->periods()
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$lastPeriod) {
            return null;
        }

        return $lastPeriod->start_date->copy()->addDays($user->average_cycle_length);
    }

    /**
     * Predict next period end date.
     */
    public function predictNextPeriodEnd(TelegramUser $user): ?Carbon
    {
        $nextStart = $this->predictNextPeriod($user);
        
        if (!$nextStart) {
            return null;
        }

        return $nextStart->copy()->addDays($user->average_period_length - 1);
    }

    /**
     * Calculate fertility window (ovulation period).
     * Typically 12-16 days before next period.
     */
    public function calculateFertilityWindow(TelegramUser $user): ?array
    {
        $nextPeriod = $this->predictNextPeriod($user);

        if (!$nextPeriod) {
            return null;
        }

        // Ovulation typically occurs 14 days before next period
        $ovulationDay = $nextPeriod->copy()->subDays(14);
        
        // Fertility window is typically 5 days before and 1 day after ovulation
        $fertileStart = $ovulationDay->copy()->subDays(5);
        $fertileEnd = $ovulationDay->copy()->addDay();

        return [
            'ovulation_date' => $ovulationDay,
            'fertile_start' => $fertileStart,
            'fertile_end' => $fertileEnd,
            'is_fertile_now' => now()->between($fertileStart, $fertileEnd),
        ];
    }

    /**
     * Get current cycle day.
     */
    public function getCurrentCycleDay(TelegramUser $user): ?int
    {
        $lastPeriod = $user->periods()
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$lastPeriod) {
            return null;
        }

        return $lastPeriod->start_date->diffInDays(now()) + 1;
    }

    /**
     * Get days until next period.
     */
    public function getDaysUntilNextPeriod(TelegramUser $user): ?int
    {
        $nextPeriod = $this->predictNextPeriod($user);

        if (!$nextPeriod) {
            return null;
        }

        $days = now()->diffInDays($nextPeriod, false);
        return $days > 0 ? (int) $days : 0;
    }

    /**
     * Check if user is currently in their period.
     */
    public function isInPeriod(TelegramUser $user): bool
    {
        return $user->hasActivePeriod();
    }

    /**
     * Get period statistics for the user.
     */
    public function getPeriodStatistics(TelegramUser $user): array
    {
        $periods = $user->periods()
            ->whereNotNull('end_date')
            ->orderBy('start_date', 'desc')
            ->limit(12)
            ->get();

        if ($periods->isEmpty()) {
            return [
                'total_periods' => 0,
                'average_cycle_length' => $user->average_cycle_length,
                'average_period_length' => $user->average_period_length,
                'cycle_regularity' => 'unknown',
            ];
        }

        // Calculate cycle regularity (standard deviation)
        $cycleLengths = [];
        for ($i = 0; $i < $periods->count() - 1; $i++) {
            $current = $periods[$i];
            $next = $periods[$i + 1];
            $cycleLengths[] = $next->start_date->diffInDays($current->start_date);
        }

        $regularity = 'unknown';
        if (count($cycleLengths) >= 3) {
            $stdDev = $this->calculateStandardDeviation($cycleLengths);
            if ($stdDev <= 2) {
                $regularity = 'very_regular';
            } elseif ($stdDev <= 4) {
                $regularity = 'regular';
            } elseif ($stdDev <= 7) {
                $regularity = 'somewhat_irregular';
            } else {
                $regularity = 'irregular';
            }
        }

        return [
            'total_periods' => $periods->count(),
            'average_cycle_length' => $user->average_cycle_length,
            'average_period_length' => $user->average_period_length,
            'cycle_regularity' => $regularity,
            'last_period_date' => $periods->first()->start_date->toDateString(),
            'predicted_next_period' => $this->predictNextPeriod($user)?->toDateString(),
        ];
    }

    /**
     * Get period insights and analysis.
     */
    public function getPeriodInsights(TelegramUser $user): string
    {
        $stats = $this->getPeriodStatistics($user);
        $currentDay = $this->getCurrentCycleDay($user);
        $daysUntil = $this->getDaysUntilNextPeriod($user);
        $fertility = $this->calculateFertilityWindow($user);

        $insights = [];

        if ($this->isInPeriod($user)) {
            $insights[] = "🩸 You are currently in your period.";
        } elseif ($daysUntil !== null) {
            if ($daysUntil <= 3) {
                $insights[] = "⚠️ Your period is expected to start in {$daysUntil} day(s).";
            } elseif ($daysUntil <= 7) {
                $insights[] = "📅 Your period is expected to start in about {$daysUntil} days.";
            } else {
                $insights[] = "📆 Your next period is expected in {$daysUntil} days.";
            }
        }

        if ($currentDay) {
            $insights[] = "📊 Current cycle day: {$currentDay}";
        }

        if ($fertility && $fertility['is_fertile_now']) {
            $insights[] = "🌸 You are currently in your fertile window.";
        } elseif ($fertility) {
            $daysToFertile = now()->diffInDays($fertility['fertile_start'], false);
            if ($daysToFertile > 0 && $daysToFertile <= 5) {
                $insights[] = "🌸 Your fertile window starts in {$daysToFertile} day(s).";
            }
        }

        $regularityText = [
            'very_regular' => 'very regular',
            'regular' => 'regular',
            'somewhat_irregular' => 'somewhat irregular',
            'irregular' => 'irregular',
            'unknown' => 'unknown',
        ];

        $insights[] = "📈 Your cycle is {$regularityText[$stats['cycle_regularity']]}.";
        $insights[] = "⏱️ Average cycle: {$stats['average_cycle_length']} days, Average period: {$stats['average_period_length']} days.";

        return implode("\n", $insights);
    }

    /**
     * Calculate standard deviation.
     */
    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count <= 1) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / $count;

        return sqrt($variance);
    }

    /**
     * Add symptoms to a period.
     */
    public function addSymptoms(Period $period, array $symptoms): Period
    {
        $existingSymptoms = $period->symptoms ?? [];
        $period->symptoms = array_unique(array_merge($existingSymptoms, $symptoms));
        $period->save();

        return $period;
    }

    /**
     * Set flow intensity for a period.
     */
    public function setFlowIntensity(Period $period, int $intensity): Period
    {
        $period->flow_intensity = max(1, min(5, $intensity));
        $period->save();

        return $period;
    }
}
