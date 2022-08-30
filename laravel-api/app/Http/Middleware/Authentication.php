<?php

namespace App\Http\Middleware;

use App\PluginActivation;
use Closure;
use Illuminate\Support\Facades\App;
use App\Helpers\DatabaseConnection;
use App\Congigurations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Authentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /**
         * @param site_url
         * @param activation_key
         * Check site url and activation key is valid
         */
        return $next($request);
    }
}
