<?php

namespace Tests\Unit;

use App\Services\CeuEmailListFormulaGenerator;
use InvalidArgumentException;
use Tests\TestCase;

class CeuEmailListFormulaGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'student_import.email_formula_mnl_year_prefix' => '2025',
            'student_import.email_domain_mnl' => '@mnl.ceu.edu.ph',
            'student_import.email_domain_default' => '@ceu.edu.ph',
            'student_import.email_formula_id_suffix_length' => 5,
            'student_import.email_formula_id_suffix_lengths' => [],
        ]);
    }

    public function test_matches_email_list_creation_formula_workbook_samples(): void
    {
        $g = new CeuEmailListFormulaGenerator;

        $this->assertSame('carranceja1201011@ceu.edu.ph', $g->generate('2012-01011', 'CARRANCEJA'));
        $this->assertSame('mendoza0504302@ceu.edu.ph', $g->generate('2005-04302', 'MENDOZA'));
        $this->assertSame('dacanay1800245@ceu.edu.ph', $g->generate('2018-00245', 'DACANAY'));
        $this->assertSame('jumma1600449@ceu.edu.ph', $g->generate('2016-00449', 'JUMMA'));
        $this->assertSame('alviz1615294@ceu.edu.ph', $g->generate('2016-15294', 'ALVIZ'));
    }

    public function test_removes_spaces_from_last_name_like_substitute(): void
    {
        $g = new CeuEmailListFormulaGenerator;

        $this->assertSame('delossantos9901234@ceu.edu.ph', $g->generate('1999-01234', 'DE LOS SANTOS'));
    }

    public function test_uses_mnl_domain_when_left_four_equals_configured_year(): void
    {
        $g = new CeuEmailListFormulaGenerator;

        $this->assertSame('student2500001@mnl.ceu.edu.ph', $g->generate('2025-00001', 'STUDENT'));
    }

    public function test_throws_when_student_id_too_short_for_year_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CeuEmailListFormulaGenerator)->generate('201', 'X');
    }
}
