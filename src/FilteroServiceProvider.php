<?php

namespace Vati\Filtero;

use Illuminate\Support\ServiceProvider;

class FilteroServiceProvider extends ServiceProvider
{
    private string $package = 'filtero';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->publishes([
            __DIR__ . '/config/' . $this->package . '.php' => config_path($this->package . '.php'),
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/' . $this->package . '.php',
            $this->package
        );
    }
}
