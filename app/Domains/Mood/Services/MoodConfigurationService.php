<?php

namespace App\Domains\Mood\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodConfiguration;
use App\Domains\Mood\Models\MoodFeedbackReason;
use App\Domains\Mood\Models\MoodRatingOption;

class MoodConfigurationService
{
    public function getConfiguration(Device $device, ?string $locale = null): array
    {
        $locale = $locale ?: 'en';

        return [
            'device' => (new MoodAuthService())->formatDevice($device),
            'theme' => $this->getTheme($device, $locale),
            'languages' => $this->getLanguages($device),
            'messages' => $this->getMessages($device, $locale),
            'company' => $this->getCompany($device, $locale),
            'rating_options' => $this->getRatingOptions($device, $locale),
            'feedback_reasons' => $this->getFeedbackReasons($device, $locale),
            'timeouts' => $this->getTimeouts($device, $locale),
            'version' => $this->getConfigVersion($device, $locale),
        ];
    }

    public function getTheme(Device $device, ?string $locale = null): array
    {
        $config = $this->resolveConfig($device, $locale);

        return $config?->theme ?? [
            'primary_color' => '#902D30',
            'secondary_color' => '#2E7D32',
            'accent_color' => '#EAB308',
            'gold_color' => '#C9A227',
            'gradient_start' => '#902D30',
            'gradient_end' => '#5C1820',
            'glass_opacity' => 0.18,
            'background_animation' => 'gradient_mesh',
        ];
    }

    public function getLanguages(Device $device): array
    {
        return [
            ['code' => 'en', 'label' => 'English', 'default' => true],
            ['code' => 'sw', 'label' => 'Kiswahili', 'default' => false],
        ];
    }

    public function getMessages(Device $device, ?string $locale = null): array
    {
        $config = $this->resolveConfig($device, $locale);

        return $config?->messages ?? [
            'idle_prompt' => 'How was your experience today?',
            'thank_you' => 'Thank you for your feedback.',
            'reason_prompt' => 'What can we improve?',
            'session_expired' => 'Session expired. Thank you.',
        ];
    }

    public function getCompany(Device $device, ?string $locale = null): array
    {
        $config = $this->resolveConfig($device, $locale);

        return $config?->company ?? [
            'name' => 'NSSF',
            'logo_url' => null,
        ];
    }

    public function getRatingOptions(Device $device, ?string $locale = null): array
    {
        $locale = $locale ?: 'en';
        $tenantId = (int) $device->tenant_id;

        $options = MoodRatingOption::withoutGlobalScope('tenant')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->where('locale', $locale)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        if ($options->isEmpty() && $locale !== 'en') {
            return $this->getRatingOptions($device, 'en');
        }

        return $options->map(fn (MoodRatingOption $option) => [
            'id' => $option->id,
            'key' => $option->key,
            'title' => $option->title,
            'emoji' => $option->emoji,
            'color' => $option->color,
            'score' => $option->score,
        ])->values()->all();
    }

    public function getFeedbackReasons(Device $device, ?string $locale = null, ?string $category = null): array
    {
        $locale = $locale ?: 'en';
        $tenantId = (int) $device->tenant_id;

        $query = MoodFeedbackReason::withoutGlobalScope('tenant')
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->where('locale', $locale)
            ->where('active', true)
            ->orderBy('sort_order');

        if ($category !== null) {
            $query->where('category', $category);
        }

        $reasons = $query->get();

        if ($reasons->isEmpty() && $locale !== 'en') {
            return $this->getFeedbackReasons($device, 'en', $category);
        }

        return $reasons->map(fn (MoodFeedbackReason $reason) => [
            'id' => $reason->id,
            'key' => $reason->key,
            'title' => $reason->title,
            'category' => $reason->category,
            'applies_to_ratings' => $reason->applies_to_ratings ?? [],
        ])->values()->all();
    }

    public function getGeneralReasons(Device $device, ?string $locale = null): array
    {
        return $this->getFeedbackReasons($device, $locale, 'general');
    }

    public function getTimeouts(Device $device, ?string $locale = null): array
    {
        $config = $this->resolveConfig($device, $locale);

        return $config?->timeouts ?? [
            'thank_you_seconds' => 5,
            'counter_session_seconds' => 30,
            'heartbeat_seconds' => 30,
        ];
    }

    public function getConfigVersion(Device $device, ?string $locale = null): int
    {
        $config = $this->resolveConfig($device, $locale);

        return (int) ($config?->version ?? 1);
    }

    private function resolveConfig(Device $device, ?string $locale = null): ?MoodConfiguration
    {
        $locale = $locale ?: 'en';
        $tenantId = (int) $device->tenant_id;

        $config = $this->findActiveConfig($tenantId, $locale);
        if ($config === null && $locale !== 'en') {
            $config = $this->findActiveConfig($tenantId, 'en');
        }

        return $config;
    }

    private function findActiveConfig(int $tenantId, string $locale): ?MoodConfiguration
    {
        // Prefer tenant-specific config over global defaults.
        // Avoid `ORDER BY tenant_id IS NULL` — invalid on Oracle (ORA-00907).
        return MoodConfiguration::withoutGlobalScope('tenant')
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->where('locale', $locale)
            ->where('active', true)
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END ASC')
            ->first();
    }
}
