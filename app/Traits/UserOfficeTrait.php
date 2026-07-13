<?php

namespace App\Traits;

use App\Domains\Authentication\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait UserOfficeTrait
{
    public function getUserOfficeAndRegionFromHrp(): array
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            throw new AuthenticationException('User not authenticated.');
        }

        $pfno = trim((string) ($user->user_id ?? ''));

        if ($pfno === '') {
            throw new RuntimeException('User PFNO is not configured.');
        }

        $hrpdOffice = DB::table('hrpd.vw_employee_details as employee')
            ->join('hrpd.office as office', 'office.office_id', '=', 'employee.office_id')
            ->leftJoin('hrpd.region as region', 'region.region_id', '=', 'office.region_id')
            ->where('employee.pfno', $pfno)
            ->select([
                'office.office_id',
                'office.region_id',
                'office.office_code',
                'employee.office_name',
                'region.region_name',
            ])
            ->first();

        if ($hrpdOffice === null) {
            throw new RuntimeException("No HRPD office assignment found for PFNO [{$pfno}].");
        }

        return [
            'user_id' => (int) $user->id,
            'pfno' => $pfno,
            'user_name' => (string) $user->name,
            'office_id' => (string) $hrpdOffice->office_id,
            'region_id' => (string) $hrpdOffice->region_id,
            'office_code' => $hrpdOffice->office_code ?? null,
            'office_name' => $hrpdOffice->office_name ?? null,
            'region_name' => $hrpdOffice->region_name ?? null,
        ];
    }

    protected function fillOfficeRegionFromHrp(array $data): array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();

        $data['office_id'] = $location['office_id'];
        $data['region_id'] = $location['region_id'];

        return $data;
    }

    protected function fillOfficeRegionFromHrpIfMissing(array $data): array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();

        if (empty($data['office_id'])) {
            $data['office_id'] = $location['office_id'];
        }

        if (empty($data['region_id'])) {
            $data['region_id'] = $location['region_id'];
        }

        return $data;
    }

    protected function scopeFiltersByHrpOffice(array $filters): array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();

        $filters['office_id'] = $location['office_id'];
        $filters['region_id'] = $location['region_id'];

        return $filters;
    }

    /**
     * Resolve office_id => office_name from HRPD for a set of office IDs.
     *
     * @param  array<int, string|int|null>  $officeIds
     * @return array<string, string>
     */
    protected function resolveHrpOfficeNamesByIds(array $officeIds): array
    {
        $ids = collect($officeIds)
            ->filter(fn ($id) => $id !== null && trim((string) $id) !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return DB::table('hrpd.office')
            ->whereIn('office_id', $ids)
            ->select(['office_id', 'office_name'])
            ->get()
            ->mapWithKeys(fn ($row) => [
                (string) $row->office_id => (string) ($row->office_name ?? ''),
            ])
            ->filter(fn (string $name) => $name !== '')
            ->all();
    }

    /**
     * Attach office_name (from HRPD) onto each item that has office_id.
     *
     * @param  iterable<mixed>  $items
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    protected function withHrpOfficeNames(iterable $items): \Illuminate\Support\Collection
    {
        $collection = collect($items);
        $namesById = $this->resolveHrpOfficeNamesByIds(
            $collection->pluck('office_id')->all()
        );

        return $collection->map(function ($item) use ($namesById) {
            $officeId = is_object($item)
                ? (string) ($item->office_id ?? '')
                : (string) ($item['office_id'] ?? '');
            $officeName = $namesById[$officeId] ?? null;

            if (is_object($item)) {
                $item->setAttribute('office_name', $officeName);
                return $item;
            }

            $item['office_name'] = $officeName;
            return $item;
        })->values();
    }
}
