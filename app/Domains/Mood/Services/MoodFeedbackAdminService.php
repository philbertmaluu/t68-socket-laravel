<?php

namespace App\Domains\Mood\Services;

use App\Domains\Feedback\Models\Feedback;
use App\Domains\Authentication\Models\User;
use App\Domains\Mood\Models\MoodCounterFeedback;
use App\Domains\Mood\Models\MoodFeedbackReason;
use App\Domains\Mood\Models\MoodGeneralFeedback;
use App\Domains\Mood\Models\MoodRatingOption;
use App\Traits\UserOfficeTrait;
use Illuminate\Support\Collection;

class MoodFeedbackAdminService
{
    use UserOfficeTrait;

    /**
     * @return array{items: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function listForCurrentOffice(array $filters = []): array
    {
        $officeId = $this->getUserOfficeAndRegionFromHrp()['office_id'];

        return $this->listForOffice((string) $officeId, $filters);
    }

    /**
     * @return array{items: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function listForOffice(string $officeId, array $filters = []): array
    {
        $type = strtolower(trim((string) ($filters['type'] ?? 'all')));
        $search = trim((string) ($filters['search'] ?? ''));
        $ratingScore = isset($filters['rating_score']) && $filters['rating_score'] !== ''
            ? (int) $filters['rating_score']
            : null;

        $items = collect();

        if ($type === 'all' || $type === 'general') {
            $items = $items->merge($this->generalRows($officeId, $ratingScore));
        }

        if ($type === 'all' || $type === 'counter') {
            $items = $items->merge($this->counterRows($officeId, $ratingScore));
        }

        if ($type === 'all' || $type === 'link') {
            $items = $items->merge($this->linkRows($officeId, $ratingScore));
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = $items->filter(function (array $row) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['device_name'] ?? null,
                    $row['rating_title'] ?? null,
                    $row['reason_title'] ?? null,
                    $row['comment'] ?? null,
                    $row['counter_id'] ?? null,
                    $row['ticket_id'] ?? null,
                    $row['ticket_number'] ?? null,
                    $row['officer_id'] ?? null,
                    $row['clerk_name'] ?? null,
                    $row['source'] ?? null,
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $sorted = $items
            ->sortByDesc(fn (array $row) => $row['submitted_at'] ?? '')
            ->values();

        return [
            'items' => $sorted->all(),
            'summary' => $this->buildSummary($sorted),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generalRows(string $officeId, ?int $ratingScore): array
    {
        $query = MoodGeneralFeedback::query()
            ->with(['device:id,name,office_id'])
            ->where('branch_id', $officeId)
            ->orderByDesc('submitted_at');

        if ($ratingScore !== null) {
            $query->where('rating_score', $ratingScore);
        }

        $rows = $query->get();
        $options = $this->ratingOptionsById($rows->pluck('rating_option_id'));
        $reasons = $this->reasonsById($rows->pluck('reason_id'));

        return $rows->map(function (MoodGeneralFeedback $row) use ($options, $reasons) {
            $option = $options->get($row->rating_option_id);
            $reason = $reasons->get($row->reason_id);

            return [
                'id' => 'general-'.$row->id,
                'source_id' => (string) $row->id,
                'type' => 'general',
                'channel' => 'mood_tablet',
                'rating_score' => (int) $row->rating_score,
                'rating_emoji' => $option?->emoji,
                'rating_title' => $option?->title,
                'reason_title' => $reason?->title,
                'comment' => $row->comment,
                'device_id' => $row->device_id ? (string) $row->device_id : null,
                'device_name' => $row->device?->name,
                'counter_id' => null,
                'ticket_id' => null,
                'ticket_number' => null,
                'officer_id' => null,
                'clerk_name' => null,
                'source' => 'mood-checker',
                'branch_id' => (string) $row->branch_id,
                'submitted_at' => optional($row->submitted_at)->toIso8601String(),
                'synced_from_offline' => (bool) $row->synced_from_offline,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function counterRows(string $officeId, ?int $ratingScore): array
    {
        $query = MoodCounterFeedback::query()
            ->with([
                'device:id,name,office_id',
                'session:id,branch_id',
                'ticket:id,clerk_id',
            ])
            ->where(function ($q) use ($officeId) {
                $q->whereHas('device', fn ($d) => $d->where('office_id', $officeId))
                    ->orWhereHas('session', fn ($s) => $s->where('branch_id', $officeId));
            })
            ->orderByDesc('submitted_at');

        if ($ratingScore !== null) {
            $query->where('rating_score', $ratingScore);
        }

        $rows = $query->get();
        $options = $this->ratingOptionsById($rows->pluck('rating_option_id'));
        $reasons = $this->reasonsById($rows->pluck('reason_id'));
        $clerkIds = $rows->pluck('officer_id')
            ->merge($rows->map(fn (MoodCounterFeedback $row) => $row->ticket?->clerk_id));
        $clerkNames = $this->clerkNamesByIds($clerkIds);

        return $rows->map(function (MoodCounterFeedback $row) use ($options, $reasons, $clerkNames) {
            $option = $options->get($row->rating_option_id);
            $reason = $reasons->get($row->reason_id);
            $clerkId = $row->officer_id ?: $row->ticket?->clerk_id;

            return [
                'id' => 'counter-'.$row->id,
                'source_id' => (string) $row->id,
                'type' => 'counter',
                'channel' => 'mood_tablet',
                'rating_score' => (int) $row->rating_score,
                'rating_emoji' => $option?->emoji,
                'rating_title' => $option?->title,
                'reason_title' => $reason?->title,
                'comment' => $row->comment,
                'device_id' => $row->device_id ? (string) $row->device_id : null,
                'device_name' => $row->device?->name,
                'counter_id' => $row->counter_id ? (string) $row->counter_id : null,
                'ticket_id' => $row->ticket_id ? (string) $row->ticket_id : null,
                'ticket_number' => null,
                'officer_id' => $clerkId ? (string) $clerkId : null,
                'clerk_name' => $this->resolveClerkName($clerkId ? (string) $clerkId : null, $clerkNames),
                'source' => 'mood-checker',
                'branch_id' => (string) ($row->session?->branch_id ?? $row->device?->office_id ?? ''),
                'submitted_at' => optional($row->submitted_at)->toIso8601String(),
                'synced_from_offline' => (bool) $row->synced_from_offline,
            ];
        })->all();
    }

    /**
     * Feedback submitted via SMS/QR link (`feedbacks` table).
     *
     * @return list<array<string, mixed>>
     */
    private function linkRows(string $officeId, ?int $ratingScore): array
    {
        $query = Feedback::query()
            ->with(['ticket:id,clerk_id'])
            ->where('office_id', $officeId)
            ->orderByDesc('submitted_at');

        if ($ratingScore !== null) {
            $query->where('rating', $ratingScore);
        }

        $rows = $query->get();
        $clerkIds = $rows->pluck('clerk_id')
            ->merge($rows->map(fn (Feedback $row) => $row->ticket?->clerk_id));
        $clerkNames = $this->clerkNamesByIds($clerkIds);

        return $rows->map(function (Feedback $row) use ($clerkNames) {
            $comment = $row->comment_text
                ?: $row->comment_label
                ?: $row->general_comment;
            $clerkId = $row->clerk_id ?: $row->ticket?->clerk_id;

            return [
                'id' => 'link-'.$row->id,
                'source_id' => (string) $row->id,
                'type' => 'link',
                'channel' => (string) ($row->feedback_type ?? 'general'),
                'rating_score' => (int) $row->rating,
                'rating_emoji' => null,
                'rating_title' => null,
                'reason_title' => $row->comment_label ?: $row->comment_key,
                'comment' => $comment,
                'device_id' => null,
                'device_name' => null,
                'counter_id' => null,
                'ticket_id' => $row->ticket_id ? (string) $row->ticket_id : null,
                'ticket_number' => $row->ticket_number ? (string) $row->ticket_number : null,
                'officer_id' => $clerkId ? (string) $clerkId : null,
                'clerk_name' => $this->resolveClerkName($clerkId ? (string) $clerkId : null, $clerkNames),
                'source' => $row->source ? (string) $row->source : 'feedback-link',
                'branch_id' => (string) ($row->office_id ?? ''),
                'submitted_at' => optional($row->submitted_at)->toIso8601String(),
                'synced_from_offline' => false,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<string, string>
     */
    private function clerkNamesByIds(Collection $ids): Collection
    {
        $ids = $ids
            ->filter(fn ($id) => $id !== null && (string) $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->select(['id', 'user_id', 'name'])
            ->where(function ($query) use ($ids) {
                $query->whereIn('id', $ids)->orWhereIn('user_id', $ids);
            })
            ->get();

        $names = collect();
        foreach ($users as $user) {
            if ($user->name) {
                $names[(string) $user->id] = (string) $user->name;
                if ($user->user_id) {
                    $names[(string) $user->user_id] = (string) $user->name;
                }
            }
        }

        return $names;
    }

    private function resolveClerkName(?string $clerkId, Collection $names): ?string
    {
        if ($clerkId === null || $clerkId === '') {
            return null;
        }

        return $names->get($clerkId);
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int|string, MoodRatingOption>
     */
    private function ratingOptionsById(Collection $ids): Collection
    {
        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return MoodRatingOption::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int|string, MoodFeedbackReason>
     */
    private function reasonsById(Collection $ids): Collection
    {
        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return MoodFeedbackReason::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $items): array
    {
        $total = $items->count();
        $general = $items->where('type', 'general')->count();
        $counter = $items->where('type', 'counter')->count();
        $link = $items->where('type', 'link')->count();
        $avg = $total > 0
            ? round((float) $items->avg('rating_score'), 2)
            : 0.0;
        $positive = $items->where('rating_score', '>=', 4)->count();
        $negative = $items->where('rating_score', '<=', 2)->count();

        return [
            'total' => $total,
            'general' => $general,
            'counter' => $counter,
            'link' => $link,
            'average_score' => $avg,
            'positive' => $positive,
            'negative' => $negative,
        ];
    }
}
