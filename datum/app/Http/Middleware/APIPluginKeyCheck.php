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

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class APIPluginKeyCheck
 * @package App\Http\Middleware
 */
class APIPluginKeyCheck 
{
    use Common;

    /**
     * @param $request
     * @param Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle($request, Closure $next)
    {
        if(( isset(getallheaders()['site_url']) && getallheaders()['site_url'] !='' ) && ( isset(getallheaders()['activation_key']) && getallheaders()['activation_key'] != '') ) {
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);

            $configuration = Congigurations::where('ActivationKey', getallheaders()['activation_key'])
                ->where('SiteUrl', $siteUrl)
                ->where('Status', 1)
                ->first();

            if( !empty($configuration) ) {

                if(isset($request->action)){
                    $action = $request->action;
                }else{
                    $action = 'Login';
                }
                $log = [
                    'Ip'            => $request->user_ip,
                    'User_id'       => '',
                    'Action'        => $action,
                    'Status'        => 'Start',
                ];
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