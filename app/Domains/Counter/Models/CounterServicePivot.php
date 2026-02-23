<?php

namespace App\Domains\Counter\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CounterServicePivot extends Pivot
{
    use HasTenant;

    protected $table = 'counter_services';

    public $incrementing = true;

    protected $fillable = [
        'counter_id',
        'service_id',
        'office_id',
        'tenant_id',
    ];
}
