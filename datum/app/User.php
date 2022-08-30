<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Traits\Common;
use App\Traits\EncryptionDecryption;
use App\Companies;
use App\UserContactMapping;
use App\UserContactMappingHistory;
use App\Database\DatabaseConnection;
use Illuminate\Support\Facades\Storage;
use Image;
use Illuminate\Support\Str;
class User extends Authenticatable
{
    use HasApiTokens, Notifiable,EncryptionDecryption, Common;

    protected $table = "Users";

    public $timestamps = false;

    //const CREATED_AT = 'CreatedDate';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $primaryKey = 'Id';

    protected $fillable = [
        "GuId",
        "FirstName",
        "LastName",
        "Email",
        "Password",
        "ProfileImage",
        "Title",
        "CompanyId",
        "IndustryRoleId",
        "InvestorTypeId",
        "BrokerTypeId",
        "LinkedIn",
        "CorporateLicense",
        "Bio",
        "UserTypeId",
        "Status",
        "CreatedBy",
        "UpdatedBy",
        "TeamSubCategoryId",
        "Username",
        "ConfigurationId",
        "IsSuperAuthorizedAccount",
        "SubscriptionTypeId",
        "LastSiteLoginTime",
        "IsContactCreatedByDashboard",
        "NextUpdateDate",
        "ResetPassExpires",
        "ExchangeStatusId",
        "IsRegistrationCompleted",
        "IsSocial",
        "SocialMediaId",
        "CreatedDate",
        "UpdatedDate",
        "NormalizedEmail",
        "ConcurrencyStamp",
        "SecurityStamp"
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'Password',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'NextUpdateDate' => 'datetime',
        'ResetPassExpires' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getAcquisitioncriteriaContactRelation() {
        return $this->hasMany(AcquisitioncriteriaContactRelation::class, 'UserId')->where('Status', 1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getUserAddressDetailsRelation() {
        return $this->hasOne(UserAddressDetails::class, 'UserId');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getUserVerificationRelation() {
        return $this->hasOne(UserVerification::class, 'UserId');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getLicense() {
        return $this->hasMany(UserLicense::class, 'UserId')->select('UserId', 'State', 'Text');
    }

    /**
     * @param $data
     * @param string $userId
     * @return User|bool
     */
    public function addUpdateUser($data ,$userId = ''){
        
        $databaseConnection = new DatabaseConnection();
        $currentConnection 	= $databaseConnection->getConnectionName();
        $configurations 	= $databaseConnection->getConfiguration();
        $password       = '';
        if (isset($data->password) && $data->password != '') {
            $password   = md5($data->password);
        }


        if($data->i_am == 1) {
            $InvestorTypeId = isset($data->investor_type) ? $data->investor_type : null;
            $BrokerTypeId   = null;
        } elseif ($data->i_am == 2) {
            $BrokerTypeId   = isset($data->brokertype_h) ? $data->brokertype_h : null;
            $InvestorTypeId = null;
        } else {
            $InvestorTypeId = null;
            $BrokerTypeId   = null;
        }

        $companyData = [];
        if(isset($data->company) && $data->company != "") {
            $companies = Companies::where('CompanyName', $data->company)->first();
            if( !empty($companies) && $companies != null ) {
                $CompanyId = $companies->Id;
                $companyData = $companies;
            } else {
                $companyData = new Companies();
                $companyData->ConfigurationId = $configurations->ConfigurationId;
                $companyData->CompanyName = $data->company;
                $companyData->CreatedDate = date('Y-m-d H:i:s');
                $companyData->IsDelete = 0;
                $companyData->save();
                $CompanyId = $companyData->Id;    
            }
        } else {
            $CompanyId = null;
        }

        $userData = [
            "Email" 					=> strtolower($data->email),
            "IsSuperAuthorizedAccount" 	=> 0,
            "SubscriptionTypeId" 		=> 3,
        ];

        if(isset( $data->firstName )){
            $userData['FirstName'] = str_replace("\'","'",ucwords($data->firstName));
            

        }
        if(isset( $data->lastName )){
            $userData['LastName'] = str_replace("\'","'",ucwords(self::lastNameFields($data->lastName)));
        }

        if(isset( $data->Title )){
            $userData['Title'] = $data->Title;
        }

        if(!empty( $CompanyId )){
            $userData['CompanyId'] = $CompanyId;
        }else{
            $userData['CompanyId'] = null;
        }   

        if(isset( $data->LinkedIn ) && $data->LinkedIn != ""){
            $userData['LinkedIn'] = $data->LinkedIn;
        } else {
            $userData['LinkedIn'] = "";
        }
        
        $acr = new Acquisitioncriteria1031Relation();

        if(isset($userId) && $userId != ''){

            $user       = User::findOrFail($userId);
            
            if($user->ExchangeStatusId !=  $data->exchange_status) {
                $userData['NextUpdateDate'] = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). ' + 6 months'));
            }

            if($user->NextUpdateDate == null) {
                $userData['NextUpdateDate'] = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). ' + 6 months'));
            }

            if($password != ''){
                $userData['Password'] = $password;
            }

            if(isset($data->IsRegistrationCompleted)){
                $userData['IsRegistrationCompleted'] = 1;
            }
            $userData['ExchangeStatusId'] = isset( $data->exchange_status ) ? $data->exchange_status : '';

            $userData['UpdatedBy']      = $userId;
            $userData['UpdatedDate']    = date('Y-m-d H:i:s');
            $user = User::where('Id',$userId )->update($userData);

            $user = User::findOrFail($userId);
            
            $acrData    = $acr->addUpdate1031($data,$user);
            $UserContactMapping = new UserContactMapping();
            $contactMappingData  = array(
                'IndustryRoleId'     => $data->i_am,
                'InvestorTypeId'     => $InvestorTypeId,
                'BrokerTypeId'       => $BrokerTypeId,
                'UserId'             => $userId,
                'UserTypeId'         => env("USER_TYPE_ID"),
                'Status'             => 2,
                'SubscriptionTypeId' => 3,
                'ConfigurationId'    => $configurations->ConfigurationId,
                'CreatedOn'          => date('Y-m-d H:i:s'),
                'CreatedBy'          => $userId,
                'UpdatedOn'          => date('Y-m-d H:i:s'),
                'UpdatedBy'          => $userId
            );
            $UserContactMapping->adduserContactMapping($contactMappingData);
        }else{

            if(isset($data->social) && $data->social != ''){
                $userData['IsSocial']           = 1;
                $userData['SocialMediaId']      = 2;
            }

            $userData['UserTypeId']                = env("USER_TYPE_ID");
            $userData['Password']                  = $password;
            $userData['Status']                    = 2;
            $userData['IsRegistrationCompleted']   = 0;
            $userData['CreatedDate']               = date('Y-m-d H:i:s');
            $userData['NextUpdateDate']            = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s'). ' + 6 months'));
            $userData['GuId'] = $this->GUID();
            $userData['ConfigurationId']           = $configurations->ConfigurationId;

            $userData['NormalizedEmail'] = strtoupper($userData['Email']);
            $userData['ConcurrencyStamp'] = $this->GUID();
            $userData['SecurityStamp'] = $this->GUID();

            $user = new User($userData);
            if($user->save()){
                $user->CreatedBy = $user->Id;
                $user->UpdatedBy = $user->Id;
                $user->save();

                $userContactMapping = new UserContactMapping();
                $userContactMapping->UserId = $user->Id;
                $userContactMapping->UserTypeId = env("USER_TYPE_ID");
                $userContactMapping->Status = 2;
                $userContactMapping->SubscriptionTypeId = 3;
                $userContactMapping->ConfigurationId = $configurations->ConfigurationId;
                $userContactMapping->CreatedOn = date('Y-m-d H:i:s');
                $userContactMapping->CreatedBy = $user->Id;
                $userContactMapping->save();

                $UserContactMappingHistory = new UserContactMappingHistory();
                $UserContactMappingHistory->UserId = $user->Id;
                $UserContactMappingHistory->UserTypeId = env("USER_TYPE_ID");
                $UserContactMappingHistory->Status = 2;
                $UserContactMappingHistory->SubscriptionTypeId = 3;
                $UserContactMappingHistory->ConfigurationId = $configurations->ConfigurationId;
                $UserContactMappingHistory->CreatedOn = date('Y-m-d H:i:s');
                $UserContactMappingHistory->CreatedBy = $user->Id;
                $UserContactMappingHistory->save();
            }else{
                return false;
            }
        }
        return $user;
    }

    /**
     * @param string $image_64
     * @param string $user
     */
    public function addUserImage($image_64 = '',$user = ''){
        
        $profileContainerName   = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');

        
        $azurePath              = env('AZURE_STORAGE_URL');
        if ( !empty($image_64) && $image_64 != null && $image_64 != 1 && !str_contains($image_64, 'https')  ) { 
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1]; 
            $imageName = Str::random(10).'.'.$extension;

            $fileName = strtolower($user->FirstName.'_'.$user->LastName).'_'.time().'.'.$extension;
                
            $height         = Image::make($image_64)->height();
            $width          = Image::make($image_64)->width();

            $resized_image  = Image::make($image_64)->resize($width,$height)->stream('png', 100);

            $fullUrl = $profileContainerName.'/'.$fileName;
            
            Storage::disk('azure')->put($fullUrl, $resized_image);
            $body = '{
                "UserId":"'.$user->Id.'",
                "ImageName":"'.$fileName.'",
                "ImageType":"image/png",
                "ImageBase64String":"'.$azurePath.'/'.$fullUrl.'"
            }';
            $response = self::saveImageOnBlobStorage($body);

            if(!empty($response)){
                if($response->type == 'success'){
                    $userData['ProfileImage'] = $fileName;
                    $user = User::where('Id',$user->Id )->update($userData);
                }
            }else{
                $userData['ProfileImage'] = '';
                $user = User::where('Id',$user->Id )->update($userData);
            }
        }else {
            if($image_64 == null ) {
                $userData['ProfileImage'] = '';
                $user = User::where('Id',$user->Id )->update($userData);
            }
        }
        
    }

    /**
     * @param null $data
     * @param string $user
     */
    public function LinkedInImageUpload($data = null,$user = '')
    {
        $profileContainerName   = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');

        $azurePath              = env('AZURE_STORAGE_URL');

        $imageName = strtolower($user->FirstName.'_'.$user->LastName).'_'.time().'.png';


        $image_64 = public_path('linkedin').'/'.$imageName;

        file_put_contents($image_64, file_get_contents($data->LinkedinImage) );
        
        chmod($image_64, 0777);

        $fullUrl        = $profileContainerName.'/'.$imageName;
        $height         = Image::make($image_64)->height();
        $width          = Image::make($image_64)->width();
        $resized_image  = Image::make($image_64)->resize($width,$height)->stream('png', 100);


        $res = Storage::disk('azure')->put($fullUrl,$resized_image);

        $body = '{
            "UserId":"'.$user->Id.'",
            "ImageName":"'.basename($imageName).'",
            "ImageType":"image/png",
            "ImageBase64String":"'.$azurePath.'/'.$fullUrl.'"
        }';
        $response = self::saveImageOnBlobStorage($body);
        if(!empty($response)){
            if($response->type == 'success'){
                $userData['ProfileImage'] = $imageName;
                $user = User::where('Id',$user->Id )->update($userData);
                return;
            }else{
                return;
            }
        }else{
            return;
        }
    }

    /**
     * @param string $body
     * @return mixed
     */
    public function saveImageOnBlobStorage ( $body = "" ) {
        $imageCompressURL = env('DASHBOARD_IMAGE_COMPRESS_URL');
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $imageCompressURL,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $body,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
          ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
    }

    /**
     * @param string $lastName
     * @return string
     */
    public function lastNameFields( $lastName = "" ) {
        $lName = "";
        if ( $lastName != "" ) {
            $op = substr($lastName, 0, 2);
            if ($op == "mc" || $op == "Mc" || $op == "MC") 
            {
                $lastName1 = ucwords("Mc".strtoupper(substr($lastName, 2, 1)).strtolower(substr($lastName, 3)));
                $lName = $lastName1;
            } else {

                $lastName1 = ucwords(strtolower($lastName));
                $lName = $lastName1;
            } 
        }

        $lName = implode("'", array_map('ucfirst', explode("'", $lName)));
        return $lName;
    }
}
