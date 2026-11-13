<?php

namespace Tests\Unit\NotesEvaluations\Lycee;

use Modules\NotesEvaluations\Services\Lycee\LyceeGradeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Règles de calcul pures des notes lycée (sans base de données).
 */
class LyceeGradeCalculatorTest extends TestCase
{
    private LyceeGradeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LyceeGradeCalculator;
    }

    #[Test]
    public function it_normalizes_scores_on_twenty_and_ignores_absences(): void
    {
        $this->assertSame(15.0, $this->calculator->normalizeScore(30, 40));
        $this->assertSame(10.0, $this->calculator->normalizeScore(5, 10));
        $this->assertNull($this->calculator->normalizeScore(12, 20, true));
        $this->assertNull($this->calculator->normalizeScore(null, 20));
        $this->assertNull($this->calculator->normalizeScore(12, 0));
    }

    #[Test]
    public function subject_average_is_weighted_and_excludes_absent_or_missing_scores(): void
    {
        $average = $this->calculator->subjectAverage([
            ['score' => 12, 'max_score' => 20, 'weight' => 1, 'is_absent' => false],
            ['score' => 30, 'max_score' => 40, 'weight' => 2, 'is_absent' => false],
            ['score' => null, 'max_score' => 20, 'weight' => 5, 'is_absent' => true],
            ['score' => null, 'max_score' => 20, 'weight' => 3, 'is_absent' => false],
        ]);

        // (12 × 1 + 15 × 2) / 3 = 14
        $this->assertSame(14.0, $average);
    }

    #[Test]
    public function subject_average_is_rounded_to_two_decimals_and_null_without_marks(): void
    {
        // (16 + 18 × 2) / 3 = 17.333…
        $this->assertSame(17.33, $this->calculator->subjectAverage([
            ['score' => 16, 'max_score' => 20, 'weight' => 1],
            ['score' => 36, 'max_score' => 40, 'weight' => 2],
        ]));

        $this->assertNull($this->calculator->subjectAverage([]));
        $this->assertNull($this->calculator->subjectAverage([
            ['score' => null, 'max_score' => 20, 'weight' => 1, 'is_absent' => true],
        ]));
    }

    #[Test]
    public function general_average_uses_coefficients_and_skips_subjects_without_average(): void
    {
        // (14.33 × 4 + 10 × 3) / 7 = 12.474…
        $this->assertSame(12.47, $this->calculator->generalAverage([
            ['average' => 14.33, 'coefficient' => 4],
            ['average' => 10.0, 'coefficient' => 3],
            ['average' => null, 'coefficient' => 2],
        ]));

        $this->assertNull($this->calculator->generalAverage([
            ['average' => null, 'coefficient' => 2],
        ]));
    }

    #[Test]
    public function annual_average_is_the_mean_of_available_semester_averages(): void
    {
        $this->assertSame(13.99, $this->calculator->annualAverage([12.47, 15.51]));
        $this->assertSame(12.0, $this->calculator->annualAverage([12.0, null]));
        $this->assertNull($this->calculator->annualAverage([null, null]));
    }

    #[Test]
    public function ranks_share_the_same_position_for_ties(): void
    {
        $ranks = $this->calculator->rank([
            10 => 15.0,
            11 => 12.5,
            12 => 12.5,
            13 => 9.0,
            14 => null,
        ]);

        $this->assertSame([10 => 1, 11 => 2, 12 => 2, 13 => 4, 14 => null], $ranks);
    }

    #[Test]
    public function stats_compute_average_extremes_and_pass_rate(): void
    {
        $stats = $this->calculator->stats([17.62, 12.47, 12.0, 9.71, null]);

        $this->assertSame(12.95, $stats['class_average']);
        $this->assertSame(17.62, $stats['highest']);
        $this->assertSame(9.71, $stats['lowest']);
        $this->assertSame(3, $stats['passed_count']);
        $this->assertSame(4, $stats['graded_count']);
        $this->assertSame(75.0, $stats['pass_rate']);

        $empty = $this->calculator->stats([null]);
        $this->assertNull($empty['class_average']);
        $this->assertNull($empty['pass_rate']);
        $this->assertSame(0, $empty['passed_count']);
    }

    /**
     * @return array<string, array{0: float|null, 1: string|null, 2: string|null}>
     */
    public static function mentionProvider(): array
    {
        return [
            'aucune moyenne' => [null, null, null],
            '16 pile' => [16.0, 'Très bien', 'Félicitations'],
            '15.99' => [15.99, 'Bien', "Tableau d'honneur + Encouragements"],
            '14 pile' => [14.0, 'Bien', "Tableau d'honneur + Encouragements"],
            '12 pile' => [12.0, 'Assez bien', "Tableau d'honneur"],
            '11.99' => [11.99, 'Passable', null],
            '10 pile' => [10.0, 'Passable', null],
            '9.99' => [9.99, 'Insuffisant', null],
            '8 pile' => [8.0, 'Insuffisant', null],
            '7.99' => [7.99, 'Insuffisant', 'Avertissement'],
        ];
    }

    #[Test]
    #[DataProvider('mentionProvider')]
    public function mentions_and_distinctions_follow_thresholds(?float $average, ?string $mention, ?string $distinction): void
    {
        $this->assertSame($mention, $this->calculator->mention($average));
        $this->assertSame($distinction, $this->calculator->distinction($average));
    }
}
