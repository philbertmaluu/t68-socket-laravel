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
            ->where('employee.pfno', $pfno)
            ->select([
                'office.office_id',
                'office.region_id',
                'office.office_code',
                'employee.office_name',
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
}
