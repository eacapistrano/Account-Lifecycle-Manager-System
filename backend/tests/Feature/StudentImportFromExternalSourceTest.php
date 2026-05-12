<?php

namespace Tests\Feature;

use App\Services\StudentImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportFromExternalSourceTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceConnection = 'source_students';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.'.$this->sourceConnection => config('database.connections.'.config('database.default')),
        ]);
        DB::purge($this->sourceConnection);
    }

    protected function tearDown(): void
    {
        if (Schema::connection($this->sourceConnection)->hasTable('lgrrs')) {
            Schema::connection($this->sourceConnection)->drop('lgrrs');
        }

        parent::tearDown();
    }

    public function test_import_builds_composite_full_name_and_merges_email_map(): void
    {
        config([
            'student_import.enabled' => true,
            'student_import.connection' => $this->sourceConnection,
            'student_import.table' => 'lgrrs',
            'student_import.order_by_column' => 'SZSTUID',
            'student_import.composite_full_name_columns' => ['SZFNAME', 'SZMNAME', 'SZLNAME'],
            'student_import.email_csv_path' => '',
            'student_import.column_map' => [
                'external_account_id' => 'SZSTUID',
                'graduation_date' => 'DTGRADDATE',
                'graduation_status' => 'SZSTATUS',
            ],
        ]);

        Schema::connection($this->sourceConnection)->create('lgrrs', function (Blueprint $table): void {
            $table->string('SZSTUID');
            $table->string('SZFNAME')->nullable();
            $table->string('SZMNAME')->nullable();
            $table->string('SZLNAME')->nullable();
            $table->string('SZSTATUS')->nullable();
            $table->string('DTGRADDATE')->nullable();
        });

        DB::connection($this->sourceConnection)->table('lgrrs')->insert([
            'SZSTUID' => '0215-25055',
            'SZFNAME' => 'Pat',
            'SZMNAME' => 'Q',
            'SZLNAME' => 'Roe',
            'SZSTATUS' => 'New',
            'DTGRADDATE' => '2024-05-15',
        ]);

        $stats = app(StudentImportService::class)->import([
            '0215-25055' => 'pat@school.edu',
        ]);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['skipped_no_email']);

        $this->assertDatabaseHas('students', [
            'external_account_id' => '0215-25055',
            'primary_email' => 'pat@school.edu',
            'full_name' => 'Pat Q Roe',
            'graduation_status' => 'New',
            'graduation_date' => '2024-05-15',
        ]);
    }

    public function test_import_skips_rows_missing_email_in_merge_map(): void
    {
        config([
            'student_import.enabled' => true,
            'student_import.connection' => $this->sourceConnection,
            'student_import.table' => 'lgrrs',
            'student_import.order_by_column' => 'SZSTUID',
            'student_import.composite_full_name_columns' => [],
            'student_import.email_csv_path' => '',
            'student_import.column_map' => [
                'external_account_id' => 'SZSTUID',
                'graduation_status' => 'SZSTATUS',
            ],
        ]);

        Schema::connection($this->sourceConnection)->create('lgrrs', function (Blueprint $table): void {
            $table->string('SZSTUID');
            $table->string('SZSTATUS')->nullable();
        });

        DB::connection($this->sourceConnection)->table('lgrrs')->insert([
            ['SZSTUID' => 'has-email', 'SZSTATUS' => 'Active'],
            ['SZSTUID' => 'no-email', 'SZSTATUS' => 'Active'],
        ]);

        $stats = app(StudentImportService::class)->import([
            'has-email' => 'has@school.edu',
        ]);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['skipped_no_email']);

        $this->assertDatabaseHas('students', [
            'external_account_id' => 'has-email',
            'primary_email' => 'has@school.edu',
        ]);

        $this->assertDatabaseMissing('students', [
            'external_account_id' => 'no-email',
        ]);
    }

    public function test_import_generates_primary_email_when_config_enabled_and_no_csv(): void
    {
        config([
            'student_import.enabled' => true,
            'student_import.connection' => $this->sourceConnection,
            'student_import.table' => 'lgrrs',
            'student_import.order_by_column' => 'SZSTUID',
            'student_import.composite_full_name_columns' => ['SZFNAME', 'SZMNAME', 'SZLNAME'],
            'student_import.email_csv_path' => '',
            'student_import.generate_primary_email' => true,
            'student_import.email_formula_last_name_column' => 'SZLNAME',
            'student_import.email_formula_id_suffix_length' => 5,
            'student_import.email_formula_mnl_year_prefix' => '2025',
            'student_import.email_domain_mnl' => '@mnl.ceu.edu.ph',
            'student_import.email_domain_default' => '@ceu.edu.ph',
            'student_import.column_map' => [
                'external_account_id' => 'SZSTUID',
                'graduation_status' => 'SZSTATUS',
            ],
        ]);

        Schema::connection($this->sourceConnection)->create('lgrrs', function (Blueprint $table): void {
            $table->string('SZSTUID');
            $table->string('SZFNAME')->nullable();
            $table->string('SZMNAME')->nullable();
            $table->string('SZLNAME')->nullable();
            $table->string('SZSTATUS')->nullable();
        });

        DB::connection($this->sourceConnection)->table('lgrrs')->insert([
            'SZSTUID' => '2012-01011',
            'SZFNAME' => 'Pat',
            'SZMNAME' => '',
            'SZLNAME' => 'CARRANCEJA',
            'SZSTATUS' => 'Active',
        ]);

        $stats = app(StudentImportService::class)->import(null);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(0, $stats['skipped_no_email']);

        $this->assertDatabaseHas('students', [
            'external_account_id' => '2012-01011',
            'primary_email' => 'carranceja1201011@ceu.edu.ph',
            'full_name' => 'Pat CARRANCEJA',
        ]);
    }
}
