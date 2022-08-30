<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Database\DatabaseConnection;

class UserAccountHistory extends Model
{
    public $timestamps = false;
    protected $table = 'UserAccountHistory';
    protected $fillable = ["Id", "UserId", "Description", "ModuleType", "Ip", "BrowserName", "CreatedBy", "CreatedDate", "ConfigurationId","HostedConfigurationId"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param $data
     * @param string $userId
     * @param string $text
     * @return UserAccountHistory
     */
    public function addUpdateUserAccountHistory($data ,$userId = '',$text = ''){
        
        $databaseConnection = new DatabaseConnection();
        $currentConnection 	= $databaseConnection->getConnectionName();
        $configurations 	= $databaseConnection->getConfiguration();
        $user       = User::where('Id', $userId)->first();
        $hostedConfId = null;

        if ( !empty($user)) {
            $hostedConfId = $user->ConfigurationId;
        } else {
            $hostedConfId = $configurations->ConfigurationId;
        }

        $AccountHistory = [
            "UserId"            => $userId,
            "Description"       => $text,
            "ModuleType"        => 1,
            "Ip"                => $data->user_ip,
            "BrowserName"       => $data->user_agent,
            "CreatedBy"         => $userId,
            "CreatedDate"       => date('Y-m-d H:i:s'),
            "ConfigurationId"   => $configurations->ConfigurationId,
            "HostedConfigurationId" => $hostedConfId,
        ];
        $userAccountHistory = new UserAccountHistory($AccountHistory);
        $userAccountHistory->save();
        return $userAccountHistory;

    }
}
