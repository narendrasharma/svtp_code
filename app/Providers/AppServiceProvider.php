<?php

namespace App\Providers;

use App\AI\Contracts\KnowledgeRetrieverInterface;
use App\AI\Contracts\VectorStoreInterface;
use App\AI\Providers\AzureProvider;
use App\AI\Providers\ClaudeProvider;
use App\AI\Providers\GeminiEmbeddingProvider;
use App\AI\Providers\GeminiProvider;
use App\AI\Providers\OpenAIEmbeddingProvider;
use App\AI\Providers\OpenAIProvider;
use App\AI\Support\AIProviderRegistry;
use App\AI\Support\AIToolRegistry;
use App\AI\Support\DatabaseKnowledgeRetriever;
use App\AI\Support\DatabaseVectorStore;
use App\AI\Support\EmbeddingProviderRegistry;
use App\AI\Tools\MarketplaceContentActionTool;
use App\AI\Tools\MarketplaceReadTool;
use App\AI\Tools\OperationalContentActionTool;
use App\AI\Tools\SearchKnowledgeTool;
use App\Contracts\Discovery\SearchProvider;
use App\Contracts\ExchangeRateProvider;
use App\Events;
use App\Listeners;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\TourAddon;
use App\Models\TourBlackoutDate;
use App\Models\TourPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorDocument;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPayoutAccount;
use App\Models\VendorPlan;
use App\Models\VendorProfile;
use App\Models\VendorWithdrawalRequest;
use App\Policies\BookingPolicy;
use App\Policies\CouponPolicy;
use App\Policies\TourAddonPolicy;
use App\Policies\TourBlackoutDatePolicy;
use App\Policies\TourPackagePolicy;
use App\Policies\VendorApplicationPolicy;
use App\Policies\VendorDocumentPolicy;
use App\Policies\VendorLedgerEntryPolicy;
use App\Policies\VendorPayoutAccountPolicy;
use App\Policies\VendorPlanPolicy;
use App\Policies\VendorProfilePolicy;
use App\Policies\VendorWithdrawalRequestPolicy;
use App\Services\Discovery\DatabaseLocationSearchProvider;
use App\Services\HotelPropertyService;
use App\Services\ImpersonationService;
use App\Services\ManualExchangeRateProvider;
use App\Services\Payouts\ManualPayoutProcessor;
use App\Services\Payouts\PayoutProcessor;
use App\Support\AuditRegistry;
use App\Support\DatabaseSafety;
use App\Support\ModuleManager;
use App\Support\StaffPermissions;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AIProviderRegistry::class, function (Application $app): AIProviderRegistry {
            $registry = new AIProviderRegistry;
            $registry->register($app->make(OpenAIProvider::class));
            $registry->register($app->make(GeminiProvider::class));
            $registry->register($app->make(ClaudeProvider::class));
            $registry->register($app->make(AzureProvider::class));

            return $registry;
        });

        $this->app->singleton(EmbeddingProviderRegistry::class, function (Application $app): EmbeddingProviderRegistry {
            $registry = new EmbeddingProviderRegistry;
            $registry->register($app->make(OpenAIEmbeddingProvider::class));
            $registry->register($app->make(GeminiEmbeddingProvider::class));

            return $registry;
        });

        $this->app->bind(VectorStoreInterface::class, DatabaseVectorStore::class);
        $this->app->bind(KnowledgeRetrieverInterface::class, DatabaseKnowledgeRetriever::class);

        $this->app->singleton(AIToolRegistry::class, function (Application $app): AIToolRegistry {
            $registry = new AIToolRegistry;

            foreach (MarketplaceReadTool::NAMES as $name) {
                $registry->register(new MarketplaceReadTool($name));
            }

            $registry->register($app->make(SearchKnowledgeTool::class));

            foreach (MarketplaceContentActionTool::NAMES as $name) {
                $registry->registerAction(new MarketplaceContentActionTool($name));
            }

            foreach (OperationalContentActionTool::NAMES as $name) {
                $registry->registerAction(new OperationalContentActionTool($name, $app->make(HotelPropertyService::class)));
            }

            return $registry;
        });

        // Phase 13C discovery seam: database driver today; future
        // Scout/Meilisearch/Algolia adapters bind here.
        $this->app->bind(
            SearchProvider::class,
            DatabaseLocationSearchProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Never let `migrate:fresh`-style commands wipe an active database
        // by accident (see App\Support\DatabaseSafety). Plain `migrate`
        // is unaffected, and isolated test databases always pass through.
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $connection = null;

            try {
                if ($event->input->hasOption('database')) {
                    $option = $event->input->getOption('database');
                    $connection = is_string($option) && $option !== '' ? $option : null;
                }
            } catch (\Throwable) {
                $connection = null;
            }

            DatabaseSafety::guardDestructiveCommand($event->command, $connection);
        });

        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(TourPackage::class, TourPackagePolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(TourAddon::class, TourAddonPolicy::class);
        Gate::policy(TourBlackoutDate::class, TourBlackoutDatePolicy::class);
        Gate::policy(VendorPlan::class, VendorPlanPolicy::class);
        Gate::policy(VendorApplication::class, VendorApplicationPolicy::class);
        Gate::policy(VendorDocument::class, VendorDocumentPolicy::class);
        Gate::policy(VendorProfile::class, VendorProfilePolicy::class);
        Gate::policy(VendorLedgerEntry::class, VendorLedgerEntryPolicy::class);
        Gate::policy(VendorPayoutAccount::class, VendorPayoutAccountPolicy::class);
        Gate::policy(VendorWithdrawalRequest::class, VendorWithdrawalRequestPolicy::class);

        // Phase 8 payout seam: manual settlement today; future providers
        // bind here without touching controllers or ledger accounting.
        $this->app->bind(PayoutProcessor::class, ManualPayoutProcessor::class);

        // Phase 13B FX provider seam: manual rates always work with zero
        // external APIs. Remote adapters implement ExchangeRateProvider
        // and are selected via the currency.provider setting.
        $this->app->bind(ExchangeRateProvider::class, ManualExchangeRateProvider::class);

        Gate::define('impersonate', function ($user, $target) {
            return app(ImpersonationService::class)->canImpersonate($user, $target);
        });

        // Phase 11.5A platform core: Module Manager singleton + staff RBAC
        // gates. Super Admin passes every staff gate (Gate::before); each
        // catalogue permission resolves through User::hasStaffPermission,
        // which keeps legacy full admins working while strictly limiting
        // role-assigned staff.
        $this->app->singleton(ModuleManager::class);

        Gate::before(function ($user, string $ability): ?bool {
            if (! $user instanceof User || ! $user->isAdmin()) {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        foreach (StaffPermissions::all() as $permission) {
            Gate::define($permission, fn ($user) => $user instanceof User && $user->hasStaffPermission($permission));
        }

        // Phase 9 domain notifications: synchronous delivery (no queue
        // worker is guaranteed, so queueing must never silently drop
        // transactional mail). Single-handle listeners are auto-discovered
        // by the framework (event discovery is on by default); only the
        // multi-event subscribers below need explicit registration.
        Event::listen(Events\TourSubmitted::class, [Listeners\NotifyTourActivity::class, 'onSubmitted']);
        Event::listen(Events\TourModerated::class, [Listeners\NotifyTourActivity::class, 'onModerated']);
        Event::listen(Events\VendorApplicationSubmitted::class, [Listeners\NotifyVendorApplication::class, 'onSubmitted']);
        Event::listen(Events\VendorApplicationDecided::class, [Listeners\NotifyVendorApplication::class, 'onDecided']);
        Event::listen(Events\WithdrawalRequested::class, [Listeners\NotifyWithdrawalActivity::class, 'onRequested']);
        Event::listen(Events\WithdrawalDecided::class, [Listeners\NotifyWithdrawalActivity::class, 'onDecided']);

        // Phase 11.5D audit trail: authentication events (login, logout,
        // failed attempts). Credential material is never stored.
        Event::listen(Login::class, [Listeners\AuditAuthentication::class, 'onLogin']);
        Event::listen(Logout::class, [Listeners\AuditAuthentication::class, 'onLogout']);
        Event::listen(Failed::class, [Listeners\AuditAuthentication::class, 'onFailed']);

        // Phase 11.5D audit registry: append-only model trail. Never
        // throws into domain writes; scheduler markers are ignored.
        AuditRegistry::register();
    }
}
