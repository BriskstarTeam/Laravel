<?php

namespace App\Http\Controllers;

use App\PluginActivation;
use App\Properties;
use App\SiteConfigurations;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Database\DatabaseConnection;

class ConfigurationsController extends Controller
{
    public $configurations = [];

    /**
     * @return \Illuminate\Http\JsonResponse
     */

    public function index(Request $request) {
        if ( ( isset( getallheaders()['activation_key'] ) && getallheaders()['activation_key'] != '' ) && ( isset( getallheaders()['site_url'] ) && getallheaders()['site_url'] ) ) {
            $plActivated = PluginActivation::with('getSiteConfigurations')
                ->where('activation_key', getallheaders()['activation_key'])
                ->first();

            return response()
                ->json(
                    [
                        'status'=>'success',
                        'message' => [],
                        'errors' => [],
                        'data'=> $plActivated
                    ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        die("TEST");
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed',
        ]);

        $siteConfigurations = new SiteConfigurations([
            'plugin_activation_key' => '',
            'last_name' => $request->last_name,
            'email' => $request->email,
        ]);
        $siteConfigurations->save();

        return response()->json([

            'status'=>'success',
            'message' => 'Successfully created user!',
            'errors' => [],
            'data' => $siteConfigurations
        ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfiguration( Request $request ) {
        $databaseConnection = new DatabaseConnection();
        $this->configurations = $databaseConnection->getConfiguration();

        if( !empty ( $this->configurations ) && $this->configurations != null ) {
            $data = array(
                'SENDGRID_EMAIL' => $this->configurations->FromEmail,
                'SENDGRID_NAME' => $this->configurations->SiteName,
                'SITE_URL' => $this->configurations->SiteUrl,
                'SITE_NAME'=>$this->configurations->SiteName,
                'EMAIL_SIGNATURE' => html_entity_decode($this->configurations->EmailSignature)
            );
        } else {
            $data = array(
                'SENDGRID_EMAIL' => config('constants.SENDGRID_EMAIL'),
                'SENDGRID_NAME' => config('constants.SENDGRID_NAME'),
                'SITE_URL' => config('constants.SITE_URL'),
                'SITE_NAME'=>config('constants.SITE_NAME'),
                'EMAIL_SIGNATURE' => null
            );
        }
        
        return response()->json([
            'status'=>'success',
            'message' => '',
            'errors' => [],
            'data' => $data
        ], 200);
    }
}
