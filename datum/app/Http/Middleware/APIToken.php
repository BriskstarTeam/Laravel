<?php

namespace App\Http\Middleware;

use App\PluginActivation;
use Closure;
use Illuminate\Support\Facades\App;
use App\Helpers\DatabaseConnection;
use App\Congigurations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Traits\Common;

class APIToken
{
    use Common;
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
        if(( isset(getallheaders()['site_url']) && getallheaders()['site_url'] !='' ) && ( isset(getallheaders()['activation_key']) && getallheaders()['activation_key'] != '') ) {
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);

            $configuration = Congigurations::where('ActivationKey', getallheaders()['activation_key'])
                ->where('SiteUrl', $siteUrl)
                ->where('Status', 1)
                ->first();
            /**
             * Check master database activation key and site url valid
             */

            if( $configuration ) {
                /**
                 * Change database connection runtime
                 * master database to child database
                 */
                return $next($request);
            } else {
                return response()->json(
                    [
                        'status' => 'failed',
                        'message' => 'Plugin is not activated. Please activate first',
                        'errors' => [],
                        'data' => []
                    ], 200);
            }
        } else {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Plugin is not activated. Please activate first',
                    'errors' => [],
                    'data' => []
                ], 200);
        }
    }
}
