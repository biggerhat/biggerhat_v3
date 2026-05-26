<?php

namespace App\Models\Campaign;

use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use Database\Factories\Campaign\BackAlleyDoctorResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for one of the Phase 5 Back-Alley Doctor outcomes (pg 33).
 * Range-based — rows cover BJ / 1-8 / 9 / 10 / 11 / 12-13 / RJ.
 */
class BackAlleyDoctorResult extends Model
{
    /** @use HasFactory<BackAlleyDoctorResultFactory> */
    use HasFactory;

    protected $table = 'back_alley_doctor_results';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_black_joker' => 'boolean',
            'is_red_joker' => 'boolean',
            'outcome_kind' => BackAlleyDoctorOutcomeEnum::class,
        ];
    }

    protected static function newFactory(): BackAlleyDoctorResultFactory
    {
        return BackAlleyDoctorResultFactory::new();
    }
}
