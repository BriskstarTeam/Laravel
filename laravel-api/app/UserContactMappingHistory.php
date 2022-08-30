<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserContactMappingHistory extends Model
{
    public $timestamps = false;

    protected $table = 'UserContactMappingHistory';
    
    protected $fillable = [
        "UserId",
        "UserTypeId",
        "Status",
        "IndustryRoleId",
        "InvestorTypeId",
        "BrokerTypeId",
        "SubscriptionTypeId",
        "ConfigurationId",
        "CreatedOn",
        "CreatedBy",
        "UpdatedOn",
        "UpdatedBy",
        "LastModifiedAt",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param $data
     * @return UserContactMappingHistory
     */
    public function adduserContactMappingHistory($data) {
        $userContactMappingHistory = new UserContactMappingHistory($data);
        $userContactMappingHistory->save();
        return $userContactMappingHistory;
    }
}
