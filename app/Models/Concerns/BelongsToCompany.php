<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = static::currentCompanyId();

            if ($companyId) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->company_id && ($companyId = static::currentCompanyId())) {
                $model->company_id = $companyId;
            }
        });
    }

    public static function currentCompanyId(): ?int
    {
        if (! app()->bound('current_company_id')) {
            return null;
        }

        $companyId = app('current_company_id');

        return $companyId ? (int) $companyId : null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
