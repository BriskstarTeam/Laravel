<?php

namespace App\Activity;
use App\Congigurations;
use App\UserActivity;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;

/**
 * Class Activity
 * @package App\Activity
 */
class Activity {

    /**
     * Activity constructor.
     */
    public function __construct() {
        $databaseName = Config::get('database.connections.'.Config::get('database.default'));

        if( DB::connection()->getDatabaseName() != $databaseName['database'] ) {
            Config::set('database.connections.mysql.database', $databaseName['database']);
            Config::set('database.connections.mysql.username', $databaseName['username']);
            Config::set('database.connections.mysql.password', $databaseName['password']);

            DB::purge('mysql');
            DB::reconnect('mysql');
        }

    }

    /**
     * @param array $activity
     * @return bool
     */
    public function addActivity($activity = array()) {
        $userActivity = new UserActivity();
        $userActivity->user_id = $activity['user_id'];
        $userActivity->ip_address = $activity['ip_address'];
        $userActivity->page_url = $activity['page_url'];
        $userActivity->api_url = $activity['api_url'];
        $userActivity->user_agent = $activity['user_agent'];
        if($userActivity->save()) {
            return true;
        }
    }
}
