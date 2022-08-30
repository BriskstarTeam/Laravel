<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Database\DatabaseConnection;
class UserVerification extends Model
{
    public $timestamps = false;

    protected $table = 'UserVerification';

    protected $fillable = [
        "UserId",
        "IsEmailVerified",
        "EmailVerifiedBy",
        "EmailVerifiedDatetime",
        "IsMobileVerified",
        "MobileVerificationDatetime",
        "VerificationId",
        "IsAccountVerified",
        "AccountVerifiedBy",
        "AccountVerifiedDatetime",
        "VerificationExpiryDate",
        "AcquisitionCriteriaUpdatedBy",
        "AcquisitionCriteriaDate",
        "ConfigurationId",
        "HostedConfigurationId",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param $data
     * @param string $userId
     * @return UserVerification
     */
    public function addUpdateUserVerification($data ,$userId = ''){
        $varificationId     = md5(uniqid());
        $expDateTime = env('EMAIL_VERIFICATION_HOURS');
        $expDateTime = date("Y-m-d H:i:s", strtotime('+'.$expDateTime.' hours'));
        $databaseConnection = new DatabaseConnection();
        $currentConnection 	= $databaseConnection->getConnectionName();
        $configurations 	= $databaseConnection->getConfiguration();
        $verificationDetails = UserVerification::where('UserId', $userId)->first();

        $verification = [
            "UserId"                        => $userId,
            "IsEmailVerified"               => 0,
            "IsMobileVerified"              => 0,
            "VerificationExpiryDate"        => $expDateTime,
            'AcquisitionCriteriaUpdatedBy'  => $userId,
            'AcquisitionCriteriaDate'       => date('Y-m-d H:i:s'),
            "ConfigurationId"               => $configurations->ConfigurationId,
            "HostedConfigurationId"         => $configurations->ConfigurationId,
        ];
        
        if(empty($verificationDetails)){
            $verification['VerificationId'] = $varificationId;
            $userverification = new UserVerification($verification);
            $userverification->save();
        }else{
            $userverification = UserVerification::where('UserId',$userId )->update($verification);
        }
        return $userverification;

    }
}
