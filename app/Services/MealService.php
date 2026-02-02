<?php

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\Meal;
use App\Models\Food;
use Carbon\Carbon;

class MealService
{
    /**
     * Get today's meals for a user
     */
    public function getTodaysMeals(TelegramUser $telegramUser): \Illuminate\Support\Collection
    {
        return $telegramUser->meals()
            ->whereDate('consumed_at', today())
            ->orderBy('consumed_at', 'asc')
            ->get();
    }

    /**
     * Get meals for a specific date
     */
    public function getMealsForDate(TelegramUser $telegramUser, Carbon $date): \Illuminate\Support\Collection
    {
        return $telegramUser->meals()
            ->whereDate('consumed_at', $date)
            ->orderBy('consumed_at', 'asc')
            ->get();
    }

    /**
     * Get meals for a date range (for weekly/monthly views)
     */
    public function getMealsForRange(TelegramUser $telegramUser, Carbon $startDate, Carbon $endDate): \Illuminate\Support\Collection
    {
        return $telegramUser->meals()
            ->whereBetween('consumed_at', [$startDate, $endDate])
            ->orderBy('consumed_at', 'asc')
            ->get();
    }

    /**
     * Get daily nutrition summary
     */
    public function getDailyNutritionSummary(TelegramUser $telegramUser, Carbon $date): array
    {
        $meals = $this->getMealsForDate($telegramUser, $date);

        return [
            'total_calories' => $meals->sum('calories'),
            'total_protein' => $meals->sum('protein'),
            'total_carbs' => $meals->sum('carbs'),
            'total_fat' => $meals->sum('fat'),
            'meal_count' => $meals->count(),
            'meals' => $meals
        ];
    }

    /**
     * Get weekly nutrition summary
     */
    public function getWeeklyNutritionSummary(TelegramUser $telegramUser, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->endOfWeek();
        $meals = $this->getMealsForRange($telegramUser, $startDate, $endDate);

        $dailySummaries = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $dailyMeals = $meals->filter(function ($meal) use ($currentDate) {
                return $meal->consumed_at->toDateString() === $currentDate->toDateString();
            });

            $dailySummaries[$currentDate->toDateString()] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $currentDate->format('D'),
                'total_calories' => $dailyMeals->sum('calories'),
                'total_protein' => $dailyMeals->sum('protein'),
                'total_carbs' => $dailyMeals->sum('carbs'),
                'total_fat' => $dailyMeals->sum('fat'),
                'meal_count' => $dailyMeals->count(),
            ];
            
            $currentDate->addDay();
        }

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_calories' => $meals->sum('calories'),
            'total_protein' => $meals->sum('protein'),
            'total_carbs' => $meals->sum('carbs'),
            'total_fat' => $meals->sum('fat'),
            'total_meal_count' => $meals->count(),
            'daily_summaries' => $dailySummaries,
        ];
    }

    /**
     * Get monthly nutrition summary
     */
    public function getMonthlyNutritionSummary(TelegramUser $telegramUser, Carbon $month): array
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();
        $meals = $this->getMealsForRange($telegramUser, $startDate, $endDate);

        $weeklySummaries = [];
        $currentWeekStart = $startDate->copy()->startOfWeek();
        $endOfWeek = $endDate->copy()->endOfWeek();
        
        while ($currentWeekStart->lessThanOrEqualTo($endOfWeek)) {
            $weekEnd = $currentWeekStart->copy()->endOfWeek();
            
            $weeklyMeals = $meals->filter(function ($meal) use ($currentWeekStart, $weekEnd) {
                return $meal->consumed_at->between($currentWeekStart, $weekEnd);
            });

            $weeklySummaries[] = [
                'week_start' => $currentWeekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'total_calories' => $weeklyMeals->sum('calories'),
                'total_protein' => $weeklyMeals->sum('protein'),
                'total_carbs' => $weeklyMeals->sum('carbs'),
                'total_fat' => $weeklyMeals->sum('fat'),
                'meal_count' => $weeklyMeals->count(),
            ];
            
            $currentWeekStart->addWeek();
        }

        return [
            'month' => $month->format('F Y'),
            'total_calories' => $meals->sum('calories'),
            'total_protein' => $meals->sum('protein'),
            'total_carbs' => $meals->sum('carbs'),
            'total_fat' => $meals->sum('fat'),
            'total_meal_count' => $meals->count(),
            'weekly_summaries' => $weeklySummaries,
        ];
    }

    /**
     * Delete a specific meal
     */
    public function deleteMeal(Meal $meal): bool
    {
        return $meal->delete();
    }

    /**
     * Update a meal
     */
    public function updateMeal(Meal $meal, array $data): Meal
    {
        $meal->update($data);
        return $meal;
    }

    /**
     * Get most commonly eaten foods for a user
     */
    public function getTopFoods(TelegramUser $telegramUser, int $limit = 10): \Illuminate\Support\Collection
    {
        return $telegramUser->meals()
            ->select('food_id', 'name', \DB::raw('COUNT(*) as count'), \DB::raw('AVG(calories) as avg_calories'))
            ->groupBy('food_id', 'name')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }
}