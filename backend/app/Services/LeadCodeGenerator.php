<?php

namespace App\Services;

use App\Models\LeadSequence;
use Illuminate\Support\Facades\DB;

class LeadCodeGenerator
{
    /**
     * Must be called inside a transaction: the counter row is held under
     * `lockForUpdate` so two simultaneous submissions cannot take the same
     * number. Deliberately not `count() + 1`.
     */
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $sequence = LeadSequence::query()->where('year', $year)->lockForUpdate()->first();

        if ($sequence === null) {
            DB::table('lead_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = LeadSequence::query()->where('year', $year)->lockForUpdate()->firstOrFail();
        }

        $number = $sequence->last_number + 1;
        $sequence->forceFill(['last_number' => $number])->save();

        return sprintf(
            '%s-%d-%s',
            config('leads.code_prefix'),
            $year,
            str_pad((string) $number, (int) config('leads.code_padding'), '0', STR_PAD_LEFT)
        );
    }
}
