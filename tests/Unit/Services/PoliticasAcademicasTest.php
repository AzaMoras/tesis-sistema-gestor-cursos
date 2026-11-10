<?php

namespace Tests\Unit\Services;

use App\Services\Soporte\PoliticaEvaluacion;
use App\Services\Soporte\PoliticaVigencia;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PoliticasAcademicasTest extends TestCase
{
    public function test_ta_08_dates_use_utc_and_only_limited_access_has_defaults(): void
    {
        $start = CarbonImmutable::parse('2026-11-10 10:00:00', 'America/Mexico_City');
        self::assertSame('2026-12-10T16:00:00+00:00', PoliticaVigencia::gratuitaLimitada()->vencimiento($start)->toIso8601String());
        self::assertSame('2026-11-10T10:00:00-06:00', $start->toIso8601String());
        self::assertNull((new PoliticaVigencia('sin_vencimiento'))->vencimiento($start));
        self::assertNull(PoliticaVigencia::gratuitaLimitada()->diasProrroga);
        self::assertSame(14, PoliticaVigencia::gratuitaLimitada(diasProrroga: 14)->diasProrroga);
    }

    #[DataProvider('invalidVigencias')]
    public function test_invalid_access_policy_is_rejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PoliticaVigencia(...$arguments);
    }

    public static function invalidVigencias(): array
    {
        return [
            [['desconocida']], [['con_vencimiento']], [['con_vencimiento', 0]],
            [['con_vencimiento', 65536]], [['sin_vencimiento', 30]],
            [['sin_vencimiento', null, 14]], [['con_vencimiento', 30, 0]],
        ];
    }

    public function test_ta_14_formative_retries_are_unlimited_and_ta_15_fourth_final_is_denied(): void
    {
        $policy = new PoliticaEvaluacion;
        self::assertTrue($policy->puedeAbrirIntento('formativa', 100000));
        self::assertTrue($policy->puedeAbrirIntento('final', 2));
        self::assertFalse($policy->puedeAbrirIntento('final', 3));
        self::assertFalse($policy->puedeAbrirIntento('final', 0, true));
        self::assertSame('aprobado', $policy->estadoFinal(3, true));
        self::assertSame('en_curso', $policy->estadoFinal(2, false));
        self::assertSame('reforzamiento_pendiente', $policy->estadoFinal(3, false));
    }

    public function test_institution_overrides_course_configuration_and_course_overrides_defaults(): void
    {
        $policy = PoliticaEvaluacion::resolver(['intentosPorCiclo' => 5, 'minutos' => 60], ['intentosPorCiclo' => 2]);
        self::assertSame(2, $policy->intentosPorCiclo);
        self::assertSame(60, $policy->minutos);
        self::assertSame(30, PoliticaEvaluacion::resolver()->minutos);
    }

    public function test_ta_16_submission_at_or_after_deadline_is_rejected_and_start_is_immutable(): void
    {
        $policy = new PoliticaEvaluacion;
        $start = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        self::assertTrue($policy->dentroDelPlazo($start, $start));
        self::assertTrue($policy->dentroDelPlazo($start, $start->addMinutes(30)->subSecond()));
        self::assertFalse($policy->dentroDelPlazo($start, $start->addMinutes(30)));
        self::assertFalse($policy->dentroDelPlazo($start, $start->addMinutes(31)));
        self::assertFalse($policy->dentroDelPlazo($start, $start->subSecond()));
        self::assertSame('12:00:00', $start->format('H:i:s'));
    }

    public function test_ta_17_ta_18_ta_19_recovery_requires_all_activities_recompleted_after_closure(): void
    {
        $policy = new PoliticaEvaluacion;
        $close = CarbonImmutable::parse('2026-11-10 12:00:00', 'UTC');
        $now = $close->addDay();
        foreach ([[], [10 => $close->subDay()], [10 => $close], [10 => $now->addSecond()], [10 => $now]] as $incomplete) {
            self::assertFalse($policy->puedeAbrirNuevoCiclo(3, false, $close, [10, 20], $incomplete, $now));
        }
        $completed = [10 => $close->addHour(), 20 => $now];
        self::assertTrue($policy->puedeAbrirNuevoCiclo(3, false, $close, [10, 20], $completed, $now));
        self::assertFalse($policy->puedeAbrirNuevoCiclo(2, false, $close, [10, 20], $completed, $now));
        self::assertFalse($policy->puedeAbrirNuevoCiclo(3, true, $close, [10, 20], $completed, $now));
        self::assertFalse($policy->puedeAbrirNuevoCiclo(3, false, $close, [], [], $now));
        self::assertSame('aprobado', $policy->estadoFinal(1, true));
    }

    #[DataProvider('invalidEvaluationPolicies')]
    public function test_invalid_exam_configuration_is_rejected(array $course, array $institution): void
    {
        $this->expectException(InvalidArgumentException::class);
        PoliticaEvaluacion::resolver($course, $institution);
    }

    public static function invalidEvaluationPolicies(): array
    {
        return [[['minutos' => 0], []], [['intentosPorCiclo' => -1], []], [['minutos' => '30'], []], [[], ['role' => 'admin']]];
    }
}
