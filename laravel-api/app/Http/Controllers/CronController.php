<?php

namespace App\Http\Controllers;

use App\Congigurations;
use Illuminate\Http\Request;
use App\Properties;
use App\WpOsdUserPropertiesRelationship;
use App\OeplPropertyTracker;
use App\DocumentVault;
use App\Directory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\Collection;
use App\Traits\Common;
use Twilio\Rest\Client;
use App\Database\DatabaseConnection;

/**
 * Class CronController
 * @package App\Http\Controllers
 */
class CronController extends Controller
{
    use Common;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $confidential_agrement  = 'confidential_agrement';
        $download_document      = 'download_document';
        
        $days = 1;
        self::createCronJobLog('test');
        //get the data of the configurations table 
        $congigurations = Congigurations::get();

        if ( !empty($congigurations) && $congigurations != null ) {
            foreach ( $congigurations as $key => $value ) {

                $dd_dir = public_path($download_document.'/'.$value->SiteUrl);
                $ca_dir = public_path($confidential_agrement.'/'.$value->SiteUrl);

                //remove the CA older than 24 Hrs.
                if(is_dir($ca_dir)) {
                    if ($handle = opendir($ca_dir)) {
                        
                        //echo $handle;
                        while (( $file = readdir($handle)) !== false ) {
                            //Skip the default directories 
                            if ( $file == '.' || $file == '..') {
                                continue;
                            }
                            if( ( time() - filectime($ca_dir.'/'.$file ) ) > ($days *86400) ) {
                                self::deleteDirectory($ca_dir.'/'.$file);
                            }
                        }
                        closedir($handle);
                    } 
                }

                //remove the DD older than 24 Hrs.
                if(is_dir($dd_dir)) {
                    if ($handle = opendir($dd_dir)) {
                        //echo $handle;
                        while (( $file = readdir($handle)) !== false ) {
                            //Skip the default directories 
                            if ( $file == '.' || $file == '..') {
                                continue;
                            }
                            if( ( time() - filectime($dd_dir.'/'.$file ) ) > ($days *86400) ) {
                                self::deleteDirectory($dd_dir.'/'.$file);
                            }
                        }
                        closedir($handle);
                    }
                }
            }
        }

        return response()->json(
        [
            'status' => 'success',
            'message' => '',
            'errors' => [],
            'data' => [],
        ], 200);
    }

    /**
     * @param $dir
     * @return bool
     */
    public function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            self::createLog($dir);
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }

        }

        return rmdir($dir); 
    }

    /**
     * @param string $deletedName
     */
    public function createLog ($deletedName = '') {
        $log  = "INFO: ".date("F j, Y, g:i a")." ".$deletedName.' - Deleted '.PHP_EOL;
        $logFolder = public_path('custome_log');
        if ( !is_dir( $logFolder ) ) {
            mkdir($logFolder, 0777, true) || chmod($logFolder, 0777);
        } 
        //chmod($logFolder.'/log_'.date("j.n.Y").'.log', 0777);
        file_put_contents($logFolder.'/log_'.date("j.n.Y").'.log', $log, FILE_APPEND);
    }

    public function createCronJobLog () {
        $log  = "INFO: ".date("F j, Y, g:i a").' - This is a cron job log '.PHP_EOL;
        $logFolder = public_path('custome_log');
        if ( !is_dir( $logFolder ) ) {
            mkdir($logFolder, 0777, true) || chmod($logFolder, 0777);
        } 
        //chmod($logFolder.'/log_'.date("j.n.Y").'.log', 0777);
        file_put_contents($logFolder.'/cron_job_log_'.date("j.n.Y").'.log', $log, FILE_APPEND);
    }

    public function checkEmailReal(Request $request) {
        // https://quickemailverification.com/verify
        try {
            $client = new \QuickEmailVerification\Client('24e00452c32d19d4f647fbedc26b80a709dd2986048917b6bb2633997c00');
            $quickemailverification = $client->quickemailverification();
            $response = $quickemailverification->verify($request->email);
            return response()->json(
            [
                'status'            => 'success',
                'errors'            => [],
                'data'              => $response,
            ], 200);
        } catch (Exception $e) {
            echo "Code: " . $e->getCode() . " Message: " . $e->getMessage();
        }
    }

    public function checkMobileReal(Request $request) {
        $sid = env('ACCOUNT_SID');
        $token = env('ACCOUNT_TOCKEN');

        $country_code = env('COUNTRY_CODE');
        try {
            $twilio = new Client($sid, $token);
            $phone_number = $twilio->lookups->v1->phoneNumbers("+917405152998")->fetch(["countryCode" => "IN"]);

            dd($phone_number);
        } catch (Exception $e) {
            echo "Code: " . $e->getCode() . " Message: " . $e->getMessage();
        }
    }
}