<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): deterministic country backfill.
     *
     * Safety rules (no silent corruption):
     * - only the four StateCitySeeder states (proven India-specific by
     *   their slug set) are mapped to India — and only when an India
     *   country row already exists;
     * - cities/destinations inherit strictly through their existing
     *   state/city chain, never by name guessing;
     * - properties link only when their explicit ISO country_code matches
     *   a seeded country row (ISO codes are unambiguous, not inferred);
     * - every update is scoped to NULL targets, so re-running changes
     *   nothing and admin corrections are never overwritten.
     *
     * Plain chunked queries only (no JOIN updates) so the migration runs
     * identically on MySQL and SQLite.
     */
    public function up(): void
    {
        $indiaId = DB::table('countries')->where('iso2', 'IN')->value('id');

        if ($indiaId) {
            DB::table('states')
                ->whereNull('country_id')
                ->whereIn('slug', ['uttar-pradesh', 'uttarakhand', 'rajasthan', 'delhi'])
                ->update(['country_id' => $indiaId]);
        }

        DB::table('states')->whereNotNull('country_id')->select('id', 'country_id')
            ->orderBy('id')->chunk(200, function ($states): void {
                foreach ($states as $state) {
                    DB::table('cities')
                        ->whereNull('country_id')
                        ->where('state_id', $state->id)
                        ->update(['country_id' => $state->country_id]);
                }
            });

        DB::table('cities')->whereNotNull('country_id')->select('id', 'country_id', 'state_id')
            ->orderBy('id')->chunk(500, function ($cities): void {
                foreach ($cities as $city) {
                    DB::table('destinations')
                        ->whereNull('country_id')
                        ->where('city_id', $city->id)
                        ->update([
                            'country_id' => $city->country_id,
                            'state_id' => $city->state_id,
                        ]);
                }
            });

        foreach (DB::table('countries')->pluck('id', 'iso2') as $iso2 => $countryId) {
            DB::table('properties')
                ->whereNull('country_id')
                ->where('country_code', $iso2)
                ->update(['country_id' => $countryId]);
        }
    }

    public function down(): void
    {
        // Backfill is data remediation, not schema: forward-fix only.
        // Admin-assigned countries must never be wiped by a rollback.
    }
};
