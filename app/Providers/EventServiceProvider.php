<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Manual event mappings. Listeners under app/Listeners are auto-discovered
     * via Application::configure()->withEvents() — do not duplicate them here.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];
}
