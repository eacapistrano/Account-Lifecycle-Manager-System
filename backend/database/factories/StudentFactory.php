<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    private const DEMO_DEPARTMENTS = ['Science', 'Arts', 'Sports', 'Humanities'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $externalAccountId = 'demo_f_'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'external_account_id' => $externalAccountId,
            'primary_email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'department' => fake()->randomElement(self::DEMO_DEPARTMENTS),
            'school_year' => fake()->randomElement(['2024', '2025', '2026', '2027']),
            'graduation_date' => fake()->boolean(35)
                ? fake()->dateTimeBetween('-2 years', '+2 years')->format('Y-m-d')
                : null,
            'graduation_status' => fake()->optional(0.6)->randomElement(['enrolled', 'graduated', 'withdrawn', 'leave of absence']),
            'degree_program' => fake()->optional(0.55)->randomElement([
                'BSc Computer Science',
                'BA Visual Arts',
                'BEng Mechanical Engineering',
                'BEd Primary',
                'Certificate in Sports Coaching',
            ]),
            'suspended' => fake()->boolean(22),
            'deletion_scheduled_at' => fake()->optional(0.12)->dateTimeBetween('-30 days', '+60 days'),
            'graduation_warning_sent_at' => fake()->optional(0.35)->dateTimeBetween('-30 days', 'now'),
            'graduation_deletion_warning_sent_at' => null,
            'priority_flag' => fake()->boolean(8),
            'compliance_notes' => fake()->optional(0.15)->sentence(8),
            'raw_json' => [
                'kind' => 'admin#user',
                'id' => $externalAccountId,
            ],
            'last_imported_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'suspended' => true,
        ]);
    }

    public function scheduledDeletion(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deletion_scheduled_at' => fake()->dateTimeBetween('-14 days', '+30 days'),
            'suspended' => true,
        ]);
    }

    public function graduatedAlumni(): static
    {
        return $this->state(fn (array $attributes): array => [
            'graduation_date' => fake()->dateTimeBetween('-4 years', '-6 months')->format('Y-m-d'),
            'graduation_status' => 'graduated',
        ]);
    }

    public function enrolledOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'graduation_status' => 'enrolled',
            'graduation_date' => fake()->dateTimeBetween('+3 months', '+2 years')->format('Y-m-d'),
            'graduation_warning_sent_at' => null,
            'suspended' => false,
        ]);
    }
}
