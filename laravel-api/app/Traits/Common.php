<?php
namespace App\Traits;
use Jenssegers\Agent\Agent;

use App\Database\DatabaseConnection;
use Hashids\Hashids;

/**
 * Trait Common
 * @package App\Traits
 */
trait Common {

    /**
     * @param string $code
     * @return mixed
     */
    public function decodeId($code = ''){
        $hashids = new Hashids('NEWmark_2022',8,'1234567890abcdef');
        $numbers = $hashids->decode($code);
        return $numbers;
    }

    /**
     * @param string $url
     * @return array|mixed|string|string[]
     */
    public function removeHttp ($url = "") {
        $disallowed = array('http://', 'https://');
        foreach($disallowed as $d) {
            if(strpos($url, $d) === 0) {
                $url1 = str_replace($d, '', $url);
                if( strpos($url1, 'www.') === 0) {
                    return str_replace('www.', '', $url1);
                } else {
                    return $url1;
                }
            }
        }

        return $url;
    }

    /**
     * @return mixed
     */
    public function getIp () {
        $user_ip = '';
        if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $user_ip = $_SERVER['REMOTE_ADDR'];
        }
        return $user_ip;
    }

    /**
     * @return mixed
     */
    public function getDevice() {
        $agent = new Agent();
        return $agent->device();
    }

    public function GUID($data = null) {
        /*$guid = md5(uniqid());
        return $guid;*/
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param string $name
     * @return string
     */
    public function propertyName( $name = "") {
        $propertyName = "";
        if($name != "") {
            $propertyName = str_replace(' ', '-', $name);
            return strtolower($propertyName);
        } else {
            return strtolower($propertyName);
        }
    }

    /**
     * @param array $log
     */
    public function logAdd($log = array()){
        dd(5);
        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();
        
        $path = public_path('datumlogs/'.$configurations->SiteUrl);
        $date = public_path('datumlogs/'.$configurations->SiteUrl.'/'.date('Y-m-d').'-log.log');

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if (file_exists($date)) {
            $fh = fopen($date, 'a');
            fwrite($fh, 'd');
        } else {
            echo "sfaf";
            $fh = fopen($date, 'wb');
            fwrite($fh, 'd');
        }

        fclose($fh);
        chmod($date, 0777);
        $orderLog = new Logger('Datum');
        $orderLog->pushHandler(new StreamHandler($date), Logger::INFO);
        $orderLog->info('DatumLog', $log);
    }
}