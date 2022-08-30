<?php

namespace App\Providers;

use App\Congigurations;
use App\PluginActivation;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use Laravel\Passport\Bridge\PersonalAccessGrant;
use League\OAuth2\Server\AuthorizationServer;

class AppServiceProvider extends ServiceProvider
{

    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Passport::routes();
        $lifetime = new \DateInterval('PT24H'); // For 24hours
        $this->app->get(AuthorizationServer::class)->enableGrantType(new PersonalAccessGrant(), $lifetime);
    }
}
