<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        $permissionRows = [
            ['slug' => 'student_import.run', 'name' => 'Run student import', 'description' => 'Trigger import or sync from external registry', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'student.bulk_suspend', 'name' => 'Bulk suspend students', 'description' => 'Queue suspend for selected accounts', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'student.bulk_delete', 'name' => 'Bulk delete students', 'description' => 'Queue permanent delete for selected accounts', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'suspended.priority', 'name' => 'Update suspended priority', 'description' => 'Change triage priority on suspended accounts', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'policy.write', 'name' => 'Manage policies', 'description' => 'Create, update, or delete automation policies', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'audit.export', 'name' => 'Export audit log', 'description' => 'Download audit CSV or PDF', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'roles.view', 'name' => 'View roles', 'description' => 'List roles and permissions', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'roles.manage', 'name' => 'Manage roles', 'description' => 'Create, update, or delete custom roles', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'users.manage', 'name' => 'Manage users', 'description' => 'List users and assign roles', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->insert($permissionRows);

        $permIdsBySlug = DB::table('permissions')->pluck('id', 'slug');

        DB::table('roles')->insert([
            ['id' => 1, 'slug' => 'admin', 'name' => 'Administrator', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'slug' => 'viewer', 'name' => 'Viewer', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $adminRoleId = 1;
        $viewerRoleId = 2;

        foreach ($permIdsBySlug as $permissionId) {
            DB::table('permission_role')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles');
        });

        $adminId = DB::table('roles')->where('slug', 'admin')->value('id');
        $viewerId = DB::table('roles')->where('slug', 'viewer')->value('id');

        DB::table('users')->where('role', 'admin')->update(['role_id' => $adminId]);
        DB::table('users')->where('role', '!=', 'admin')->update(['role_id' => $viewerId]);
        DB::table('users')->whereNull('role_id')->update(['role_id' => $viewerId]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('admin')->after('password');
        });

        $adminId = DB::table('roles')->where('slug', 'admin')->value('id');
        $viewerId = DB::table('roles')->where('slug', 'viewer')->value('id');

        if ($adminId !== null) {
            DB::table('users')->where('role_id', $adminId)->update(['role' => 'admin']);
        }
        if ($viewerId !== null) {
            DB::table('users')->where('role_id', $viewerId)->update(['role' => 'viewer']);
        }
        DB::table('users')->whereNull('role_id')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
