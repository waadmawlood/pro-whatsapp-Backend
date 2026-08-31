<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(null);

        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $registrar->forgetCachedPermissions();

        Company::query()->each(fn (Company $company) => $this->seedForCompany($company));
    }

    public function seedForCompany(Company $company): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // الصلاحيات عامة لكل الشركات — تُنشأ خارج سياق الفريق
        $registrar->setPermissionsTeamId(null);
        foreach (Permissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $registrar->forgetCachedPermissions();

        $registrar->setPermissionsTeamId($company->id);

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions(Permissions::all());

        $employee = Role::findOrCreate('employee', 'web');
        $employee->syncPermissions(Permissions::employeeDefaults());

        $registrar->forgetCachedPermissions();
    }
}
