<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserContactMapping extends Model
{
    public $timestamps = false;

    protected $table = 'UserContactMapping';
    
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
     * @return bool
     */
    public function adduserContactMapping($data) {
        
        $userContactMapDT = UserContactMapping::where('UserId', $data['UserId'])->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();
        $UserContactMappingHistory = new UserContactMappingHistory();

        if(!empty($userContactMapDT)) {

            $userContactMapDT->IndustryRoleId = $data['IndustryRoleId'];
            $userContactMapDT->InvestorTypeId = $data['InvestorTypeId'];
            $userContactMapDT->BrokerTypeId = $data['BrokerTypeId'];
            $userContactMapDT->UpdatedOn = $data['UpdatedOn'];
            $userContactMapDT->UpdatedBy = $data['UpdatedBy'];
            $userContactMapDT->save();
            
            $history = array(
                'IndustryRoleId'     => $data['IndustryRoleId'],
                'InvestorTypeId'     => $data['InvestorTypeId'],
                'BrokerTypeId'       => $data['BrokerTypeId'],
                'UserId'             => $data['UserId'],
                'UserTypeId'         => $userContactMapDT->UserTypeId,
                'Status'             => $userContactMapDT->Status,
                'SubscriptionTypeId' => $userContactMapDT->SubscriptionTypeId,
                'ConfigurationId'    => $data['ConfigurationId'],
                'CreatedOn'          => date('Y-m-d H:i:s'),
                'CreatedBy'          => $data['UserId'],
                'UpdatedOn'          => date('Y-m-d H:i:s'),
                'UpdatedBy'          => $data['UserId']
            );
            $UserContactMappingHistory->adduserContactMappingHistory($history);
        } else {
            unset($data['UpdatedOn']);
            unset($data['UpdatedBy']);
            $userContactMapping = new UserContactMapping($data);
            $userContactMapping->save();
        }
        return true;
    }
}
