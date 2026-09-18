<?php

use App\Services\MarketplaceCommissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6: historical vendor assignment + immutable commission snapshot.
     *
     * vendor_profile_id records the responsible vendor AT BOOKING TIME, so
     * later tour ownership changes never rewrite old bookings. Financial
     * columns snapshot the split computed from the then-current default
     * platform commission — historical amounts/statuses are untouched.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('vendor_profile_id')->nullable()->after('package_id')->constrained('vendor_profiles')->nullOnDelete();
            $table->decimal('gross_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('platform_commission_percentage', 5, 2)->nullable()->after('gross_amount');
            $table->decimal('platform_commission_amount', 10, 2)->nullable()->after('platform_commission_percentage');
            $table->decimal('vendor_earning_amount', 10, 2)->nullable()->after('platform_commission_amount');
        });

        $this->backfillSnapshots();
    }

    protected function backfillSnapshots(): void
    {
        $percentage = Schema::hasTable('settings')
            ? MarketplaceCommissionService::normalizePercentage(
                DB::table('settings')->where('key', MarketplaceCommissionService::SETTING_KEY)->value('value')
            )
            : MarketplaceCommissionService::FALLBACK_PERCENTAGE;

        DB::table('bookings')->orderBy('id')->chunkById(200, function ($bookings) use ($percentage): void {
            foreach ($bookings as $booking) {
                $vendorProfileId = DB::table('tour_packages')
                    ->where('id', $booking->package_id)
                    ->value('vendor_profile_id');

                if ($vendorProfileId !== null) {
                    $split = MarketplaceCommissionService::splitAmounts((string) $booking->total_amount, $percentage);

                    DB::table('bookings')->where('id', $booking->id)->update([
                        'vendor_profile_id' => $vendorProfileId,
                        'gross_amount' => $booking->total_amount,
                        'platform_commission_percentage' => $percentage,
                        'platform_commission_amount' => $split['commission'],
                        'vendor_earning_amount' => $split['earning'],
                    ]);
                } else {
                    // Admin-owned tour: platform retains the full booking value.
                    DB::table('bookings')->where('id', $booking->id)->update([
                        'vendor_profile_id' => null,
                        'gross_amount' => $booking->total_amount,
                        'platform_commission_percentage' => null,
                        'platform_commission_amount' => 0,
                        'vendor_earning_amount' => 0,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['vendor_profile_id']);
            $table->dropColumn([
                'vendor_profile_id',
                'gross_amount',
                'platform_commission_percentage',
                'platform_commission_amount',
                'vendor_earning_amount',
            ]);
        });
    }
};
