<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Database\DatabaseConnection;
use Illuminate\Support\Facades\DB;

class InsightsPageAccess extends Model
{
    public $timestamps = false;

    protected $table = 'InsightPageAccess';

    protected $fillable = [
        "Id",
        "IndustryRoleId",
        "UserId",
        "Email",
        "Access",
        "HasDefaultAccess",
        "ConfigurationId",
        "ApprovedBy",
        "ApprovedDate",
        "RejectedBy",
        "RejectedDate",
        "CreatedBy",
        "CreatedDate",
        "UpdatedBy",
        "UpdatedDate",
        "LastModifiedAt",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param string $userId
     * @param array $data
     * @param string $Id
     */
    public function addUpdatePageAccess($userId='',$data = [],$Id = ''){
        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();
        if($Id != ''){

            $InsightPageAccessData = array(
                "UpdatedBy"         => $userId,
                "Access"            => 2,
                "UpdatedDate"       => date('Y-m-d H:i:s'),
                "RejectedBy"        => NULL,
                "RejectedDate"      => NULL,
                "ConfigurationId"   => $configurations->ConfigurationId,
            );


            $user = InsightsPageAccess::where('Id',$Id )->update($InsightPageAccessData);
            
        }else{
            //DB::enableQueryLog();
            $InsightPageAccessData = array(
                "UserId"        => $userId,
                "Access"        => 2,
                "CreatedBy"     => $userId,
                "CreatedDate"   => date('Y-m-d H:i:s'),
                "ConfigurationId" => $configurations->ConfigurationId,
            );

            $InsightPageAccess = new InsightsPageAccess($InsightPageAccessData);
            
            if($InsightPageAccess->save()){
                //return true;
            }
            //dd(DB::getQueryLog());

        }
    }
}