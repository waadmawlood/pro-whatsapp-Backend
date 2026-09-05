<?php

namespace App\Support;

use Spatie\Permission\PermissionRegistrar;

final class CompanyContext
{
    public static function set(int $companyId): void
    {
        app()->instance('current_company_id', $companyId);
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
    }

    public static function clear(): void
    {
        app()->forgetInstance('current_company_id');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
