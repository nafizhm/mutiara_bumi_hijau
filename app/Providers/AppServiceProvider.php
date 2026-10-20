<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\ActivityRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\DB::listen(function ($event) {
            app(\App\Services\ActivityRecorder::class)->capture($event);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\TransactionCommitted::class, function ($event) {
            app(\App\Services\ActivityRecorder::class)->committed($event->connection->transactionLevel());
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\TransactionRolledBack::class, function ($event) {
            app(\App\Services\ActivityRecorder::class)->rolledBack($event->connection->transactionLevel());
        });
        foreach ([\Illuminate\Auth\Events\Login::class => 'login', \Illuminate\Auth\Events\Logout::class => 'logout'] as $eventClass => $action) {
            \Illuminate\Support\Facades\Event::listen($eventClass, function ($event) use ($action) {
                if ($event->user && request()->route()) {
                    app(\App\Services\ActivityRecorder::class)->write(request(), $event->user, $action);
                }
            });
        }
    }
}
