<?php

namespace App\Providers;

use App\Enums\Catalog;
use App\Enums\LibraryCatalog;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Blaze\Blaze;
use Livewire\Component;
use Throwable;

use function Livewire\on;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRouteBindings();
        $this->configureLivewire();

        Blaze::optimize()->in(resource_path('views/components'));
    }

    /**
     * Dispatch "validation-failed" to the component whose call fails validation, for resources/js/scroll-to-first-error.js.
     */
    protected function configureLivewire(): void
    {
        on('exception', function (mixed $target, Throwable $exception): void {
            if ($target instanceof Component && $exception instanceof ValidationException) {
                $target->dispatch('validation-failed')->self();
            }
        });
    }

    /**
     * Bind the {current_team} segment to its Team so scoped bindings resolve records through that team,
     * and the {catalog} and {libraryCatalog} segments to the catalog addressed by their slug.
     */
    protected function configureRouteBindings(): void
    {
        Route::model('current_team', Team::class);

        Route::bind('catalog', fn (string $slug): Catalog => Catalog::fromSlug($slug) ?? abort(404));

        Route::bind('libraryCatalog', fn (string $slug): LibraryCatalog => LibraryCatalog::fromSlug($slug) ?? abort(404));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }
}
