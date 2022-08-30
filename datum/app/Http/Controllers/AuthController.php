<?php

namespace App\Http\Controllers;

use App\Mail\Email;
use App\UserAddressDetails;
use App\UserVerification;
use App\UserVerificationHistory;
use App\Leadbase;
use App\UserOtpCheck;
use App\UserOtpCheckHistory;
use App\AcquisitioncriteriaContactRelation;
use App\AcquisitioncriteriaType;
use App\Acquisitioncriteria1031Relation;
use App\UserAccountHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\User;
use App\PasswordReset;
use Illuminate\Support\Str;
use App\Rules\MatchOldPassword;
use App\Rules\EmailVerificationUnique;
use App\Rules\EmailUnique;
use App\Rules\VerifyOldPassword;
use App\Traits\EncryptionDecryption;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\InsightAccessController;
use App\Database\DatabaseConnection;
use App\UserLoginHistory;
use App\Traits\Common;
use Jenssegers\Agent\Agent;
use Twilio\Rest\Client;
use App\Companies;
use App\DashboardParentAdmin;
use App\Property\Property;
use App\Property\DocumentVaultomAccess;
use Illuminate\Support\Facades\DB;
use App\Password\PasswordHash;
use Illuminate\Support\Facades\Storage;
use Image;
use Illuminate\Validation\Rule;
use App\UserContactMapping;
use App\UserContactMappingHistory;
use App\Property\PressreleaseHistory;
/**
 * Class AuthController
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    use EncryptionDecryption, Common;
    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        $azurePath = env('AZURE_STORAGE_URL');

        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();

        if(isset($request->social_login) && $request->social_login != ''){
            $request->validate([
                'email' => 'required|string|email',
            ]);
        }else{
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
                'remember_me' => 'boolean'
            ]);
        }

        $user_ip_address = $request->user_ip;
        $user_agent = $request->user_agent;
        if( ( isset( $request->confirm_password ) && $request->confirm_password != "" ) && ( isset ($request->update_password) && $request->update_password != "" && $request->update_password == 'yes')) {

            $request->validate([
                'password' => 'required|min:8',
                'confirm_password' => 'required|same:password'
            ]);

            if( isset ( $request->token ) && $request->token != "" ) {
                $userVerification = UserVerification::where('VerificationId', $request->token)->first();

                if(empty($userVerification) || $userVerification == null ) {
                    return response()->json([
                            'status' => 'failed',
                            'message' => 'This email verification token is invalid.',
                            'errors' => [],
                            'data' => []
                        ], 200);
                }

                if(!Carbon::parse($userVerification->VerificationExpiryDate)->addHour(env('EMAIL_VERIFICATION_HOURS'))->isPast()) {
                    if(!empty($userVerification) && $userVerification != null ) {
                        if($userVerification->IsEmailVerified == 1 || $userVerification->IsMobileVerified == 1) {
                            return response()->json([
                                'status' => 'success',
                                'message' => 'User aleready verifyed.',
                                'errors' => [],
                                'isVerifiedUser'=> 'yes',
                                'data' => $userVerification
                            ], 200);
                        }

                        $userVerification->IsEmailVerified = 1;
                        $userVerification->EmailVerifiedDateTime = date("Y-m-d H:i:s");
                        $userVerification->EmailVerifiedBy = $userVerification->UserId;

                        $userVerificationHistory = new UserVerificationHistory([
                            "UserId" => $userVerification->UserId,
                            "VerifiedDatetime" => date("Y-m-d H:i:s"),
                            "VerifiedBy" => $userVerification->UserId,
                            "IsVerified" => 1,
                            "VerificationType" => 1,
                            "ConfigurationId"   => $configurations->ConfigurationId,
                            "HostedConfigurationId" => $configurations->ConfigurationId,
                        ]);
                        $user = User::where('Id', $userVerification->UserId)->first();
                        $userAccountHistory = new UserAccountHistory([
                            "UserId" => $userVerification->UserId,
                            "Description" => 'Contact Verified',
                            "ModuleType" => 1,
                            "Ip" => $user_ip_address,
                            "BrowserName" => null,
                            "CreatedBy" => $userVerification->UserId,
                            "CreatedDate" => date('Y-m-d H:i:s'),
                            "ConfigurationId" => $configurations->ConfigurationId,
                            "HostedConfigurationId" => $user->ConfigurationId
                        ]);
                        $userAccountHistory->save();
                        
                        $UserContactMapping = UserContactMapping::where('UserId', $userVerification->UserId)->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();
                        $UserContactMapping->Status = 1;
                        if($UserContactMapping->save()) {
                            
                            $UserContactMappingHistory = new UserContactMappingHistory(array(
                                "UserId" => $UserContactMapping->UserId,
                                "UserTypeId" => $UserContactMapping->UserTypeId,
                                "Status" => $UserContactMapping->Status,
                                "IndustryRoleId" => $UserContactMapping->IndustryRoleId,
                                "InvestorTypeId" => $UserContactMapping->InvestorTypeId,
                                "BrokerTypeId" => $UserContactMapping->BrokerTypeId,
                                "SubscriptionTypeId" => $UserContactMapping->SubscriptionTypeId,
                                "ConfigurationId" => $UserContactMapping->ConfigurationId,
                                "CreatedOn" => $UserContactMapping->CreatedOn,
                                "CreatedBy" => $UserContactMapping->UserId
                            ));
                            $UserContactMappingHistory->save();

                            $userVerification->save();
                            $userVerificationHistory->save();
                            $userDatas = [];
                            $isDashboardUser = false;

                            $databaseConnection = new DatabaseConnection();
                            $configurations = $databaseConnection->getConfiguration();

                            $email = new Email();
                            $to = array(
                                array(
                                    'email' => $user->Email,
                                    'name' => $user->FirstName.' '.$user->LastName
                                ),
                            );
                            $params = env("LOGIN_ACTION");
                            $loginPopup = array(
                                'user_id' => "",
                                'verification_code' =>"",
                                'username' => "",
                                "password" => "",
                                'dmaction' => 'login'
                            );
                            $link = $configurations->ClientServerProtocol.$configurations->SiteUrl.'?dmaction='.base64_encode(json_encode($loginPopup));
                            $subject = "Thank you for completing your registration successfully";

                            $content = "<p>Thank you for completing your registration with {$configurations->SiteName}. Please click the link below to login to your account.<p>";
                            $content .= "<p><a href=".$link." target='_blank'>Login</a></p>";
                            $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                            $email->sendEmail( $subject, $to, $message );
                        } else {
                            $email = new Email();
                            $to = array(
                                array(
                                    'email' => $user->Email,
                                    'name' => $user->FirstName.' '.$user->LastName
                                ),
                            );
                            $subject = "Failure of registration";
                            $content = "<p>Your registration has not been completed, please contact to administrator.<p>";
                            $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                            $email->sendEmail( $subject, $to, $message );
                            return response()->json([
                                'status' => 'failed',
                                'message' => 'This email verification token is invalid.',
                                'errors' => [],
                                'data' => []
                            ], 200);
                        }
                    } else {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'This email verification token is invalid.',
                            'errors' => [],
                            'data' => []
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'This email verification token is expired.',
                        'expired' => true,
                        'errors' => [],
                        'data' => []
                    ], 200);
                }
            }
            $user = User::where('Email', $request->email)->where("IsSuperAuthorizedAccount", '!=', 1)
            ->first();

            $user->Password = md5($request->password);
            $user->save();
        }
        
        $userValid      = false;
        $forgotPassword = false;

        $databaseConnection = new DatabaseConnection();
        $configurations     = $databaseConnection->getConfiguration();
        
        $passwordHash = new PasswordHash(8, TRUE);
        
        
        $user = User::query();
        $user->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        //$user->leftJoin('ContactAccountStatus', 'ContactAccountStatus.UserId', '=', 'Users.Id');

        $user->leftJoin('ContactAccountStatus', function ($join) use ($configurations)
            {
                $join->on('ContactAccountStatus.UserId', '=', 'Users.Id');
                $join->on('ContactAccountStatus.ConfigurationId','=',DB::raw($configurations->ConfigurationId));
            });

        $user->where('Users.Email', $request->email);
        $user->whereIn('UserContactMapping.Status', array('1','2'));
        $user->where(function($b) use ($request){
            $b->where('Users.IsSuperAuthorizedAccount', '!=',1)
                ->orWhereNull('Users.IsSuperAuthorizedAccount');
        });
        $user->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $user->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId","ContactAccountStatus.status AS ContactStatus"]);
        $user = $user->first();
        if(!empty($user)) {
            if(isset($request->social_login) && $request->social_login != ''){
                $userValid = true;
            }else{

                $user1 = User::query();
                $user1->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
                $user1->where('Users.Email', $request->email);
                $user1->where('Password', md5($request->password));
                $user1->where(function($b) use ($request){
                    $b->where('Users.IsSuperAuthorizedAccount', '!=',1)
                        ->orWhereNull('Users.IsSuperAuthorizedAccount');
                });
                $user1->orderBy('UserContactMapping.UserTypeId', 'DESC');
                $user1->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
                $user1 = $user1->first();
                if(empty($user1)) {
                    $ds = $passwordHash->CheckPassword($request->password, $user->Password);
                    if($ds == 1) {
                        $userValid = true;
                    } else {
                        $userValid = false;
                    }
                } else {
                    $userValid = true;
                }
            }

        }

        if(!$userValid && (isset($request->social_login) && $request->social_login != '') ){
            
        	$IsSocial         = 1;
            $SocialMediaId    = 2;
            
            try{
                $request->social = 1;
                $userSaved  = new User();
                $user       = $userSaved->addUpdateUser($request);
                if($user){
                    if(isset($request->LinkedinImage) && $request->LinkedinImage != '')
                    {
                        $userSaved->LinkedInImageUpload($request,$user);
                    }
                    $userAddresses      = new UserAddressDetails();
                    $userAddresses->addUpdateAddress($request,$user->Id);
                    $userVerification   = new UserVerification();
                    $userVerification->addUpdateUserVerification($request,$user->Id);
                    
                    $UserAccountHistory   = new UserAccountHistory();
                    $UserAccountHistory->addUpdateUserAccountHistory($request,$user->Id,'Account Created');
    

                    $user = User::where('Id', $user->Id)->first();
                    if( $user->ProfileImage != '' && $user->ProfileImage != null) {
                        $user->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$user->ProfileImage;
                        $user->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$user->ProfileImage;
                    }
                    return response()->json([
                        'status' 	=> 'success',
                        'errors' 	=> [],
                        'data' 		=> $user,
                        'user_id'	=> $user->Id
                    ]);
    
                }else{
                    return response()->json([
                        'status' 	=> 'failed',
                        'message' 	=> 'Some things went wrong please try again later.',
                        'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
                        'data' 		=> []
                    ], 404);
                }
            }
            catch(\Exception $e){
                return response()->json([
                    'status' 	=> 'failed',
                    'message' 	=> $e,
                    'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
                    'data' 		=> []
                ], 404);
            }
	       	
        }
        
        if($userValid) {
        //if(!empty ( $user ) ) {

            $userAddressDetails = UserAddressDetails::where('UserId', $user->Id)->first();
            $userVerification   = UserVerification::where('UserId', $user->Id)
                ->first();
            
            if( (!empty($userVerification) && $userVerification != null) && ( $userVerification->IsEmailVerified != 1 && $userVerification->IsMobileVerified != 1 ) ) {
                if( $user->ProfileImage != '' && $user->ProfileImage != null) {
                    $user->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$user->ProfileImage;
                    $user->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$user->ProfileImage;
                }
                return response()->json([
                    'status' => 'success',
                    'message' => 'Please verify your account',
                    'errors' => [],
                    'data' => [
                        'user' => array(
                            'data' => $user,
                            'Id' => $user->Id,
                            'firstName' => $user->FirstName,
                            'LastName' => $user->LastName,
                            'Email' => $user->Email,
                            'get_user_address_details_relation' => array('MobilePhone'=>$userAddressDetails->MobilePhone),
                            'IsAccountVerified' => false,
                            'IsRegistrationCompleted'  => $user->IsRegistrationCompleted,
                            'IsSocial'  => $user->IsSocial,
                        )
                    ]
                ], 200);
            }

            if ($user->Status == 2) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Your account is locked. Please contact the website administrator or another member of the team.',
                    'errors' => [],
                    'data' => []
                ], 200);
            }

            if ($user->Status == 3) {
                if( $user->ProfileImage != '' && $user->ProfileImage != null) {
                    $user->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$user->ProfileImage;
                    $user->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$user->ProfileImage;
                }
                return response()->json([
                    'status' => 'success',
                    'message' => 'Your account is deleted. Please contact administrator',
                    'errors' => [],
                    'data' => [
                        'user' => array(
                            'data' => $user,
                            'Id' => $user->Id,
                            'FirstName' => $user->FirstName,
                            'LastName' => $user->LastName,
                            'Email' => $user->Email,
                            'IsRegistrationCompleted'  => $user->IsRegistrationCompleted,
                            'get_user_address_details_relation' => array('MobilePhone'=>$userAddressDetails->MobilePhone),
                            'IsAccountVerified' => false,
                            'IsSocial'  => $user->IsSocial,
                        )
                    ]
                ], 200);
            }

            if($user->Status == 1 && ($user->ContactStatus == 1 || $user->ContactStatus == null )  ) {
                $isNextUpdateDate = false;
                $userDatas = [];

                if($user->NextUpdateDate <= date('Y-m-d H:i:s')  || $user->NextUpdateDate == null) {
                    $isNextUpdateDate = true;
                    /**
                     * Current user
                     * start
                     */

                    $query = User::query();
                    $query->with('getAcquisitioncriteriaContactRelation');
                    $query->with('getUserAddressDetailsRelation');
                    $query->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
                    $query->where('Users.Id', '=', $user->Id);
                    $query->orderBy('UserContactMapping.UserTypeId', 'DESC');
                    $query->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
                    $userData = $query->get();

                    if($userData[0]->CompanyId != '' && $userData[0]->CompanyId != null ) {
                        $company = Companies::where('Id', $userData[0]->CompanyId)->first();
                        if(!empty($company)) {
                            $userData[0]->CompanyName = $company->CompanyName;
                        } else {
                            $userData[0]->CompanyName = "";
                        }
                    } else {
                        $userData[0]->CompanyName = "";
                    }

                    if( !empty($userData[0]->getAcquisitioncriteriaContactRelation)) {
                        $ids1 = [];
                        $ids2 = [];
                        $commonId = [];
                        foreach($userData[0]->getAcquisitioncriteriaContactRelation as $key => $value ) {
                            if( $value->AcquisitionCriteriaSubTypeId != null) {
                                $commonId[] = $value->AcquisitionCriteriaTypeId;
                                $ids1[$value->AcquisitionCriteriaTypeId][] = $value->AcquisitionCriteriaSubTypeId;
                            }
                            $ids2[] = $value->AcquisitionCriteriaTypeId;
                        }

                        unset($userData[0]->getAcquisitioncriteriaContactRelation);
                        $ids2 = array_unique($ids2);
                        $ids2 = array_diff($ids2, array_unique($commonId));

                        $userData[0]->get_acquisitioncriteria_contact_relation = array(
                            'acquisitionCriteriaSubType' =>$ids1,
                            'acquisitionCriteriaType'=> $ids2
                        );
                    }

                    $userData[0]->IsAccountVerified = true;
                    $userDatas = $userData[0];

                    if($user->IsContactCreatedByDashboard == 1) {
                        if($user->ExchangeStatusId == null ) {
                            $userData[0]->isNextUpdateDate = true;
                        } else {
                            $userData[0]->isNextUpdateDate = false;
                        }

                        if($user->NextUpdateDate == null ) {
                            $userData[0]->IsupdateDashbord = true;
                        } else {
                            $userData[0]->IsupdateDashbord = false;
                        }

                        $userData[0]->IsContactCreatedByDashboard = true;
                    } else {
                        $userData[0]->IsupdateDashbord = false;
                        $userData[0]->isNextUpdateDate = $isNextUpdateDate;
                        $userData[0]->IsContactCreatedByDashboard = false;
                        $user->ExchangeStatusId = null;
                        $user->save();
                    }

                    if( $userData[0]->ProfileImage != '' && $userData[0]->ProfileImage != null) {
                        $userData[0]->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$userData[0]->ProfileImage;
                        $userData[0]->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$userData[0]->ProfileImage;
                    }

                    /**
                     * Current user data
                     * end
                     */
                } else {
                    
                    $userDatas = array(
                        'Id' => $user->Id,
                        'data' => $user,
                        'FirstName' => $user->FirstName,
                        'LastName' => $user->LastName,
                        'Email' => $user->Email,
                        'get_user_address_details_relation' => array('MobilePhone'=>!empty($userAddressDetails) ? $userAddressDetails->MobilePhone : ''),
                        'IsAccountVerified' => true,
                        'IsRegistrationCompleted'  => $user->IsRegistrationCompleted,
                        'IsupdateDashbord' => false,
                        'IsSocial'  => $user->IsSocial,
                    );
                }

                $tokenResult = $user->createToken('Personal Access Token');
                
                $user->LastSiteLoginTime = date('Y-m-d H:i:s');
                //$user->timestamps = false;
                $user->save();
                $token = $tokenResult->token;
                
                if ($request->remember_me) {
                    $token->expires_at = Carbon::now()->addHour(24);
                } else {
                    $token->expires_at = Carbon::now()->addHour(24);
                }

                $token->save();
                
                $userLoginHistory = new UserLoginHistory([
                    "SessionToken"  => "",
                    "UserId"        => $user->Id,
                    "TimeLogin"     => date('Y-m-d H:i:s'),
                    "TimeLastSeen"  => date('Y-m-d H:i:s'),
                    "IpAddress"     => $user_ip_address,
                    "Device"        => $this->getDevice(),
                    "IsLogout"      => 1,
                    "ConfigurationId" => $configurations->ConfigurationId,
                ]);
                $userLoginHistory->save();
                
                $userAccountHistory = new UserAccountHistory([
                    "UserId" => $user->Id,
                    "Description" => 'Login',
                    "ModuleType" => 1,
                    "Ip" => $user_ip_address,
                    "BrowserName" => $user_agent,
                    "CreatedBy" => $user->Id,
                    "CreatedDate" => date('Y-m-d H:i:s'),
                    "ConfigurationId" => $configurations->ConfigurationId,
                    "HostedConfigurationId" => $user->ConfigurationId
                ]);
                $userAccountHistory->save();
                
                $pressReleaseData = [];
                if( ( isset($request->press_release) && $request->press_release == "press_release" ) && (isset($request->property_id) && is_numeric($request->property_id))) {

                    $databaseConnection = new DatabaseConnection();
                    $configurations = $databaseConnection->getConfiguration();

                    $property = Property::query();
                    $property->leftJoin('PropertyPressRelease', 'PropertyPressRelease.PropertyId', '=', 'Property.Id');
                    $property->where('Property.Id', $request->property_id);
                    $property->select(['Property.Id', 'PropertyPressRelease.IsPressReleaseFile', 'PropertyPressRelease.PressReleaseFile', 'PropertyPressRelease.PressReleaseLink']);

                    $pressRelease = env('PROPERTY_PRESS_DOCUMENT_URL', '');
                    
                    $property = $property->first();
                    if(!empty($property)) {

                        $pressreleaseHistoryArray = [];

                        $pressreleaseHistoryArray['PropertyId'] = $request->property_id;
                        $pressreleaseHistoryArray['UserId'] = $user->Id;
                        if ($property->IsPressReleaseFile == 1) {
                            $pressreleaseHistoryArray['FileName'] = $property->PressReleaseFile;    
                        } else {
                            $pressreleaseHistoryArray['FileName'] = $property->PressReleaseLink;
                        }
                        $pressreleaseHistoryArray['IP'] = $user_ip_address;
                        $pressreleaseHistoryArray['CreateDate'] = date('Y-m-d H:i:s');
                        $pressreleaseHistoryArray['ConfigurationId'] = $configurations->ConfigurationId;

                        $pressreleaseHistory = new PressreleaseHistory($pressreleaseHistoryArray);

                        $pressreleaseHistory->save();

                        $pressReleaseData = array(
                            'PropertyId'         => $property->Id,
                            'IsPressReleaseFile' =>$property->IsPressReleaseFile,
                            'PressReleaseFile'   => $pressRelease.'/'.$property->PressReleaseFile,
                            'FileName' => $property->PressReleaseFile,
                            'PressReleaseLink'   =>$property->PressReleaseLink);
                    }
                }

                $isNextUpdateDate = false;
                if($user->NextUpdateDate <= date('Y-m-d H:i:s') || $user->NextUpdateDate == null) {
                    $isNextUpdateDate = true;
                }
                /**
                 *
                 * First time user entry stored in mySQL
                 *
                */ 
                

                
                return response()->json([
                    'status'=>'success',
                    'message' => 'Successfully user loged in!',
                    'errors' => [],
                    'data' => [
                        'user' => $userDatas
                    ],
                    'forgot_password' => $forgotPassword,
                    'isNextUpdateDate' => $isNextUpdateDate,
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'press_release' => $pressReleaseData,
                    'expires_at' => Carbon::parse($tokenResult->token->expires_at,
                    )->toDateTimeString()
                ]);
            } else if($user->ContactStatus == 2){
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Your account is locked. Please contact the website administrator or another member of the team.',
                    'errors' => [],
                    'data' => []
                ], 200);
            } else {


                return response()->json([
                    'status' => 'success',
                    'message' => 'Please verify your account',
                    'errors' => [],
                    'data' => [
                        'user' => array(
                            'Id' => $user->Id,
                            'FirstName' => $user->FirstName,
                            'LastName' => $user->LastName,
                            'Email' => $user->Email,
                            'get_user_address_details_relation' => array('MobilePhone'=>$userAddressDetails->MobilePhone),
                            'IsAccountVerified' => false,
                            'IsRegistrationCompleted'  => $user->IsRegistrationCompleted,
                            'IsSocial'  => $user->IsSocial,
                        )
                    ]
                ], 200);
            }

        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Email Address or Password is incorrect.',
                'errors' => [],
                'data' => [
                    'user' => 'Email Address or Password is incorrect.'
                ]
            ], 401);
        }
        /**
         * Check verified date otp is valid 24 Hours
         */
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function newUserRegister(Request $request){
        $databaseConnection = new DatabaseConnection();
        $currentConnection 	= $databaseConnection->getConnectionName();
        $configurations 	= $databaseConnection->getConfiguration();
        

        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        $azurePath = env('AZURE_STORAGE_URL');

        $step1 = $request->validate([
            'email' => ['required', new EmailUnique(""),new EmailVerificationUnique("")],
        ]);

        try{
            
            $userSaved  = new User();
	       	$user       = $userSaved->addUpdateUser($request);
	    	if($user){

                if(isset($request->LinkedinImage) && $request->LinkedinImage != '')
                {
                    $userSaved->LinkedInImageUpload($request,$user);
                }
                
                $userAddresses      = new UserAddressDetails();
                $userAddresses->addUpdateAddress($request,$user->Id);
                $userVerification   = new UserVerification();
                $userVerification->addUpdateUserVerification($request,$user->Id);
                
                $UserAccountHistory   = new UserAccountHistory();
                $UserAccountHistory->addUpdateUserAccountHistory($request,$user->Id,'Account Created');

                $user = User::where('Id', $user->Id)->first();
                if( $user->ProfileImage != '' && $user->ProfileImage != null) {
                    $user->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$user->ProfileImage;
                    $user->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$user->ProfileImage;
                }
		        return response()->json([
		            'status' 	=> 'success',
		            'errors' 	=> [],
		            'data' 		=> $user,
		            'user_id'	=> $user->Id
		        ]);

	        }else{
	        	return response()->json([
	                'status' 	=> 'failed',
	                'message' 	=> 'Some things went wrong please try again later.',
	                'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
	                'data' 		=> []
	            ], 404);
	        }
	    }
	    catch(\Exception $e){
	    	return response()->json([
                'status' 	=> 'failed',
                'message' 	=> $e,
                'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
                'data' 		=> []
            ], 404);
	    }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userRegister(Request $request){
        
        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();

        $profileContainerName   = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');

        $azurePath              = env('AZURE_STORAGE_URL');
        $confId                 = $configurations->ConfigurationId;
        
        $user_id = $request->user_id;
        if($request->step == 1){
            $step1 = $request->validate([
                'firstName' => 'required|string',
                'lastName' => 'required|string',
                'email' => ['required', new EmailUnique($user_id),new EmailVerificationUnique("")],
                'street' => 'required|string',
                'country' => 'required|string',
                'city' => 'required|string',
                'zipcode' => 'required|string',
                'cell_phone' => 'required|string',
                'i_am' => 'required|string',
            ]);
            if ($file = $request->file('avatar')) {
                $request->validate([
                    'avatar' => 'mimes:jpg,png,jpeg|max:2048',
                ]);
            }

            if($step1) {
                return response()->json([
                    'status'=>'success',
                    'message' => "Step ".$request->step." is validate",
                    'errors' => [],
                    'data' => []
                ], 200);
            }
        } elseif ($request->step == 2) {
            $step2 = $request->validate([
                'exchange_status' => 'required|string',
            ]);
            if($step2) {
                $request->validate([
                    'firstName'        => 'required|string',
                    'lastName'         => 'required|string',
                    'email' => ['required', new EmailUnique($user_id),new EmailVerificationUnique("")],
                    'street'            => 'required|string',
                    'country'           => 'required|string',
                    'city'              => 'required|string',
                    'zipcode'           => 'required|string',
                    'cell_phone'        => 'required|string',
                    'i_am'              => 'required|string',
                    'exchange_status'   =>'required|string',
                ]);
                try{
                    $userSaved  = new User();
                    
                    $request->IsRegistrationCompleted = 1;
                    $user       = $userSaved->addUpdateUser($request,$user_id);
                    
                    if($user){
                        
                        $userSaved->addUserImage($request->profile_avatar,$user);
                        
                        $userAddresses      = new UserAddressDetails();
                        $userAddresses->addUpdateAddress($request,$user->Id);
                        
                        $userVerification   = new UserVerification();
                        $userVerification->addUpdateUserVerification($request,$user->Id);

                        $PropetyType = json_decode($request->PropetyType);
                        $ContactRelation = new AcquisitioncriteriaContactRelation();
                        
                        $ContactRelation->addPropetyType($PropetyType,$user);
                       
                        $ContactRelation->addAllCriteriaType($request->PeferredMarketType,$user);
                       
                        $ContactRelation->addAllCriteriaType($request->InvestmentStraragy,$user);
                        $ContactRelation->addAllCriteriaType($request->ReturnMetrics,$user);
                        $ContactRelation->addAllCriteriaType($request->PrefferedDealSize,$user);
                       
                        $verificationDetails = UserVerification::where('UserId', $user->Id)->first();

                        $email = new Email();
                        $to = array(
                            array(
                                'email' => $user->Email,
                                'name' => $user->FirstName.' '.$user->LastName
                            ),
                        );

                        $verificationArray = array(
                            'user_id'           => $user->Id,
                            'verification_code' => $verificationDetails->VerificationId,
                            'username'          => $user->Email,
                            "password"          => "",
                            'dmaction'          => 'wordpress_verification'
                        );


                        $verifyuser = $configurations->ClientServerProtocol.$configurations->SiteUrl.'?datumpara='.base64_encode(json_encode($verificationArray));
                        
                        $subject = "Registration Successful | ".$configurations->SiteName;
                        $content = "<p>Thank you for creating an account with {$configurations->SiteName}. Please click the link below to complete your registration.</p>";
                        $content .= "<p><a href=".$verifyuser." target='_blank'>Complete your registration</a></p>";
                        $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);

                        $email->sendEmail( $subject, $to, $message );

                        $userAddresses = UserAddressDetails::where('UserId', $user->Id)->first();
                        $user->get_user_address_details_relation = $userAddresses;
                        

                        if( $user->ProfileImage != '' && $user->ProfileImage != null) {
                            $user->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$user->ProfileImage;
                            $user->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$user->ProfileImage;
                        }

                        return response()->json([
                            'status'=>'success',
                            'message' => 'Successfully created user!',
                            'errors' => [],
                            'data' => $user
                        ], 200);

                    } else {
                        return response()->json([
                            'status'=>'failed',
                            'message' => 'User is not inserted please try again',
                            'errors' => [],
                            'data' => $user
                        ], 200);
                    }
                }
                catch(\Exception $e){
                    return response()->json([
                        'status' 	=> 'failed',
                        'message' 	=> $e,
                        'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
                        'data' 		=> []
                    ], 404);
                }
            }
        }
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        $azurePath = env('AZURE_STORAGE_URL');

        $query = User::query();
        $query->with('getAcquisitioncriteriaContactRelation');
        $query->with('getUserAddressDetailsRelation');
        $query->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $query->where('Users.Id', '=', $request->user()->Id);
        $query->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $query->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
        $data = $query->get();

        if($data[0]->CompanyId != '' && $data[0]->CompanyId != null ) {
            $company = Companies::where('Id', $data[0]->CompanyId)->first();
            if(!empty($company)) {
                $data[0]->CompanyName = $company->CompanyName;
            } else {
                $data[0]->CompanyName = "";
            }
        } else {
            $data[0]->CompanyName = "";
        }
        if( !empty($data[0]->getAcquisitioncriteriaContactRelation)) {
            $ids1 = [];
            $ids2 = [];
            $commonId = [];
            foreach($data[0]->getAcquisitioncriteriaContactRelation as $key => $value ) {
                if( $value->AcquisitionCriteriaSubTypeId != null) {
                    $commonId[] = $value->AcquisitionCriteriaTypeId;
                    $ids1[$value->AcquisitionCriteriaTypeId][] = $value->AcquisitionCriteriaSubTypeId;
                }
                $ids2[] = $value->AcquisitionCriteriaTypeId;
            }

            unset($data[0]->getAcquisitioncriteriaContactRelation);
            $ids2 = array_unique($ids2);
            $ids2 = array_diff($ids2, array_unique($commonId));

            $data[0]->get_acquisitioncriteria_contact_relation = array(
                'acquisitionCriteriaSubType' =>$ids1,
                'acquisitionCriteriaType'=> $ids2
            );
        }
        $isNextUpdateDate = false;
        if($data[0]->NextUpdateDate <= date('Y-m-d H:i:s')  || $data[0]->NextUpdateDate == null) {
            if($data[0]->IsContactCreatedByDashboard == 1) {
                if($data[0]->ExchangeStatusId == null ) {
                    $data[0]->isNextUpdateDate = true;
                } else {
                    $data[0]->isNextUpdateDate = false;
                }

                if($data[0]->NextUpdateDate == null ) {
                    $data[0]->IsupdateDashbord = true;
                } else {
                    $data[0]->IsupdateDashbord = false;
                }
                $data[0]->IsContactCreatedByDashboard = true;
            } else {
                if($data[0]->ExchangeStatusId == null ) {
                    $data[0]->isNextUpdateDate = true;
                    $isNextUpdateDate = true;
                } else {
                    $isNextUpdateDate = false;
                    $data[0]->isNextUpdateDate = false;
                }
                
                $data[0]->isNextUpdateDate = $isNextUpdateDate;
                $data[0]->IsContactCreatedByDashboard = false;
                $userUpdate = User::where('Id', $data[0]->Id)->first();
                $userUpdate->ExchangeStatusId = null;
                $userUpdate->save();
            }
        }
        $data[0]->isNextUpdateDate = $isNextUpdateDate;
        if( $data[0]->ProfileImage != '' && $data[0]->ProfileImage != null) {
            $data[0]->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$data[0]->ProfileImage;
            $data[0]->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$data[0]->ProfileImage;
        }

        return response()->json($data[0]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request) {
        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        
        $azurePath = env('AZURE_STORAGE_URL');
        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();
        $confId = $configurations->ConfigurationId;
        if($request->step == 1){
            
            $currentUserIdVal = $request->user()->Id;
            
            $step1 = $request->validate([
                'firstName' => 'required|string',
                'lastName' => 'required|string',
                'email' => ['required', new EmailUnique($request->user()->Id),new EmailVerificationUnique("")],
                'street' => 'required|string',
                'country' => 'required|string',
                'city' => 'required|string',
                'zipcode' => 'required|string',
                'cell_phone' => 'required|string',
                'i_am' => 'required|string',
            ]);
            if ($file = $request->file('avatar')) {
                $request->validate([
                    'avatar' => 'mimes:jpg,png,jpeg|max:2048',
                ]);
            }

            if($step1) {
                return response()->json([
                    'status'=>'success',
                    'message' => "Step ".$request->step." is validate",
                    'errors' => [],
                    'data' => []
                ], 200);
            }
        } elseif ($request->step == 2) {
            $step2 = $request->validate([
                'exchange_status' => 'required|string',
            ]);
            if($step2) {
                $currentUserIdVal = $request->user()->Id;
                $request->validate([
                    'firstName' => 'required|string',
                    'lastName' => 'required|string',
                    'email' => [
                        'required', 'Email',new EmailUnique($request->user()->Id),new EmailVerificationUnique("")
                    ],
                    'street' => 'required|string',
                    'country' => 'required|string',
                    'city' => 'required|string',
                    'zipcode' => 'required|string',
                    'cell_phone' => 'required|string',
                    'i_am' => 'required|string',
                    'exchange_status' =>'required|string',
                ]);

                if ($file = $request->file('avatar')) {
                    $request->validate([
                        'avatar' => 'mimes:jpg,png,jpeg|max:2048',
                    ]);
                }
                try{

                    $userSaved  = new User();
                    $user       = $userSaved->addUpdateUser($request,$request->user()->Id);

                    if($user){
                        $userSaved->addUserImage($request->profile_avatar,$user);
                       
                        $userAddresses      = new UserAddressDetails();
                        $userAddresses->addUpdateAddress($request,$user->Id);
        
                        $UserAccountHistory   = new UserAccountHistory();
                        $UserAccountHistory->addUpdateUserAccountHistory($request,$user->Id,'Update Profile');
                        

                        $PropetyType        = json_decode($request->PropetyType);
                        $ContactRelation    = new AcquisitioncriteriaContactRelation();
                        $acquMainTypeIdMain = AcquisitioncriteriaContactRelation::where('UserId', $user->Id)->update(['Status' => 0]);

                        $ContactRelation->updatePropetyType($PropetyType,$user);
                       
                        $ContactRelation->addAllUpdateCriteriaType($request->PeferredMarketType,$user);
                       
                        $ContactRelation->addAllUpdateCriteriaType($request->InvestmentStraragy,$user);
                        $ContactRelation->addAllUpdateCriteriaType($request->ReturnMetrics,$user);
                        $ContactRelation->addAllUpdateCriteriaType($request->PrefferedDealSize,$user);

                        $users = User::where('Id', $user->Id)->first();

                        $UserAccessController = new UserAccessController();
                        $InsightAccessController = new InsightAccessController();
                        return response()->json([
                            'status' 	=> 'success',
                            'errors' 	=> [],
                            'message' => 'User has been successfully update',
                            'data' 		=> User::where('Id', $user->Id)->first(),
                            'user_id'	=> $user->Id,
                            'userAccess'   => $UserAccessController->checkUserPageAccessData(),
                            'insightsAccess'   => $InsightAccessController->checkUserPageAccessData(),
                            'ProfileImage'    => $azurePath.'/'.$profileContainerSMName.'/'.$users->ProfileImage
                        ]);
        
                    }else{
                        return response()->json([
                            'status' 	=> 'failed',
                            'message' 	=> 'Some things went wrong please try again later.',
                            'errors' 	=> ['confirm_password' => 'Some things went wrong please try again later.'],
                            'data' 		=> []
                        ], 404);
                    }
                }
                catch(\Exception $e){
                    return response()->json([
                        'status' 	=> 'failed',
                        'message' 	=> $e,
                        'errors' 	=> ['confirm_password' => $e],
                        'data' 		=> []
                    ], 404);
                }
            }
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();

        $user_ip_address = $request->user_ip;
        $user_agent = $request->user_agent;
        
        $user       = User::where('Users.Id', $request->user()->Id)->first();
        $userDT = User::where('Users.Id', $request->user()->Id)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->orderBy('UserContactMapping.UserTypeId', 'DESC')->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.ConfigurationId"])->first();
        $hostedConfId = null;

        if ( !empty($userDT)) {
            $hostedConfId = $userDT->ConfigurationId;
        } else {
            $hostedConfId = $configurations->ConfigurationId;
        }

        $userAccountHistory = new UserAccountHistory([
            "UserId" => $request->user()->Id,
            "Description" => 'Logout',
            "ModuleType" => 1,
            "Ip" => $user_ip_address,
            "BrowserName" => $user_agent,
            "CreatedBy" => $request->user()->Id,
            "CreatedDate" => date('Y-m-d H:i:s'),
            "ConfigurationId" => $configurations->ConfigurationId,
            "HostedConfigurationId" => $hostedConfId
        ]);
        $userAccountHistory->save();

        $request->user()->token()->revoke();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
            'errors' => [],
            'data' => []
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword( Request $request ) {
        $request->validate([
            'email' => 'required|string|email'
        ]);
        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();
        
        $user = User::where('Email', $request->email)->first();

        if ( $user ) {
            $oneTimePassword = sprintf("%06d", mt_rand(100000,999999));
            $passwordReset = PasswordReset::where('UserId', $user->Id)->first();
            if(!empty($passwordReset) && $passwordReset != null ) {
                $valid = strtotime($passwordReset->OneTimePasswordExpiryDate) + (30 * 60);
                if(strtotime("now") > $valid) {
                    $passwordReset->Attempt = 0;
                    $passwordReset->ResendAttempts = 0;
                    $passwordReset->save();
                }

                $userVerification = UserVerification::where('UserId', $user->Id)->first();
                $verifiedUserFlag = false;
                if(!empty($userVerification) && $userVerification->IsEmailVerified == 1) {
                    $verifiedUserFlag = true;
                }
                if ( !$verifiedUserFlag ) {
                    if( $passwordReset->Attempt >= 3 ) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Your account has been temporarily locked, please try again after 30 minutes.',
                            'errors' => [],
                            'data' => []
                        ], 404);
                    }
                }

                $passwordReset->IsForgot = 1;
                $passwordReset->OneTimePasswordExpiryDate = date("Y-m-d H:i:s");
                $passwordReset->OneTimePassword = $oneTimePassword;
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = 0;
                $passwordReset->save();
            } else {
                $passwordReset = new PasswordReset();
                $passwordReset->UserId = $user->Id;
                $passwordReset->IsForgot = 1;
                $passwordReset->OneTimePasswordExpiryDate = date("Y-m-d H:i:s");
                $passwordReset->OneTimePassword = $oneTimePassword;
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = 0;
                $passwordReset->save();
            }
            $email = new Email();
            $to = array(
                array(
                    'email' => $user->Email,
                    'name' => $user->FirstName.' '.$user->LastName
                ),
            );
            $subject =  "One-Time Password for Forgot Password Request | ".$configurations->SiteName;
            $content =  "<p>To authenticate, please use the following One-Time Password:</p>";
            $content .=  "<p>{$oneTimePassword}</p>";
            $content .= '<p>Thank you,</p>';
            $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);

            $status = $email->sendEmail( $subject, $to, $message );
            
            return response()->json([
                'status' => 'success',
                'message' => "We've sent a one-time password to {$user->Email}. Please enter it below.",
                'errors' => [],
                'data' => array( 'Id' => $user->Id, 'Email' => $user->Email ),
                'step' => 1,
                'email' => $user->Email
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'An account with that Email Address does not exist.',
                'errors' => [],
                'data' => []
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOnetimePassword ( Request $request ) {
        $request->validate([
            'onetime_password' => 'required|integer',
            'user_id' => 'required|integer'
        ]);

        $user = User::where('Id', $request->user_id)->first();
        $passwordReset = PasswordReset::where('UserId', $request->user_id)->first();

        if( ( !empty($user) && $user != null ) && ( !empty( $passwordReset ) && $passwordReset != null ) ) {
            
            $valid = strtotime($passwordReset->OneTimePasswordExpiryDate) + (30 * 60);
            if(strtotime("now") > $valid) {
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = 0;
                $passwordReset->save();
            }

            if ($passwordReset->OneTimePassword != $request->onetime_password) {
                $passwordReset->Attempt = ( $passwordReset->Attempt + 1 );
                $passwordReset->save();

                $userVerification = UserVerification::where('UserId', $user->Id)->first();
                $verifiedUserFlag = false;
                if(!empty($userVerification) && $userVerification->IsEmailVerified == 1) {
                    $verifiedUserFlag = true;
                }
                if ( !$verifiedUserFlag ) {
                    if( $passwordReset->Attempt >= 3 ) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Your account has been temporarily locked, please try again after 30 minutes.',
                            'errors' => [],
                            'data' => [],
                            'remove_resend' => true,
                        ], 404);
                    }       
                }
                return response()->json([
                    'status' => 'failed',
                    'message' => 'One-Time Password is incorrect.',
                    'errors' => [],
                    'data' => [],
                    'remove_resend' => false,
                ], 404);
            }
            
            $valid = strtotime($passwordReset->OneTimePasswordExpiryDate) + (30 * 60);

            if(strtotime("now") > $valid) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'One-Time Password has expired.',
                    'errors' => [],
                    'data' => [],
                    'remove_resend' => false,
                ], 404);
            }

            if(strtotime("now") > $valid) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'One-Time Password has expired.',
                    'errors' => [],
                    'data' => array( 'Id' => $user->Id ),
                    'remove_resend' => false,
                ], 404);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => "Authentication successfully",
                    'errors' => [],
                    'data' => array( 'Id' => $user->Id )
                ], 200);
            }
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'We can\'t find a user with that user id.',
                'errors' => [],
                'data' => []
            ], 404);
        }
    } 

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function passwordReset( Request $request ) {
        $request->validate([
            'user_id' => 'required|integer',
            'password' => 'required|min:8',
            'reenter_password' => 'required|same:password'
        ]);

        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();

        $user_ip_address    = $request->user_ip;
        $user = User::where('Id', $request->user_id)->first();
        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();
        $passwordReset      = PasswordReset::where('UserId', $request->user_id)->first();
        
        if( ( !empty($user) && $user != null )) {

            $valid = strtotime($passwordReset->OneTimePasswordExpiryDate) + (30 * 60);
            if(strtotime("now") > $valid) {
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = 0;
                $passwordReset->save();
            }

            $userVerification = UserVerification::where('UserId', $user->Id)->first();
            $verifiedUserFlag = false;
            if(!empty($userVerification) && $userVerification->IsEmailVerified == 1) {
                $verifiedUserFlag = true;
            }
            if ( !$verifiedUserFlag ) {
                if( $passwordReset->Attempt >= 3 ) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Your account has been temporarily locked, please try again after 30 minutes.',
                        'errors' => [],
                        'data' => []
                    ], 404);
                }    
            }
            $valid = strtotime($passwordReset->OneTimePasswordExpiryDate) + (30 * 60);
            if(strtotime("now") > $valid) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'One-Time Password has expired.',
                    'errors' => [],
                    'data' => []
                ], 404);
            } else {
                
                if( $user->Password != md5( $request->password ) ) {
                    $user->Password = md5($request->password);
                    $user->save();

                    $userVerification = UserVerification::where('UserId', $user->Id)->first();
            
                    if( (!empty($userVerification) && $userVerification != null) && ( $userVerification->IsEmailVerified != 1 && $userVerification->IsMobileVerified != 1 ) ) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Your password update has been successfully. Please verify login and verify your account.',
                            'errors' => [],
                            'data' => [
                                'user' => array(
                                    'Id' => $user->Id,
                                    'firstName' => $user->FirstName,
                                    'LastName' => $user->LastName,
                                    'Email' => $user->Email,
                                    'IsAccountVerified' => false
                                )
                            ]
                        ], 200);
                    }
                    $userDT = User::where('Users.Id', $user->Id)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->orderBy('UserContactMapping.UserTypeId', 'DESC')->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.ConfigurationId"])->first();

                    if ($userDT->Status == 2) {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Your account is locked. Please contact the website administrator or another member of the team.',
                            'errors' => [],
                            'data' => []
                        ], 200);
                    }

                    if ($userDT->Status == 3) {
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Your account is deleted. Please contact administrator',
                            'errors' => [],
                            'data' => [
                                'user' => array(
                                    'Id' => $user->Id,
                                    'firstName' => $user->FirstName,
                                    'LastName' => $user->LastName,
                                    'Email' => $user->Email,
                                    'IsAccountVerified' => false
                                )
                            ]
                        ], 200);
                    }

                    if($userDT->Status == 1) {
                        $tokenResult = $user->createToken('Personal Access Token');
                        $user->LastSiteLoginTime = date('Y-m-d H:i:s');
                        $token = $tokenResult->token;
                        $token->save();

                        $userLoginHistory = new UserLoginHistory([
                            "SessionToken" => "",
                            "UserId" => $user->Id,
                            "TimeLogin" => date('Y-m-d H:i:s'),
                            "TimeLastSeen" => date('Y-m-d H:i:s'),
                            "IpAddress" => $user_ip_address,
                            "Device" => $this->getDevice(),
                            "ConfigurationId" => $configurations->ConfigurationId,
                            "IsLogout" => 1,
                            "ConfigurationId" => $configurations->ConfigurationId,
                        ]);
                        $userLoginHistory->save();

                        $user_agent = $request->user_agent;
                        $userAccountHistory = new UserAccountHistory([
                            "UserId"        => $user->Id,
                            "Description"   => 'Login',
                            "ModuleType"    => 1,
                            "Ip"            => $user_ip_address,
                            "BrowserName"   => (!empty($user_agent) ? $user_agent : '' ),
                            "CreatedBy"     => $user->Id,
                            "CreatedDate"   => date('Y-m-d H:i:s'),
                            "ConfigurationId" => $configurations->ConfigurationId,
                            "HostedConfigurationId" => $user->ConfigurationId,
                        ]);
                        $userAccountHistory->save();
                        if($user->save()) {
                            $passwordReset->delete();
                            return response()->json([
                                'status' => 'success',
                                'message' => "Password update successfully.",
                                'errors' => [],
                                'data' => array( 'Id' => $user->Id, 'access_token' => $tokenResult->accessToken, 'token_type' => 'Bearer',)
                            ], 200);

                        } else {
                            return response()->json([
                                'status' => 'failed',
                                'message' => "The given data was invalid.",
                                'errors' => [],
                                'data' => []
                            ], 404);
                        }
                    } else {
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Please verify your account',
                            'errors' => [],
                            'data' => [
                                'user' => array(
                                    'Id' => $user->Id,
                                    'firstName' => $user->FirstName,
                                    'LastName' => $user->LastName,
                                    'Email' => $user->Email,
                                    'IsAccountVerified' => false
                                )
                            ]
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'You cannot use a password that you have used in the past.',
                        'errors' => array(
                            'password' => 'You cannot use a password that you have used in the past.'
                        ),
                    ], 422);
                }
            }
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => "We can\'t find a user with that user id.",
                'errors' => [],
                'data' => []
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendOnetimePassword ( Request $request ) {
        $request->validate([
            'user_id' => 'required|integer',
        ]);
        
        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();
        
        $user = User::where('Id', $request->user_id)->where("ConfigurationId", $configurations->ConfigurationId)->first();

        if ( $user ) {
            $oneTimePassword = sprintf("%06d", mt_rand(100000,999999));
            $passwordReset = PasswordReset::where('UserId', $user->Id)->first();

            if(!empty($passwordReset) && $passwordReset != null ) {
                if( $passwordReset->ResendAttempts >= 5 ) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Resend link has been temporarily locked.',
                        'errors' => [],
                        'block' => true,
                        'data' => []
                    ], 404);
                }

                $passwordReset->IsForgot = 1;
                $passwordReset->OneTimePasswordExpiryDate = date("Y-m-d H:i:s");
                $passwordReset->OneTimePassword = $oneTimePassword;
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = $passwordReset->ResendAttempts + 1;
                if($passwordReset->save()) {
                    $email = new Email();
                    $to = array(
                        array(
                            'email' => $user->Email,
                            'name' => $user->FirstName.' '.$user->LastName
                        ),
                    );
                    $subject =  "One-Time Password for Forgot Password Request | ".$configurations->SiteName;
                    $content =  "<p>To authenticate, please use the following One-Time Password:</p>";
                    $content .=  "<p>{$oneTimePassword}</p>";
                    $content .= '<p>Thank you,</p>';
                    $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                    $email->sendEmail( $subject, $to, $message );
                    return response()->json([
                        'status' => 'success',
                        'message' => "We've sent a one-time password to {$user->Email}. Please enter it below.",
                        'errors' => [],
                        'data' => array( 'Id' => $user->Id, 'Email' => $user->Email ),
                        'block' => ( $passwordReset->ResendAttempts == 5) ? true : false,
                        'count' => $passwordReset->ResendAttempts
                    ], 200);
                }

                
            } else {
                $passwordReset = new PasswordReset();
                $passwordReset->UserId = $user->Id;
                $passwordReset->IsForgot = 1;
                $passwordReset->OneTimePasswordExpiryDate = date("Y-m-d H:i:s");
                $passwordReset->OneTimePassword = $oneTimePassword;
                $passwordReset->Attempt = 0;
                $passwordReset->ResendAttempts = 0;
                if($passwordReset->save()) {
                    $email = new Email();
                    $to = array(
                        array(
                            'email' => $user->Email,
                            'name' => $user->FirstName.' '.$user->LastName
                        ),
                    );
                    $subject =  "One-Time Password for Forgot Password Request | ".$configurations->SiteName;
                    $content =  "<p>To authenticate, please use the following One-Time Password:</p>";
                    $content .=  "<p>{$oneTimePassword}</p>";
                    $content .= '<p>Thank you,</p>';
                    $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                    $email->sendEmail( $subject, $to, $message );
                    return response()->json([
                        'status' => 'success',
                        'message' => "We've sent a one-time password to {$user->Email}. Please enter it below.",
                        'errors' => [],
                        'data' => array( 'Id' => $user->Id, 'Email' => $user->Email ),
                        'block' => false,
                        'count' => $passwordReset->ResendAttempts
                    ], 200);
                }
            }

        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'An account with that Email Address does not exist.',
                'errors' => [],
                'data' => []
            ], 404);
        }
    }
    
    /**
     * @param $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function find( $token ) {
        $passwordReset = PasswordReset::where('Token', $token)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'status' => 'failed',
                'message' => 'This password reset token is invalid.',
                'errors' => [],
                'data' => []
            ], 404);
        }

        if (Carbon::parse($passwordReset->CreatedDate)->addHour(env('RESET_FORGOR_PASSWOR_TOKEN_HOURS'))->isPast()) {
            $passwordReset->delete();
            return response()->json([
                'status' => 'failed',
                'message' => 'This password reset token is invalid.',
                'errors' => [],
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token is verify successfully !!',
            'errors' => [],
            'data' => $passwordReset
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword( Request $request ) {
        $user = User::where('Id', $request->user()->Id)->first();
        $request->validate([
            'current_password' => ['required', new MatchOldPassword],
            'new_password' => 'required|min:8|different:current_password',
            'reenter_password' => 'required|same:new_password'
        ]);
        $user->Password = md5($request->new_password);
        $user->save();
        return response()->json([
            'status' => 'success',
            'message' => 'Password has been update successfully!!',
            'errors' => [],
            'data' => $user
        ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeEmailAddress( Request $request ) {
        $request->validate([
            'user_id' => 'required|numeric',
            'email' => 'required|string|email',
        ]);
        $user = User::where('Id', $request->user_id)->first();
        if( !empty( $user )) {
            
            if($user->Email != $request->email) {
                $currentUserIdVal = $user->Id;
                $request->validate([
                    'email' => ['required', new EmailUnique($user->Id),new EmailVerificationUnique("")],
                ]);

                $message = '';
                if( isset( $request->datum_password_h ) && $request->datum_password_h != "" ) {
                    $request->validate([
                        'datum_password_h' => 'required|min:8',
                        'datum_confirm_pass_h' => 'required|same:datum_password_h'
                    ]);
                    $user->Password = md5($request->datum_password_h);
                    $message = 'and password';
                }

                $user->Email = $request->email;
                $user->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Your email address '.$message.' change successfully',
                    'errors' => [],
                    'data' => []
                ], 200);
            } else {
                if( isset( $request->datum_password_h ) && $request->datum_password_h != "" ) {

                    $request->validate([
                        'datum_password_h' => 'required|min:8',
                        'datum_confirm_pass_h' => 'required|same:datum_password_h'
                    ]);

                    $user->Password = md5($request->datum_password_h);

                    $user->save();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Your password has been successfully updated.',
                        'errors' => [],
                        'data' => []
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Your email address and password up to date.',
                        'errors' => [],
                        'data' => []
                    ], 200);
                }
            }

        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'User is not found please register',
                'errors' => [],
                'data' => []
            ], 200);
        }

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendMobileOTP ( Request $request ) {
        $request->validate([
            'user_id' => 'required|numeric',
            'mobile_phone' => 'required|digits:10'
        ]);
        $country_code = env('COUNTRY_CODE');

        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();

        $user_ip = $this->getIp();
        $user_ip_address = $request->user_ip;
        $user = User::where('Users.Id', $request->user_id)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->orderBy('UserContactMapping.UserTypeId', 'DESC')->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"])->first();

        $userVerification = UserVerification::where('UserId', $request->user_id)->first();
        $flag = true;
        if(( !empty( $user ) && $user->Status == 1 ) || ( !empty($userVerification) && $userVerification->IsEmailVerified == 1 ) ) {
            $flag = false;
        }

        if( $flag ) {
            $VerificationAttempts = UserOtpCheck::where('UserId', $request->user_id)->first();
            if( !empty($VerificationAttempts) && $VerificationAttempts->VerificationAttempts >= 3 ) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Your account is temporarily locked.',
                    'errors' => [],
                    'account' => 1,
                    'isDesabled' => true,
                    'data' => []
                ], 200);    
            }
        }

        if( !empty ($user) ) {
            $userOtpCheck = UserOtpCheck::where('UserId', $request->user_id)->first();
            if( !empty($userOtpCheck) && $userOtpCheck != null) {
                $valid = strtotime($userOtpCheck->OtpLastSent) + (60 * 60);
                if( $flag ) {
                    if($userOtpCheck->OtpTries >= 3 && strtotime("now") < $valid){
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Your account is temporarily locked.',
                            'errors' => [],
                            'account'   => '1',
                            'data' => []
                        ], 200);
                    }
                }
                
                $otp_tries = 0;
                if($userOtpCheck->OtpTries >= 3){
                    $otp_tries = 1;
                }else{
                    $otp_tries = $userOtpCheck->OtpTries + 1;
                }

                $userOTP = sprintf("%06d", mt_rand(100000,999999));
                $sid = env('ACCOUNT_SID');
                $token = env('ACCOUNT_TOCKEN');
                $country_code = env('COUNTRY_CODE');
                
                try {
                    $client = new Client($sid, $token);
                    $message = $client->messages->create(
                        "{$country_code}{$request->mobile_phone}", // Text this number
                        [
                            'from' => '15043809903',
                            'body' => $configurations->SiteName.' one time verification code: '.$userOTP,
                        ]
                    );
                    if(isset($message->sid) && $message->sid != "") {
                        UserOtpCheck::updateOrCreate(
                            ['UserId' => $user->Id],
                            [
                                'Ip' => $user_ip_address,
                                'Otp' => $userOTP, 
                                'OtpTime' => date('Y-m-d H:i:s'),
                                'OtpLastSent' => date('Y-m-d H:i:s'),
                                'OtpTries' => $otp_tries,
                                'Status' => 1,
                                'VerificationAttempts' => 0,
                            ]
                        );

                        UserOtpCheckHistory::Create(
                            [
                                'UserId' => $user->Id,
                                'Ip' => $user_ip_address,
                                'Otp' => $userOTP,
                                'Status' => 1,
                            ]
                        );

                        $userAddressDetails = UserAddressDetails::where('UserId', $user->Id)->first();
                        $userAddressDetails->MobilePhone = $request->mobile_phone;
                        $userAddressDetails->save();
                        return response()->json([
                            'status' => 'success',
                            'message' => 'OTP has been send successfully!',
                            'errors' => [],
                            'data' => [],
                            'account'   => '0',
                        ], 200);
                    } else {
                        return response()->json([
                            'status' => 'failed',
                            'message' => $message->message,
                            'errors' => [],
                            'data' => [],
                            'account'   => '1',
                        ], 200);
                    }
                }
                catch(\Exception $e){
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Your Mobile Number is Wrong',
                        'errors' => [],
                        'data' => [],
                        'account'   => '1',
                    ], 200);
                }
            } else {
                $userOTP = sprintf("%06d", mt_rand(100000,999999));
                $sid = env('ACCOUNT_SID');
                $token = env('ACCOUNT_TOCKEN');
                $client = new Client($sid, $token);
                $country_code = env('COUNTRY_CODE');

                $message = $client->messages->create(
                    "{$country_code}{$request->mobile_phone}", // Text this number
                    [
                        'from' => '15043809903',
                        'body' => $configurations->SiteName.' one time verification code: '.$userOTP,
                    ]
                );

                if(isset($message->sid) && $message->sid != "") {
                    UserOtpCheck::updateOrCreate(
                        ['UserId' => $user->Id],
                        [
                            'Ip' => $user_ip_address,
                            'Otp' => $userOTP,
                            'OtpTime' => date('Y-m-d H:i:s'),
                            'OtpLastSent' => date('Y-m-d H:i:s'),
                            'OtpTries' => 1,
                            'Status' => 1,
                            'VerificationAttempts' => 0,
                        ]
                    );

                    UserOtpCheckHistory::Create(
                        [
                            'UserId' => $user->Id,
                            'Ip' => $user_ip_address,
                            'Otp' => $userOTP,
                            'Status' => 1,
                        ]
                    );
                    
                    $userAddressDetails = UserAddressDetails::where('UserId', $user->Id)->first();
                    $userAddressDetails->MobilePhone = $request->mobile_phone;
                    $userAddressDetails->save();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'OTP has been send successfully!',
                        'errors' => [],
                        'data' => [],
                        'account'   => '0',
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'failed',
                        'message' => $message->message,
                        'errors' => [],
                        'data' => [],
                        'account'   => '1',
                    ], 200);
                }
            }


        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'User not found please register first',
                'errors' => [],
                'data' => [],
                'account'   => '0',
            ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyMobileOTP ( Request $request ) {
        $request->validate([
            'user_id' => 'required|numeric',
            'otp' => 'required|numeric'
        ]);

        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();

        $otp = trim($request->otp);

        $userOtpCheck = UserOtpCheck::where('UserId', $request->user_id)->first();

        $user = User::where('Users.Id', $request->user_id)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->orderBy('UserContactMapping.UserTypeId', 'DESC')->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"])->first();

        $user_ip = $request->user_ip;
        $userVerification = UserVerification::where('UserId', $request->user_id)->first();
        if( !empty($userOtpCheck) && $userOtpCheck != null) {
            
            $flag = true;
            if( !empty( $user ) && !empty($userVerification) ) {
                if(((!empty($user) && $user->Status == 1 ) || (!empty($userVerification) && $userVerification->IsEmailVerified == 1 )) && $userOtpCheck->VerificationAttempts >= 3) {
                    $flag = false;
                    $userOPTDT = UserOtpCheck::where('UserId', $request->user_id)->first();
                    $userOPTDT->VerificationAttempts = 0;
                    $userOPTDT->save();
                }
            }
            
            if( $flag ) {
                $VerificationAttempts = UserOtpCheck::where('UserId', $request->user_id)->first();
                if( !empty($VerificationAttempts) && $VerificationAttempts->VerificationAttempts >= 3 ) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Your account is temporarily locked.',
                        'errors' => [],
                        'account' => 1,
                        'isDesabled' => true,
                        'data' => []
                    ], 200);    
                }
            }

            if($userOtpCheck->Otp != $otp){

                $userOtpCheck1 = UserOtpCheck::where('UserId', $request->user_id)->first();
                $userOtpCheck1->VerificationAttempts = $userOtpCheck1->VerificationAttempts + 1;
                $userOtpCheck1->save();
                
                if($userOtpCheck1->VerificationAttempts >= 3){
                    $userVerification = UserVerification::where('UserId', $request->user_id)->first();
                    $userVerification->IsEmailVerified = null;
                    $userVerification->save();

                    $UserContactMapping = UserContactMapping::where('UserId', $request->user_id)->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();
                    $UserContactMapping->Status = 2;
                    $UserContactMapping->save();
                    $UserContactMappingHistory = new UserContactMappingHistory(array(
                        "UserId" => $UserContactMapping->UserId,
                        "UserTypeId" => $UserContactMapping->UserTypeId,
                        "Status" => $UserContactMapping->Status,
                        "IndustryRoleId" => $UserContactMapping->IndustryRoleId,
                        "InvestorTypeId" => $UserContactMapping->InvestorTypeId,
                        "BrokerTypeId" => $UserContactMapping->BrokerTypeId,
                        "SubscriptionTypeId" => $UserContactMapping->SubscriptionTypeId,
                        "ConfigurationId" => $UserContactMapping->ConfigurationId,
                        "CreatedOn" => $UserContactMapping->CreatedOn,
                        "CreatedBy" => $UserContactMapping->UserId
                    ));
                    $UserContactMappingHistory->save();

                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Your account is temporarily locked.',
                        'errors' => [],
                        'account' => 1,
                        'isDesabled' => true,
                        'data' => []
                    ], 200);
                } else {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Invalid verification code. Please try again.',
                        'errors' => [],
                        'data' => []
                    ], 200);
                }
            }

            $valid = strtotime($userOtpCheck->OtpLastSent) + (2 * 60);
            if (strtotime("now") > $valid) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Your OTP has been expired.',
                    'errors' => [],
                    'data' => []
                ], 200);
            }
            $userVerification = UserVerification::where('UserId', $user->Id)->first();

            $userVerification->IsMobileVerified = 1;
            $userVerification->MobileVerificationDatetime = date('Y-m-d H:i:s');
            $userVerification->save();

            $UserContactMapping = UserContactMapping::where('UserId', $user->Id)->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();

            $UserContactMapping->Status = 1;
            $UserContactMapping->save();

            $email = new Email();
            $to = array(
                array(
                    'email' => $user->Email,
                    'name' => $user->FirstName.' '.$user->LastName
                ),
            );
            $params = env("LOGIN_ACTION");
            $loginPopup = array(
                'user_id' => "",
                'verification_code' =>"",
                'username' => "",
                "password" => "",
                'dmaction' => 'login'
            );

            $link = $configurations->ClientServerProtocol.$configurations->SiteUrl.'?datumpara='.base64_encode(json_encode($loginPopup));

            $subject = "Thank you for completing your registration successfully";

            $content = "<p>Thank you for completing your registration with {$configurations->SiteName}. Please click the link below to login to your account.<p>";
            $content .= "<p><a href=".$link." target='_blank'>Login</a> </p>";
            $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
            $email->sendEmail( $subject, $to, $message );

            $userVerificationHistory = new UserVerificationHistory([
                "UserId" => $user->Id,
                "VerifiedDatetime" => date("Y-m-d H:i:s"),
                "VerifiedBy" => $user->Id,
                "IsVerified" => 1,
                "VerificationType" => 1,
                "ConfigurationId"   => $configurations->ConfigurationId,
                "HostedConfigurationId" => $configurations->ConfigurationId,
            ]);
            $userVerificationHistory->save();

            $userAccountHistory = new UserAccountHistory([
                "Ip" => $user_ip,
                "BrowserName" => $request->user_agent,
                "UserId" => $user->Id,
                "ModuleType" => 1,
                "Description" => 'Contact Verified',
                "CreatedBy" => $user->Id,
                "CreatedDate" => date('Y-m-d H:i:s'),
                "ConfigurationId" => $configurations->ConfigurationId,
                "HostedConfigurationId" => $user->ConfigurationId,
            ]);
            $userAccountHistory->save();

            UserOtpCheckHistory::Create(
                [
                    'UserId' => $user->Id,
                    'Ip' => $user_ip,
                    'Otp' => $otp,
                    'Status' => 1,
                ]
            );
            return response()->json([
                'status' => 'success',
                'message' => 'OTP verify has been successfully.',
                'account'  => 1,
                'errors' => [],
                'data' => []
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'OTP has been not found. Please try to reset OTP.',
                'errors' => [],
                'data' => []
            ], 200); 
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificationEmailToken( Request $request ) {
        $request->validate([
            'token' => 'required'
        ]);
        $user_ip_address        = $request->user_ip;
        $databaseConnection     = new DatabaseConnection();
        $currentConnection      = $databaseConnection->getConnectionName();
        $configurations         = $databaseConnection->getConfiguration();

        $userVerification = UserVerification::where('VerificationId', $request->token)
            ->first();
        if( !empty ($userVerification) && $userVerification != null ) {
            if(!Carbon::parse($userVerification->VerificationExpiryDate)->addHour(env('EMAIL_VERIFICATION_HOURS'))->isPast()) {
                if(!empty($userVerification) && $userVerification != null ) {
                    if($userVerification->IsEmailVerified == 1 || $userVerification->IsMobileVerified == 1) {
                        return response()->json([
                            'status' => 'success',
                            'message' => 'User aleready verify',
                            'errors' => [],
                            'data' => $userVerification
                        ], 200);
                    }

                    $userVerification->IsEmailVerified = 1;
                    $userVerification->EmailVerifiedDateTime = date("Y-m-d H:i:s");
                    $userVerification->EmailVerifiedBy = $userVerification->UserId;
                    $userVerification->EmailVerifiedDateTime = date("Y-m-d H:i:s");

                    $userVerificationHistory = new UserVerificationHistory([
                        "UserId" => $userVerification->UserId,
                        "VerifiedDatetime" => date("Y-m-d H:i:s"),
                        "VerifiedBy" => $userVerification->UserId,
                        "IsVerified" => 1,
                        "VerificationType" => 1,
                        "ConfigurationId"   => $configurations->ConfigurationId,
                        "HostedConfigurationId" => $configurations->ConfigurationId,
                    ]);
                    $user = User::where('Id', $userVerification->UserId)->first();
                    $userAccountHistory = new UserAccountHistory([
                        "UserId" => $userVerification->UserId,
                        "Description" => 'Contact Verified',
                        "ModuleType" => 1,
                        "Ip" => $user_ip_address,
                        "BrowserName" => null,
                        "CreatedBy" => $userVerification->UserId,
                        "CreatedDate" => date('Y-m-d H:i:s'),
                        "ConfigurationId" => $configurations->ConfigurationId,
                        "HostedConfigurationId" => $user->ConfigurationId,
                    ]);
                    $userAccountHistory->save();
                    $UserContactMapping = UserContactMapping::where('UserId', $user->Id)->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();
                    $UserContactMapping->Status = 1;
                    if($UserContactMapping->save()) {
                        $UserContactMappingHistory = new UserContactMappingHistory(array(
                            "UserId" => $UserContactMapping->UserId,
                            "UserTypeId" => $UserContactMapping->UserTypeId,
                            "Status" => $UserContactMapping->Status,
                            "IndustryRoleId" => $UserContactMapping->IndustryRoleId,
                            "InvestorTypeId" => $UserContactMapping->InvestorTypeId,
                            "BrokerTypeId" => $UserContactMapping->BrokerTypeId,
                            "SubscriptionTypeId" => $UserContactMapping->SubscriptionTypeId,
                            "ConfigurationId" => $UserContactMapping->ConfigurationId,
                            "CreatedOn" => $UserContactMapping->CreatedOn,
                            "CreatedBy" => $UserContactMapping->UserId
                        ));
                        $UserContactMappingHistory->save();

                        $userVerification->save();
                        $userVerificationHistory->save();
                        $userDatas = [];
                        $isDashboardUser = false;

                        $databaseConnection = new DatabaseConnection();
                        $configurations = $databaseConnection->getConfiguration();

                        $email = new Email();
                        $to = array(
                            array(
                                'email' => $user->Email,
                                'name' => $user->FirstName.' '.$user->LastName
                            ),
                        );
                        $params = env("LOGIN_ACTION");
                        $loginPopup = array(
                            'user_id' => "",
                            'verification_code' =>"",
                            'username' => "",
                            "password" => "",
                            'dmaction' => 'login'
                        );
                        $link = $configurations->ClientServerProtocol.$configurations->SiteUrl.'?dmaction='.base64_encode(json_encode($loginPopup));
                        $subject = "Thank you for completing your registration successfully";

                        $content = "<p>Thank you for completing your registration with {$configurations->SiteName}. Please click the link below to login to your account.<p>";
                        $content .= "<p><a href=".$link." target='_blank'>Login</a></p>";
                        $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                        //$email->sendEmail( $subject, $to, $message );

                        if( $user->IsContactCreatedByDashboard == 1 && $user->NextUpdateDate == null ) {
                            $isDashboardUser = true;
                            $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
                            $azurePath = env('AZURE_STORAGE_URL');

                            $query = User::query();
                            $query->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
                            $query->where('Users.Id', '=', $user->Id);
                            $query->orderBy('UserContactMapping.UserTypeId', 'DESC');
                            $query->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
                            $data = $query->first();

                            if($data[0]->CompanyId != '' && $data[0]->CompanyId != null ) {
                                $company = Companies::where('Id', $data[0]->CompanyId)->first();
                                if(!empty($company)) {
                                    $data[0]->CompanyName = $company->CompanyName;
                                } else {
                                    $data[0]->CompanyName = "";
                                }
                            } else {
                                $data[0]->CompanyName = "";
                            }
                            if( !empty($data[0]->getAcquisitioncriteriaContactRelation)) {
                                $ids1 = [];
                                $ids2 = [];
                                $commonId = [];
                                foreach($data[0]->getAcquisitioncriteriaContactRelation as $key => $value ) {
                                    if( $value->AcquisitionCriteriaSubTypeId != null) {
                                        $commonId[] = $value->AcquisitionCriteriaTypeId;
                                        $ids1[$value->AcquisitionCriteriaTypeId][] = $value->AcquisitionCriteriaSubTypeId;
                                    }
                                    $ids2[] = $value->AcquisitionCriteriaTypeId;
                                }

                                unset($data[0]->getAcquisitioncriteriaContactRelation);
                                $ids2 = array_unique($ids2);
                                $ids2 = array_diff($ids2, array_unique($commonId));

                                $data[0]->get_acquisitioncriteria_contact_relation = array(
                                    'acquisitionCriteriaSubType' =>$ids1,
                                    'acquisitionCriteriaType'=> $ids2
                                );
                            }
                            $isNextUpdateDate = false;
                            if($data[0]->NextUpdateDate <= date('Y-m-d H:i:s')  || $data[0]->NextUpdateDate == null) {
                                $isNextUpdateDate = true;
                            }
                            $data[0]->isNextUpdateDate = $isNextUpdateDate;

                            if( $data[0]->ProfileImage != '' && $data[0]->ProfileImage != null) {
                                $data[0]->ProfileImage = $azurePath.'/'.$profileContainerName.'/'.$data[0]->ProfileImage;
                            }
                            $userDatas = $data[0];

                        }

                        $confId = $configurations->ConfigurationId;
                        /**
                         * Update userId to documentvultomaccess table
                         */
                        $documentVaultomAccess = DocumentVaultomAccess::where('UserEmail', $user->Email)->first();
                        if( !empty( $documentVaultomAccess ) && $documentVaultomAccess != null ) {
                            $documentVaultomAccess->UserId = $user->Id;
                            $documentVaultomAccess->save();
                        }

                        /*$leadbase = new Leadbase();
                        $leadbase->LeadID = $user->Id;
                        $leadbase->Base = 1;
                        $leadbase->CreatedBy = $user->Id;
                        $leadbase->CreatedDate = date("Y-m-d H:i:s");
                        $leadbase->save();*/

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Email verification successfully!!!',
                            'isVerifiedUser'=>'no',
                            'email' => $user->Email,
                            'errors' => [],
                            'data' => (object) array('isDashboardUser'=> $isDashboardUser, 'user'=>$userDatas)
                        ], 200);
                    } else {
                        $email = new Email();
                        $to = array(
                            array(
                                'email' => $user->Email,
                                'name' => $user->FirstName.' '.$user->LastName
                            ),
                        );
                        $subject = "Failure of registration";
                        $content = "<p>Your registration has not been completed, please contact to administrator.<p>";
                        $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);
                        //$email->sendEmail( $subject, $to, $message );
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'This email verification token is invalid.',
                            'errors' => [],
                            'data' => []
                        ], 200);
                    }

                } else {

                    return response()->json([
                        'status' => 'failed',
                        'message' => 'This email verification token is invalid.',
                        'errors' => [],
                        'data' => []
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'This email verification token is expired.',
                    'errors' => [],
                    'data' => []
                ], 200);
            }
        } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'This email verification token is expired.',
                    'errors' => [],
                    'data' => []
                ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function ResendEmailVerificationLink( Request $request ) {
        $request->validate([
            'user_id' => 'required|numeric',
        ]);

        $user = User::where('Id', $request->user_id)->first();

        if(!empty($user)) {

            $userVerification = UserVerification::where('UserId', $user->Id)
                ->first();

            if($userVerification->IsEmailVerified == 1 || $userVerification->IsMobileVerified == 1) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'User aleready verified.',
                    'errors' => [],
                    'data' => []
                ], 404);
            }

            $varificationId = md5(uniqid());
            $userVerification->VerificationId = $varificationId;
            $expDateTime = env('EMAIL_VERIFICATION_HOURS');
            $userVerification->VerificationExpiryDate = date("Y-m-d H:i:s", strtotime('+'.$expDateTime.' hours'));
            $userVerification->save();

            $email = new Email();

            $to = array(
                array(
                    'email' => $user->Email,
                    'name' => $user->FirstName.' '.$user->LastName
                ),
            );

            $databaseConnection = new DatabaseConnection();
            $currentConnection = $databaseConnection->getConnectionName();
            $configurations = $databaseConnection->getConfiguration();

            $verificationArray = array(
                'user_id' => $user->Id,
                'verification_code' =>$userVerification->VerificationId,
                'username' => $user->Email,
                "password" => "",
                'dmaction' => 'wordpress_verification'
            );

            $subject = $configurations->SiteName." Verfication Link";
            $verifyuser = $configurations->ClientServerProtocol.$configurations->SiteUrl.'?datumpara='.base64_encode(json_encode($verificationArray));
            $content = "<p>Please follow the below link to complete your registration.</p>";
            $content .= "<p><a href=".$verifyuser." target='_blank'>Complete your registration</a></p>";
            $message = $email->email_content($user->FirstName.' '.$user->LastName, $content);

            $email->sendEmail( $subject, $to, $message );

            return response()->json([
                'status' => 'success',
                'message' => "To verify your email, we've send a unique link.",
                'errors' => [],
                'data' => []
            ], 404);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'User is not registered. Please followed registration process',
                'errors' => [],
                'data' => []
            ], 404);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmailToken ( Request $request ) {
        $request->validate([
            'token' => 'required',
        ]);

        $userVerification = UserVerification::where('VerificationId', $request->token)
            ->first();

        if( !empty($userVerification) && $userVerification != null ) {
            if( time() - strtotime($userVerification->VerificationExpiryDate) > 60*60*env('EMAIL_VERIFICATION_HOURS') ) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'This email verification token is expired.',
                    'errors' => [],
                    'data' => []
                ], 200);
            } else {
                if($userVerification->IsEmailVerified == 1 || $userVerification->IsMobileVerified == 1) {
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'User already verify',
                        'errors' => [],
                        'isVerifiedUser'=> 'yes',
                    ], 200); 
                } else {
                    return response()->json([
                        'status' => 'success',
                        'isVerifiedUser'=> 'no',
                        'data' => $userVerification
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'This email verification token is expired.',
                'errors' => [],
                'data' => []
            ], 200);
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

    public function UserLoginById(Request $request){
        $user_ip_address    = $request->user_ip;
        $user_agent         = $request->user_agent;
        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        $azurePath = env('AZURE_STORAGE_URL');

        $databaseConnection = new DatabaseConnection();
        $currentConnection = $databaseConnection->getConnectionName();
        $configurations = $databaseConnection->getConfiguration();
        $user = User::query();
        $user->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        //$user->leftJoin('ContactAccountStatus', 'ContactAccountStatus.UserId', '=', 'Users.Id');

        $user->leftJoin('ContactAccountStatus', function ($join) use ($configurations)
            {
                $join->on('ContactAccountStatus.UserId', '=', 'Users.Id');
                $join->on('ContactAccountStatus.ConfigurationId','=',DB::raw($configurations->ConfigurationId));
            });

        $user->where('Users.Id', $request->Id);
        $user->whereIn('UserContactMapping.Status', array('1','2'));
        $user->where(function($b) use ($request){
            $b->where('Users.IsSuperAuthorizedAccount', '!=',1)
                ->orWhereNull('Users.IsSuperAuthorizedAccount');
        });
        $user->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $user->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId","ContactAccountStatus.status AS ContactStatus"]);
        $user = $user->first();
       

        $userAddressDetails = UserAddressDetails::where('UserId', $user->Id)->first();
        $userVerification   = UserVerification::where('UserId', $user->Id)->first();
        if(!empty($user)) {
            if($user->Status == 2){
                return response()->json([
                    'status'    => 'failed',
                    'type'      => 'Not login',
                    'errors'    => [],
                    'data'      => []
                ], 200);
            }elseif($user->Status == 1 && ($user->ContactStatus == 1 || $user->ContactStatus == null )) {
                
                $tokenResult = $user->createToken('Personal Access Token');
                
                $user->LastSiteLoginTime = date('Y-m-d H:i:s');
                //$user->timestamps = false;
                $user->save();
                $token = $tokenResult->token;
                
                if ($request->remember_me) {
                    $token->expires_at = Carbon::now()->addHour(24);
                } else {
                    $token->expires_at = Carbon::now()->addHour(24);
                }

                $token->save();
                
                $userLoginHistory = new UserLoginHistory([
                    "SessionToken"  => "",
                    "UserId"        => $user->Id,
                    "TimeLogin"     => date('Y-m-d H:i:s'),
                    "TimeLastSeen"  => date('Y-m-d H:i:s'),
                    "IpAddress"     => $user_ip_address,
                    "Device"        => $this->getDevice(),
                    "IsLogout"      => 1,
                    "ConfigurationId" => $configurations->ConfigurationId,
                ]);
                $userLoginHistory->save();
                
                $userAccountHistory = new UserAccountHistory([
                    "UserId" => $user->Id,
                    "Description" => 'Login',
                    "ModuleType" => 1,
                    "Ip" => $user_ip_address,
                    "BrowserName" => $user_agent,
                    "CreatedBy" => $user->Id,
                    "CreatedDate" => date('Y-m-d H:i:s'),
                    "ConfigurationId" => $configurations->ConfigurationId,
                    "HostedConfigurationId" => $user->ConfigurationId
                ]);
                $userAccountHistory->save();
                $userDatas = array(
                    'Id' => $user->Id,
                    'data' => $user,
                    'FirstName' => $user->FirstName,
                    'LastName' => $user->LastName,
                    'Email' => $user->Email,
                    'get_user_address_details_relation' => array('MobilePhone'=>!empty($userAddressDetails) ? $userAddressDetails->MobilePhone : ''),
                    'IsAccountVerified' => true,
                    'IsRegistrationCompleted'  => $user->IsRegistrationCompleted,
                    'IsupdateDashbord' => false,
                    'IsSocial'  => $user->IsSocial,
                );


                return response()->json([
                    'status'=>'success',
                    'message' => 'Successfully user loged in!',
                    'errors' => [],
                    'data' => [
                        'user' => $userDatas
                    ],
                    'type'  => 'login',
                    'access_token' => $tokenResult->accessToken,
                    'token_type' => 'Bearer',
                    'expires_at' => Carbon::parse($tokenResult->token->expires_at,
                    )->toDateTimeString()
                ]);
            }else{
                return response()->json([
                    'status'    => 'failed',
                    'type'      => 'Not login',
                    'errors'    => [],
                    'data'      => []
                ], 200);
            }
        }else{
            return response()->json([
                'status'    => 'failed',
                'type'      => 'Not login',
                'errors'    => [],
                'data'      => []
            ], 200);
        }

    }

    public function checkMobileNumber(Request $request){
        $sid    = env('ACCOUNT_SID');
        $token  = env('ACCOUNT_TOCKEN');
        $country_code = env('COUNTRY_CODE');
        try {
            $twilio = new Client($sid, $token);

            $phone_number = $twilio->lookups->v1->phoneNumbers($request->cell_phone)->fetch(["countryCode" => "US"]);
            if($phone_number->phoneNumber){
                 return response()->json([
                    'status'=>'success',
                    'message' =>"",
                    'errors' => [],
                    'data' => []
                ], 200);
            }else{
                 return response()->json([
                    'status'=>'failed',
                    'message' => '',
                    'errors' => [],
                    'data' => []
                ], 200);
            } 
        } catch (Exception $e) {
            echo "Code: " . $e->getCode() . " Message: " . $e->getMessage();
        }
    }
}
