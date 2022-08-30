<?php

namespace App\Http\Controllers; 

use App\Congigurations;
use App\FavoriteProperty;
use App\Property\AcquisitioncriteriaPropertyRelation;
use App\Property\AcquisitionType;
use App\Property\AcquisitionSubType;
use App\Property\PropertyAgents;
use App\Property\PropertyImages;
use App\LeadAdminProperty;
use App\Companies;
use App\WpOsdUserPropertiesRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\AgentApi;
use App\Database\DatabaseConnection;
use App\Property\Property;
use App\Property\ListingStatus;
use App\Property\BuildingClass;
use App\Property\PressreleaseHistory;
use App\Property\DocumentVaultomAccess;
use App\Property\PropertyPressRelease;
use App\Property\DocumentVaultOMAccessHistory;
use App\PluginActivation;
use App\User;
use App\IndustryRole;
use App\BrokerType;
use App\InvestorType;
use App\DocumentVault;
use App\Directory;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use App\Mail\Email;
use App\Traits\EncryptionDecryption;
Use App\OeplPropertyTracker;
use App\States;
use Illuminate\Support\Facades\Storage;
use URL;
use App\Agreement\Agreement;
use App\Document\Document;
use App\Document\DocumentHigh;
use ZipArchive;
use App\Documentvaultomddhistory;
use App\Property\NdaTracker;
use App\PropetyConfigurationMapping;
use App\UserNotifications;
const UNDERCONTRACT         = 4;
const OMVaultAccessRequest  = 1;

/**
 * Class DocumentVaultPreviewController
 * @package App\Http\Controllers
 */
class DocumentVaultPreviewController extends Controller {
    use Common;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function documentList(Request $request){
    
        $this->currentUser = $request->user();
        $databaseConnection = new DatabaseConnection();
        $configurationData = $databaseConnection->getConfiguration();
        $currentUser       = auth()->guard('api')->user();
        $property = Property::query();
        if(is_numeric($request->propertyId)) {
            $property->where('Property.Id', $request->propertyId);
        } else {
            $property->where('Property.URLSlug', $request->propertyId);
        }
        $property = $property->first();

        if(isset($request->page_view_count) && $request->page_view_count == '1'){
            $hostedConfigData   = $databaseConnection->getPropertyConfiguration($property->Id);
            $browse_from_mobile = 0;
            if ( isset( $request->device ) && $request->device != '') {
                $browse_from_mobile = $request->device;
            }

            if(!empty($property) && $property != null ) {
                $oeplPropertyTracker = new OeplPropertyTracker();
                if(!empty($currentUser)) {
                    $oeplPropertyTracker->UserId        = $currentUser->Id;
                    $oeplPropertyTracker->LoggedIn      = 1;
                } else {
                    $oeplPropertyTracker->LoggedIn      = 0;
                }
                $oeplPropertyTracker->PropertyId        = $property->Id;
                $oeplPropertyTracker->CreatedDateTime   = date('Y-m-d H:i:s');
                $oeplPropertyTracker->SessionId         = '';
                $oeplPropertyTracker->UserIp            = $request->user_ip;
                $oeplPropertyTracker->BrowseFromMobile  = $browse_from_mobile;
                $oeplPropertyTracker->ConfigurationId   = $configurationData->ConfigurationId;
                $hostedConfigData->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $oeplPropertyTracker->save();
            }
        }

        $user_email = $this->currentUser->Email;
        if(!empty($property)){
            /*if($property->PropertyStatusId == UNDERCONTRACT){
                $this->underContractPropertyProcess();
            }else{
                return $this->checkStatusDocumentAccess($request->propertyId,$this->currentUser->Id,$request->user_ip,'',$request->documentType);
            } */
            return $this->checkStatusDocumentAccess($request->propertyId,$this->currentUser->Id,$request->user_ip,'',$request->documentType);
        }else{
            return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => [],
                'URL' => '',
            ], 200);  
        }
    }

    /**
     * @param string $id
     * @param string $user_id
     * @param string $user_ip
     * @param string $URL
     * @return \Illuminate\Http\JsonResponse
     */
    public function agreementController($id = '',$user_id = '',$user_ip = '',$URL = ''){

        $property_om = Property::query();
        
        if(is_numeric($id)) {
            $property_om->where('Property.Id', $id);
        } else {
            $property_om->where('Property.URLSlug', $id);
        }

        $property_om->leftJoin('PropertyCAContent', 'PropertyCAContent.PropertyID', '=', 'property.ID');
        $property_om->select(['Property.*',"PropertyCAContent.CAPdfDocument"]);

        $property_om = $property_om->first();


        if (!empty($property_om) && $property_om->CAPdfDocument != '') {
            $Agreement = new Agreement();
            $caResult  = $Agreement->confidencialAgreementPreview($user_id,$id,$user_ip);

            if (isset($caResult['type'] ) || $caResult['type'] == '404') {
                $agrement = 'agreement';
            } else {
                $agrement = 'agreement_success';
            }

            return response()->json(
            [
                'status'    => 'success',
                'message'   => [],
                'errors'    => [],
                'data'      => [],
                'URL'       => $caResult['URL'],
                'type'      => $agrement,
                'downloadCA'   => $URL,
                'publish_date' => date('m/d/Y')
            ], 200);
        }else{
            return response()->json(
            [
                'status'      => 'success',
                'message'     => [],
                'errors'      => [],
                'data'        => [],
                'ca_notfound' => true,
                'URL'         => URL::to('/ca-404'),
                'type'        => 'agreement_success'
            ], 200);
        }

    }

    /**
     * @param $request
     * @return array
     * @throws \SendGrid\Mail\TypeException
     */
    public function addOMAccessToUser($request) {
        $currentUser = $request->currentUser;
        $databaseConnection = new DatabaseConnection();
        
        $property = Property::where('Id', $request->property_id)
            ->first();
        $leadAdminProperty = new LeadAdminProperty();
        $encrypt_arr = array();
        $encrypt_arr['PropertyId'] = $property->Id;
        $encrypt_arr['UserId'] = $currentUser->Id;

        $encrypt_param = base64_encode(json_encode($encrypt_arr));
        $dashboardUrl = env('DASHBOARD_URL').'/';

        $string = $property->URLSlug;

        $dd_request_url = $dashboardUrl.'securitylevelrequest/'.$string.'/'.$encrypt_param;

        $directory = Directory::where("PropertyId", $property->Id)->where("DirectoryName", 'Offering Memorandum')->first();
        //$this->userNotifications($property->Id,$currentUser->Id);

        $propertyagents = PropertyAgents::query()
            ->select('AgentId')
            ->where('PropertyId', $request->property_id)
            ->where('IsNotificationEnabled', 1)
            ->where('EntityStatusId', 1)
            ->get();
        $agentIds = [];

        if ( !$propertyagents->isEmpty() ) {
            foreach ( $propertyagents as $key => $value ) {
                $agentIds[] = $value->AgentId;
            }
        }
        
        $configurationData     = $databaseConnection->getConfiguration();
        $confDataId            = $configurationData->ConfigurationId;
        $propertyConfig        = $databaseConnection->getPropertyConfiguration($property->Id);
        $hostedConfigurationId = $propertyConfig->ConfigurationId;

        $users = User::query();
        $users->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $users->whereIn('Users.Id', $agentIds);
        $users->where('UserContactMapping.UserTypeId', '!=', 4);
        $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email"]);
        $agent = $users->get();
        $agent_to = [];
        if( !$agent->isEmpty() && $agent != null ) {
            $i = 0;
            foreach ( $agent as $key => $value ) {
                $agent_to[$i]['email'] = $value->Email;
                $agent_to[$i]['name'] = $value->FirstName.' '.$value->LastName;
                $i++;
            }
        }

        $documentVaultomAccess12 = Directory::query();
        $documentVaultomAccess12->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
        $documentVaultomAccess12->where('DatumDirectory.PropertyId', $property->Id);
        $documentVaultomAccess12->where('DocumentVaultOMAccess.Access', "=", 2);
        $documentVaultomAccess12->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
        $documentVaultomAccess12->where('DatumDirectory.ParentId', 0);
        $documentVaultomAccess12->where('DocumentVaultOMAccess.UserId', $currentUser->Id);
        $documentVaultomAccess12->select(['DocumentVaultOMAccess.*']);
        $documentVaultomAccess12 = $documentVaultomAccess12->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
        if(!empty($documentVaultomAccess12) && $documentVaultomAccess12 != null) {
            return
                [
                    'status' => 'success',
                    'message' => 'You have tried already for access',
                    'errors' => [],
                    'data' => array('access'=>true)
                ];
        } else {
            if( !empty($directory) && $directory != null ) {

                $documentVaultomAccess = DocumentVaultomAccess::where('UserId', $currentUser->Id)->where('DatumDirectoryId', $directory->Id)->orderBy('Id', 'desc')->first();

                $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property->Id)->where('UserId', $currentUser->Id)->first();

                $underContractLogicFlag = false;
                if($propertyUserRelationship->DuediligenceRequestStatus == 3) {
                    $underContractLogicFlag = true;
                }

                $sqlAccess = 2;
                if($underContractLogicFlag) {
                    $sqlAccess = 3;
                }

                if( !empty($documentVaultomAccess) && $documentVaultomAccess != null ) {
                    $documentVaultomAccess->DatumDirectoryId = $directory->Id;
                    $documentVaultomAccess->UserId = $currentUser->Id;
                    if($underContractLogicFlag) {
                        $documentVaultomAccess->Access = 3;
                    } else {
                        $documentVaultomAccess->Access = 2;
                    }
                    $documentVaultomAccess->UpdatedBy = $currentUser->Id;
                    $documentVaultomAccess->UpdatedDate = date('Y-m-d H:i:s');
                    //$documentVaultomAccess->ConfigurationId = $configurationData->ConfigurationId;
                    //$documentVaultomAccess->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultomAccess->save();

                    $documentVaultOMAccessHistory = new DocumentVaultOMAccessHistory();
                    $documentVaultOMAccessHistory->DocumentVaultOMAccessId = $documentVaultomAccess->Id;
                    $documentVaultOMAccessHistory->UserId = $currentUser->Id;
                    $documentVaultOMAccessHistory->UserEmail = $currentUser->Email;
                    $documentVaultOMAccessHistory->Access = 2;
                    $documentVaultOMAccessHistory->CreatedBy = $currentUser->Id;
                    $documentVaultOMAccessHistory->CreatedDate = date('Y-m-d H:i:s');
                    $documentVaultOMAccessHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultOMAccessHistory->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultOMAccessHistory->save();

                    $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
                    $DocumentVaultOMDDHistory->UserId = $currentUser->Id;
                    $DocumentVaultOMDDHistory->PropertyId = $property->Id;
                    $DocumentVaultOMDDHistory->DocumentRole = 'OM';
                    $DocumentVaultOMDDHistory->CreatedDate = date('Y-m-d H:i:s');
                    $DocumentVaultOMDDHistory->CreatedBy = $currentUser->Id;
                    $DocumentVaultOMDDHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
                    $DocumentVaultOMDDHistory->save();

                    if($hostedConfigurationId != $confDataId) {
                        self::savePropertyConfigurationMapping($property->Id, $currentUser->Id, $confDataId, $hostedConfigurationId);
                    }
                } else {
                    $documentVaultomAccess = new DocumentVaultomAccess();
                    $documentVaultomAccess->DatumDirectoryId = $directory->Id;
                    $documentVaultomAccess->UserId = $currentUser->Id;
                    $documentVaultomAccess->Access = 2;
                    $documentVaultomAccess->UpdatedBy = $currentUser->Id;
                    $documentVaultomAccess->UpdatedDate = date('Y-m-d H:i:s');
                    $documentVaultomAccess->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultomAccess->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultomAccess->save();

                    $documentVaultOMAccessHistory = new DocumentVaultOMAccessHistory();
                    $documentVaultOMAccessHistory->DocumentVaultOMAccessId = $documentVaultomAccess->Id;
                    $documentVaultOMAccessHistory->UserId = $currentUser->Id;
                    $documentVaultOMAccessHistory->UserEmail = $currentUser->Email;
                    $documentVaultOMAccessHistory->Access = 2;
                    $documentVaultOMAccessHistory->CreatedBy = $currentUser->Id;
                    $documentVaultOMAccessHistory->CreatedDate = date('Y-m-d H:i:s');
                    $documentVaultOMAccessHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultOMAccessHistory->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultOMAccessHistory->save();

                    $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
                    $DocumentVaultOMDDHistory->UserId = $currentUser->Id;
                    $DocumentVaultOMDDHistory->PropertyId = $property->Id;
                    $DocumentVaultOMDDHistory->DocumentRole = 'OM';
                    $DocumentVaultOMDDHistory->CreatedDate = date('Y-m-d H:i:s');
                    $DocumentVaultOMDDHistory->CreatedBy = $currentUser->Id;
                    $DocumentVaultOMDDHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
                    $DocumentVaultOMDDHistory->save();
                    if($hostedConfigurationId != $confDataId) {
                        self::savePropertyConfigurationMapping($property->Id, $currentUser->Id, $confDataId, $hostedConfigurationId);
                    }
                }

                $leadAdminProperty = LeadAdminProperty::where('PropertyId', $property->Id)->where('UserId', $currentUser->Id)->first();

                if(!empty($leadAdminProperty) && $leadAdminProperty != null ) {
                    $leadAdminProperty->StatusId = 13;
                    $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                    $leadAdminProperty->HostedConfigurationId = $hostedConfigurationId;
                    $leadAdminProperty->save();
                }  else {
                    $leadAdminProperty = new LeadAdminProperty();
                    $leadAdminProperty->PropertyId = $property->Id;
                    $leadAdminProperty->AdminId = $currentUser->Id;
                    $leadAdminProperty->UserId = $currentUser->Id;
                    $leadAdminProperty->PriorityId = 0;
                    $leadAdminProperty->StatusId = 13;
                    $leadAdminProperty->TopProspect = 0;
                    $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                    $leadAdminProperty->HostedConfigurationId = $hostedConfigurationId;
                    $leadAdminProperty->save();
                }
            } else {
                $directory = new Directory([
                    'DirectoryGUID' => $this->GUID(),
                    'ParentId' => 0,
                    'PropertyId' =>$property->Id,
                    'DirectoryName' => 'Offering Memorandum',
                    'CreatedBy' => $currentUser->Id,
                    'CreatedDate' => date('Y-m-d H:i:s'),
                ]);
                $directory->save();

                $documentVaultomAccess = DocumentVaultomAccess::where('UserId', $currentUser->Id)->where('DatumDirectoryId', $directory->Id)->orderBy('Id', 'desc')->first();

                $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property->Id)->where('UserId', $currentUser->Id)->first();

                $underContractLogicFlag = false;
                if($propertyUserRelationship->DuediligenceRequestStatus == 3) {
                    $underContractLogicFlag = true;
                }

                $sqlAccess = 2;
                if($underContractLogicFlag) {
                    $sqlAccess = 3;
                }

                if( !empty($documentVaultomAccess) && $documentVaultomAccess != null ) {
                    $documentVaultomAccess->DatumDirectoryId = $directory->Id;
                    $documentVaultomAccess->UserId = $currentUser->Id;
                    $documentVaultomAccess->Access = 2;
                    $documentVaultomAccess->UpdatedBy = $currentUser->Id;
                    $documentVaultomAccess->UpdatedDate = date('Y-m-d H:i:s');
                    /*$documentVaultomAccess->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultomAccess->HostedConfigurationId = $hostedConfigurationId;*/
                    $documentVaultomAccess->save();

                    $documentVaultOMAccessHistory = new DocumentVaultOMAccessHistory();
                    $documentVaultOMAccessHistory->DocumentVaultOMAccessId = $documentVaultomAccess->Id;
                    $documentVaultOMAccessHistory->UserId = $currentUser->Id;
                    $documentVaultOMAccessHistory->UserEmail = $currentUser->Email;
                    $documentVaultOMAccessHistory->Access = 2;
                    $documentVaultOMAccessHistory->CreatedBy = $currentUser->Id;
                    $documentVaultOMAccessHistory->CreatedDate = date('Y-m-d H:i:s');
                    $documentVaultOMAccessHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultOMAccessHistory->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultOMAccessHistory->save();

                    $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
                    $DocumentVaultOMDDHistory->UserId = $currentUser->Id;
                    $DocumentVaultOMDDHistory->PropertyId = $property->Id;
                    $DocumentVaultOMDDHistory->DocumentRole = 'OM';
                    $DocumentVaultOMDDHistory->CreatedDate = date('Y-m-d H:i:s');
                    $DocumentVaultOMDDHistory->CreatedBy = $currentUser->Id;
                    $DocumentVaultOMDDHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
                    $DocumentVaultOMDDHistory->save();
                    
                    if($hostedConfigurationId != $confDataId) {
                        self::savePropertyConfigurationMapping($property->Id, $currentUser->Id, $confDataId, $hostedConfigurationId);
                    }
                } else {
                    $documentVaultomAccess = new DocumentVaultomAccess();
                    
                    $documentVaultomAccess->DatumDirectoryId = $directory->Id;
                    $documentVaultomAccess->UserId = $currentUser->Id;
                    $documentVaultomAccess->Access = 2;
                    $documentVaultomAccess->UpdatedBy = $currentUser->Id;
                    $documentVaultomAccess->UpdatedDate = date('Y-m-d H:i:s');
                    $documentVaultomAccess->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultomAccess->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultomAccess->save();

                    $documentVaultOMAccessHistory = new DocumentVaultOMAccessHistory();
                    $documentVaultOMAccessHistory->DocumentVaultOMAccessId = $documentVaultomAccess->ID;
                    $documentVaultOMAccessHistory->UserId = $currentUser->Id;
                    $documentVaultOMAccessHistory->UserEmail = $currentUser->Email;
                    $documentVaultOMAccessHistory->Access = 2;
                    $documentVaultOMAccessHistory->CreatedBy = $currentUser->Id;
                    $documentVaultOMAccessHistory->CreatedDate = date('Y-m-d H:i:s');
                    $documentVaultOMAccessHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVaultOMAccessHistory->HostedConfigurationId = $hostedConfigurationId;
                    $documentVaultOMAccessHistory->save();

                    $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
                    $DocumentVaultOMDDHistory->UserId = $currentUser->Id;
                    $DocumentVaultOMDDHistory->PropertyId = $property->Id;
                    $DocumentVaultOMDDHistory->DocumentRole = 'OM';
                    $DocumentVaultOMDDHistory->CreatedDate = date('Y-m-d H:i:s');
                    $DocumentVaultOMDDHistory->CreatedBy = $currentUser->Id;
                    $DocumentVaultOMDDHistory->ConfigurationId = $configurationData->ConfigurationId;
                    $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
                    $DocumentVaultOMDDHistory->save();

                    if($hostedConfigurationId != $confDataId) {
                        self::savePropertyConfigurationMapping($property->Id, $currentUser->Id, $confDataId, $hostedConfigurationId);
                    }
                }


                $leadAdminProperty = LeadAdminProperty::where('PropertyId', $property->Id)->where('UserId', $currentUser->Id)->first();
                if(!empty($leadAdminProperty) && $leadAdminProperty != null ) {
                    $leadAdminProperty->StatusId = 13;
                    $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                    $leadAdminProperty->HostedConfigurationId = $hostedConfigurationId;
                    $leadAdminProperty->save();
                }  else {
                    $leadAdminProperty = new LeadAdminProperty();
                    $leadAdminProperty->PropertyId = $property->Id;
                    $leadAdminProperty->AdminId = $currentUser->Id;
                    $leadAdminProperty->UserId = $currentUser->Id;
                    $leadAdminProperty->PriorityId = 0;
                    $leadAdminProperty->StatusId = 13;
                    $leadAdminProperty->TopProspect = 0;
                    $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                    $leadAdminProperty->HostedConfigurationId = $hostedConfigurationId;
                    $leadAdminProperty->save();
                }
            }

            $userCurrentData = User::where('Id', $currentUser->Id)->first();
            $userCurrentData->HasPendingOM = 1;
            $userCurrentData->save();

            $encrypt_arr = array();
            $encrypt_arr['PropertyId'] = $property->Id;
            $encrypt_arr['UserId'] = $currentUser->Id;
            $encrypt_arr['type'] = 'om';
            $encrypt_arr['ConfigurationId'] = $configurationData->ConfigurationId;

            $encrypt_param = base64_encode(json_encode($encrypt_arr));
            $dashboardUrl = env('DASHBOARD_URL').'/';

            $has = strlen($property->HashId);
            if($has == 7){
                $string = $property->PrivateURLSlug.'/'.'0'.$property->HashId;
            }else{
                $string = $property->PrivateURLSlug.'/'.$property->HashId;
            }

            //$string = $property->PrivateURLSlug.'/'.$property->HashId;

            $dd_request_url = $dashboardUrl.'securitylevelrequest/'.$string.'/'.$encrypt_param;
            $email_subject = "Offering Materials Request | {$property->Name}";
            $companyName = '';
            if($currentUser->CompanyId != "" && $currentUser->CompanyId != null ) {
                $companies = Companies::where('Id', $currentUser->CompanyId)->first();
                if( !empty( $companies ) && $companies != null ) {
                    $companyName = $companies->CompanyName;
                }
            }
            if($companyName == '') {
                $companyName = 'Individual';
            } 

            $userQuery = User::query();
            $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
            $userQuery->where('Users.Id', $currentUser->Id);
            $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
            $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
            $userData = $userQuery->first();
        
            if($userData->IndustryRoleId == 1)
            {
                $industry_role = IndustryRole::select('IndustryRole.Role')->where('Id',$userData->IndustryRoleId)->first();
                $investor_type = InvestorType::select('InvestorType.Type')->where('Id',$userData->InvestorTypeId)->first();    
                $requestor_type = $industry_role['Role'].' | '.$investor_type['Type'];
            }
            elseif($userData->IndustryRoleId == 2){
                $industry_role = IndustryRole::select('IndustryRole.Role')->where('Id',$userData->IndustryRoleId)->first();
                $broker_type = BrokerType::select('BrokerType.Type')->where('Id',$userData->BrokerTypeId)->first();    
                $requestor_type = $industry_role['Role'].' | '.$broker_type['Type'];
            }
            else {
                $industry_role = IndustryRole::select('IndustryRole.Role')->where('Id',$userData->IndustryRoleId)->first();
                $requestor_type = $industry_role['Role'];
            }

            $content      = "<p><b>Requestor Name: </b>".$currentUser->FirstName." ".$currentUser->LastName."<br>";
            
            $current_time = date("M d, Y h:i A",strtotime(getallheaders()['current_date_time']));

            $content     .= "<b>Requestor Company: </b>{$companyName}<br>";
            $content     .="<b>Requestor Type: </b>{$requestor_type}<br><br>";
            $content .= "<b>Listing Name: </b>{$property->Name}<br>";
            $content .= "<b>Requested Date/Time: </b>".$current_time."<br>";
            $content .= "<b>Approve/Deny: </b><a href='{$dd_request_url}' >Click here</a></p>";
            if ( !empty($agent_to)) {
                $email = new Email();
                $message = $email->email_content('', $content, true);
                $email->sendEmail( $email_subject, $agent_to, $message);
            }

            return
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => array('access'=>true)
                ];
        }

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function documentVaultStructure(Request $request){
        $this->currentUser = $request->user();
        $URL = '';
        if(isset($request->agreement_profile) && $request->agreement_profile == '1'){
            $this->currentUser = $request->user();
        
            $databaseConnection = new DatabaseConnection();
            $configurationData = $databaseConnection->getConfiguration();

            $property = Property::query();
                $userIP = $this->getIp();

            if(is_numeric($request->propertyId)) {
                $property->where('property.Id', $request->propertyId);
            } else {
                $property->where('property.PrivateURLSlug', $request->propertyId);
            }
            $property = $property->first();
            $hostedConfigData   = $databaseConnection->getPropertyConfiguration($property->Id);
            $browse_from_mobile = 0;
            if ( isset( $request->device ) && $request->device != '') {
                $browse_from_mobile = $request->device;
            }
            if(!empty($property) && $property != null ) {
                $oeplPropertyTracker = new OeplPropertyTracker();
                if(!empty($currentUser)) {
                    $oeplPropertyTracker->UserId = $currentUser->Id;
                    $oeplPropertyTracker->LoggedIn = 1;
                } else {
                    $oeplPropertyTracker->LoggedIn = 0;
                }
                $oeplPropertyTracker->PropertyId = $property->Id;
                $oeplPropertyTracker->CreatedDateTime = date('Y-m-d H:i:s');
                $oeplPropertyTracker->SessionId = '';
                $oeplPropertyTracker->UserIp = $request->user_ip;
                $oeplPropertyTracker->BrowseFromMobile = $browse_from_mobile;
                $oeplPropertyTracker->ConfigurationId = $configurationData->ConfigurationId;
                $oeplPropertyTracker->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                if($oeplPropertyTracker->save()) {
                }
            }
        }
        

        if(isset($request->downloadCA) && $request->downloadCA == '1'){
            if($request->documentType != 'Public'){
                $Agreement = new Agreement();
                $caResult = $Agreement->confidencialAgreementDownload($this->currentUser->Id,$request->propertyId,$request->user_ip,$request->copyOnEmail);
                $URL =  $caResult;
            }
        }
        return $this->checkStatusDocumentAccess($request->propertyId,$this->currentUser->Id,$request->user_ip,$URL,$request->documentType);
    }

    /**
     * @param string $property_id
     * @param string $user_id
     * @param string $user_ip
     * @param array $URL
     * @param string $documentType
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function checkStatusDocumentAccess($property_id = '',$user_id = '',$user_ip = '',$URL = array(),$documentType = ''){
        $databaseConnection = new DatabaseConnection();
        $currentUser = auth()->guard('api')->user();

        $masterProperty = $databaseConnection->getPropertyConfiguration($property_id);
        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property_id)->where('UserId', $user_id)->first();

        $property = Property::where('Id', $property_id)->first();
        $documentAccess = self::checkOMAccess($property_id, $currentUser);
        if (!empty($propertyUserRelationship) && $propertyUserRelationship != null) {

            if ($documentType != 'Due Diligence' && $documentAccess != 'Approved') {

                if($propertyUserRelationship->DuediligenceRequestStatus == 2) {
                    return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'data'      => [],
                        'URL'       => '',
                        'html'      => '',
                        'type'      => 'ddrequested',
                        'downloadCA'   => '',
                    ], 200);
                }

            }

            if($propertyUserRelationship->NDASigned == 0){
                if($documentType == 'Public'){
                    $document = new Document();
                    return response()->json(
                        [
                            'status'    => 'success',
                            'message'   => [],
                            'errors'    => [],
                            'data'      => [],
                            'URL'       => '',
                            'html'      => $document->propertyDocumentPreview( $property_id, $user_id, $user_ip,$documentType),
                            'type'      => 'Approved',
                            'downloadCA'   => $URL,
                        ], 200);
                }else{
                    return $this->agreementController($property_id,$user_id,$user_ip,$URL);
                }
            } else if($documentType == 'Due Diligence'){
                $html = "";
                
                $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property_id)->where('UserId', $user_id)->first();

                $omrequestedStatus = self::checkOMRequested($property, $currentUser);
                
                $highSecurity = false;
                $dddocflag = false;
                if( !empty($propertyUserRelationship) && $propertyUserRelationship != null ) {

                    if($propertyUserRelationship->DuediligenceRequestStatus == 3) {

                        
                        
                        if ($propertyUserRelationship->DocumentRole == 'High') {

                            $highSecurity = true;
                            $document = new DocumentHigh();

                            $html1 = $document->propertyDocumentPreview ( $property_id, $user_id, $user_ip, 'Due Diligence');
                            $html = $html1['html'];
                            $dddocflag = $html1['dd_flag'];
                        } else {

                            $highSecurity = false;                            
                            $document = new Document();
                            $html = $document->propertyDocumentPreview ( $property_id, $user_id, $user_ip, 'Due Diligence');
                        }
                    }

                }
                if($omrequestedStatus == 'Yes') {
                    return response()->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'html' => $html,
                        'data' => $propertyUserRelationship,
                        'type'      => 'OmRequestednotsedndd',
                        'downloadCA'   => '',
                        'high_security' => $highSecurity,
                        'dddocflag' => $dddocflag
                    ], 200);
                } else {
                    return response()->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'html' => $html,
                        'data' => $propertyUserRelationship,
                        'type'      => 'DD',
                        'downloadCA'   => $URL,
                        'high_security' => $highSecurity,
                        'dddocflag' => $dddocflag
                    ], 200);
                }
            } else if($documentAccess == 'Approved'){
                $document = new Document();
                return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'data'      => [],
                        'URL'       => '',
                        'html'      => $document->propertyDocumentPreview( $property_id, $user_id, $user_ip,$documentType),
                        'type'      => 'Approved',
                        'downloadCA'   => $URL,
                    ], 200);
            } else if($documentAccess == 'Pending'){
                $array = array(
                        'property_id' => $property_id,
                        'currentUser' => $currentUser,
                );
                if ($property->PropertyStatusId == 4) {
                    $documentAccess = self::checkOMAccess($property_id, $currentUser);
                    if ($documentAccess == 'None') {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'OmRequested',
                                'downloadCA'   => $URL,
                            ], 200);
                    } else{
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'Pending',
                                'downloadCA'   => $URL,
                            ], 200);  
                    }
                }else{
                    $om = $this->addOMAccessToUser((object) $array);
                    if($om['status'] == "success" && $om['data']['access']) {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'Pending',
                                'downloadCA'   => $URL,
                            ], 200);
                    } else {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'agent-not-found',
                                'downloadCA'   => $URL,
                            ], 200);
                    }
                } 
            } else if ($documentAccess == 'Rejected') {
                if ($property->PropertyStatusId == 4) {
                    return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'data'      => [],
                        'URL'       => '',
                        'type'      => 'OmRequested',
                        'downloadCA'   => $URL,
                    ], 200);
                } else {
                    $array = array(
                        'property_id' => $property_id,
                        'currentUser' => $currentUser,
                    );
                    $om = $this->addOMAccessToUser((object) $array);
                    if($om['status'] == "success" && $om['data']['access']) {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'Pending',
                                'downloadCA'   => $URL,
                            ], 200);
                    } else {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'agent-not-found',
                                'downloadCA'   => $URL,
                            ], 200);
                    }
                }
            } else if($documentAccess == 'Requested'){
                return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'data'      => [],
                        'URL'       => '',
                        'type'      => 'Requested',
                        'downloadCA'   => $URL,
                    ], 200); 
            }else{
                $array = array(
                    'property_id' => $property_id,
                    'currentUser' => $currentUser,
                );
                if ($property->PropertyStatusId == 4) {
                    return response()->json(
                        [
                            'status'    => 'success',
                            'message'   => [],
                            'errors'    => [],
                            'data'      => [],
                            'URL'       => '',
                            'type'      => 'OmRequested',
                            'downloadCA'   => $URL,
                        ], 200);
                }else{
                    $om = $this->addOMAccessToUser((object) $array);
                    if($om['status'] == "success" && $om['data']['access']) {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'Pending',
                                'downloadCA'   => $URL,
                            ], 200);
                    } else {
                        return response()->json(
                            [
                                'status'    => 'success',
                                'message'   => [],
                                'errors'    => [],
                                'data'      => [],
                                'URL'       => '',
                                'type'      => 'agent-not-found',
                                'downloadCA'   => $URL,
                            ], 200);
                    }
                }
            }
        }else{
            if($documentType == 'Public'){
                $document = new Document();
                return response()->json(
                    [
                        'status'    => 'success',
                        'message'   => [],
                        'errors'    => [],
                        'data'      => [],
                        'URL'       => '',
                        'html'      => $document->propertyDocumentPreview( $property_id, $user_id, $user_ip,$documentType),
                        'type'      => 'Approved',
                        'downloadCA'   => $URL,
                    ], 200);
            }else{
                return $this->agreementController($property_id,$user_id,$user_ip,$URL);
            }
        }
    }

    /**
     * @param string $propertyID
     * @param array $user
     * @return string
     */
    public function checkOMAccessUnderContract($propertyID = '', $user = array()) {
        $directory = Directory::query();
        $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
        $directory->where('DatumDirectory.PropertyId', $propertyID);
        $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
        $directory->where('DatumDirectory.ParentId', 0);
        $directory->where('DocumentVaultOMAccess.UserId', $user->Id);
        $directory->select(['DocumentVaultOMAccess.*']);
        $directoryomaccess = $directory->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
        if(!empty($directoryomaccess) && $directoryomaccess != null) {
            if($directoryomaccess->Access == 1) {
                $access = 'None';
            } elseif ($directoryomaccess->Access == 2) {
                $access = 'Requested';
            } elseif($directoryomaccess->Access == 3) {
                $access = 'Approved';
            } elseif ($directoryomaccess->Access == 4 ) {
                $access = 'None';
            } else {
                $access = 'None';
            }
        } else {
             $access = 'None';
        }
        return $access;
    }

    /**
     * @param string $propertyID
     * @param array $user
     * @return string
     */
    public function checkOMAccess ($propertyID = '', $user = array()) {
        $userQuery = User::query();
        $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $userQuery->where('Users.Id', $user->Id);
        $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.Username", "Users.Email", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId","UserContactMapping.ConfigurationId"]);
        $user = $userQuery->first();

        $directory = Directory::query();
        $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
        $directory->where('DatumDirectory.PropertyId', $propertyID);
        $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
        $directory->where('DatumDirectory.ParentId', 0);
        $directory->where(function ($q) use ($user) {
           $q->orWhere('DocumentVaultOMAccess.UserId', $user->Id);
           $q->orWhere('DocumentVaultOMAccess.UserEmail', $user->Email); 
        });
        $directory->select(['DocumentVaultOMAccess.*']);
        $directoryomaccess = $directory->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
        $access = 'None';

        if(!empty($directoryomaccess) && $directoryomaccess != null) {
            if($directoryomaccess->Access == 1) {
                $access = 'Pending';
            } elseif ($directoryomaccess->Access == 2) {
                $access = 'Requested';
            } elseif($directoryomaccess->Access == 3) {
                $access = 'Approved';
            } elseif ($directoryomaccess->Access == 4 ) {
                $access = 'Rejected';
            }

            if($directoryomaccess->Access == 1 || $directoryomaccess->Access == 2 || $directoryomaccess->Access == 4) {
                $directory = Directory::query();
                $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
                $directory->where('DatumDirectory.PropertyId', $propertyID);
                $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
                $directory->where('DatumDirectory.ParentId', 0);
                $directory->where('DocumentVaultOMAccess.IndustryRoleId', $user->IndustryRoleId);
                $directory->where('DocumentVaultOMAccess.Access', 3);
                $directory->select(['DocumentVaultOMAccess.*']);
                $directoryomaccess = $directory->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
                if(!empty($directoryomaccess) && $directoryomaccess != null) {
                    if($directoryomaccess->Access == 1) {
                        $access = 'Pending';
                    } elseif ($directoryomaccess->Access == 2) {
                        $access = 'Requested';
                    } elseif($directoryomaccess->Access == 3) {
                        $access = 'Approved';
                    } elseif ($directoryomaccess->Access == 4 ) {
                        $access = 'Rejected';
                    }
                }
            }
        } else{
            $directory = Directory::query();
            $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
            $directory->where('DatumDirectory.PropertyId', $propertyID);
            $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
            $directory->where('DatumDirectory.ParentId', 0);
            $directory->where('DocumentVaultOMAccess.IndustryRoleId', $user->IndustryRoleId);
            $directory->where('DocumentVaultOMAccess.Access', 3);
            $directory->select(['DocumentVaultOMAccess.*']);
            $directoryomaccess = $directory->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
            if(!empty($directoryomaccess) && $directoryomaccess != null) {
                if($directoryomaccess->Access == 1) {
                    $access = 'Pending';
                } elseif ($directoryomaccess->Access == 2) {
                    $access = 'Requested';
                } elseif($directoryomaccess->Access == 3) {
                    $access = 'Approved';
                } elseif ($directoryomaccess->Access == 4 ) {
                    $access = 'Rejected';
                }
            } else {
                $access = 'None';
            }
        }
        $property = Property::where('Id', $propertyID)->first();
        if ($property->PropertyStatusId == 4) {
            return self::checkOMAccessUnderContract($propertyID, $user);
        } else {
            return $access;    
        }
    }

    /**
     * @param Request $request
     */
    public function downloadVaultStructure(Request $request) {
        $databaseConnection = new DatabaseConnection();
        $configurationData = $databaseConnection->getConfiguration();
        $currentUser = auth()->guard('api')->user();

        $documenType = "OM";
        
        if( isset($request->type) && $request->type != "") {
            if($request->type == "Offering Memorandum") {
                $documenType = "OM";
            }
            if($request->type == "Due Diligence") {
                $documenType = "DD";
            }
            if($request->type == "Public") {
                $documenType = "Public";
            }
        }

        $documentBlobPath = env('DOCUMENT_VAULT_URL', '');
        $property = Property::where("Id", $request->property_id)->first();

        $siteUrl = $this->removeHttp(getallheaders()['site_url']);
        $currentUser = $request->user();
        $main_path = public_path('download_document');

        if(isset($request->agreement_profile) && $request->agreement_profile == '1'){
            $this->currentUser = $request->user();
            $property = Property::query();
            $userIP = $this->getIp();

            if(is_numeric($request->property_id)) {
                $property->where('Property.Id', $request->property_id);
            } else {
                $property->where('Property.PrivateURLSlug', $request->property_id);
            }
            $property = $property->first();
            $hostedConfigData   = $databaseConnection->getPropertyConfiguration($property->Id);
            $browse_from_mobile = 0;
            if ( isset( $request->device ) && $request->device != '') {
                $browse_from_mobile = $request->device;
            }
            if(!empty($property) && $property != null ) {
                $oeplPropertyTracker = new OeplPropertyTracker();
                if(!empty($currentUser)) {
                    $oeplPropertyTracker->UserId = $currentUser->Id;
                    $oeplPropertyTracker->LoggedIn = 1;
                } else {
                    $oeplPropertyTracker->LoggedIn = 0;
                }
                $oeplPropertyTracker->PropertyId = $property->Id;
                $oeplPropertyTracker->CreatedDateTime = date('Y-m-d H:i:s');
                $oeplPropertyTracker->SessionId = '';
                $oeplPropertyTracker->UserIp = $request->user_ip;
                $oeplPropertyTracker->BrowseFromMobile = $browse_from_mobile;
                $oeplPropertyTracker->ConfigurationId = $configurationData->ConfigurationId;
                $oeplPropertyTracker->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $oeplPropertyTracker->save();
            }
        }
        
        $directoryFullPath = $main_path.'/'.$siteUrl.'/'.$property->PrivateURLSlug.'/'.$currentUser->Id;
        
        if ( isset( $request->filename ) && !empty($request->filename) ) {
            $documentVaultSavedArray = $request->filename;
            $data = self::documentVaultDataSave( $property->Id, $currentUser->Id, $documenType, $documentVaultSavedArray, $request->user_ip);
        }
    }

    /**
     * @param string $url
     * @return string
     */
    public function fileUrl ($url = "") {
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));

        return $parts['scheme'] . '://' .$parts['host'] .implode('/', array_map('rawurlencode', $path_parts));
    }

    /**
     * @param string $propertyId
     * @param string $userId
     * @param string $documentType
     * @param array $fileIdFileNameArray
     * @param $ip
     */
    public function documentVaultDataSave ( $propertyId = "", $userId = "", $documentType = "", $fileIdFileNameArray = array(), $ip) {
        $databaseConnection = new DatabaseConnection();
        $configurationData = $databaseConnection->getConfiguration();
        $hostedConfigData   = $databaseConnection->getPropertyConfiguration($propertyId);
        $currentUser = auth()->guard('api')->user();
        
        if($documentType == "Public") {
            if ( !empty ( $fileIdFileNameArray ) ) {
                foreach ( $fileIdFileNameArray as $key => $value ) {
                    $documentVault = new DocumentVault();
                    $documentVault->PropertyId = $propertyId;
                    $documentVault->DownloadDateTime = date('Y-m-d H:i:s');
                    $documentVault->UserId = $userId;
                    $documentVault->DocumentType = $documentType;
                    $documentVault->FilePath = $value['filename'];
                    $documentVault->DirectoryFileId = $value['directoryFileId'];
                    $documentVault->ConfigurationId = $configurationData->ConfigurationId;
                    $documentVault->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                    $documentVault->save();
                }
            }
            $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $propertyId)->where('UserId', $userId)->first();
            
            if(!empty($propertyUserRelationship) && $propertyUserRelationship != null ) {
                $propertyUserRelationship->UserId = $userId;
                $propertyUserRelationship->PropertyId = $propertyId;
                $propertyUserRelationship->DocumentRole = "Public";
                $propertyUserRelationship->NdaIp = $ip;
                $propertyUserRelationship->ConfigurationId = $configurationData->ConfigurationId;
                $propertyUserRelationship->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $propertyUserRelationship->NDASignedDateTime = date('Y-m-d H:i:s');
                $propertyUserRelationship->DuediligenceRequestStatus = 1;
                $propertyUserRelationship->DuediligenceRequestDateTime = date('Y-m-d H:i:s');
                $propertyUserRelationship->NDASigned = 0;
                $propertyUserRelationship->DDApproved = 0;
            } else {
                $wpOsdUserPropertiesRelationship = new WpOsdUserPropertiesRelationship();
                $wpOsdUserPropertiesRelationship->UserId = $userId;
                $wpOsdUserPropertiesRelationship->PropertyId = $propertyId;
                $wpOsdUserPropertiesRelationship->DocumentRole = "Public";
                $wpOsdUserPropertiesRelationship->NdaIp = $ip;
                $wpOsdUserPropertiesRelationship->ConfigurationId = $configurationData->ConfigurationId;
                $wpOsdUserPropertiesRelationship->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $wpOsdUserPropertiesRelationship->NDASignedDateTime = date('Y-m-d H:i:s');
                $wpOsdUserPropertiesRelationship->DuediligenceRequestStatus = 1;
                $wpOsdUserPropertiesRelationship->DuediligenceRequestDateTime = date('Y-m-d H:i:s');
                $wpOsdUserPropertiesRelationship->NDASigned = 0;
                $wpOsdUserPropertiesRelationship->DDApproved = 0;
                $wpOsdUserPropertiesRelationship->save();  
            }


            $leadAdminProperty = LeadAdminProperty::where('PropertyId', $propertyId)->where('UserId', $currentUser->Id)->first();

            if(!empty($leadAdminProperty) && $leadAdminProperty != null ) {
                $leadAdminProperty->StatusId = 13;
                $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                $leadAdminProperty->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $leadAdminProperty->save();
            }  else {
                $leadAdminProperty = new LeadAdminProperty();
                $leadAdminProperty->PropertyId = $propertyId;
                $leadAdminProperty->AdminId = $currentUser->Id;
                $leadAdminProperty->UserId = $currentUser->Id;
                $leadAdminProperty->PriorityId = 0;
                $leadAdminProperty->StatusId = 13;
                $leadAdminProperty->TopProspect = 0;
                $leadAdminProperty->ConfigurationId = $configurationData->ConfigurationId;
                $leadAdminProperty->HostedConfigurationId = $hostedConfigData->ConfigurationId;
                $leadAdminProperty->save();
            }

        } else {
            if ( !empty ( $fileIdFileNameArray ) ) {
                $documentVaultDataSaved = [];
                
                foreach ( $fileIdFileNameArray as $key => $value ) {
                    $dType = 'OM';

                    if( $value['file_type'] == "Offering Memorandum") {
                        $dType = "OM";
                    }
                    
                    if( $value['file_type'] == "Due Diligence") {
                        $dType = "DD";
                    }

                    if( $value['file_type'] == "Public") {
                        $dType = "Public";
                    }

                    if( $value['file_type'] == "High") {
                        $dType = "High";
                    }

                    $documentVaultDataSaved[] = array(
                        'PropertyId' => $propertyId,
                        'DownloadDateTime' => date('Y-m-d H:i:s'),
                        'UserId' => $userId,
                        'DocumentType' => $dType,
                        'FilePath' => $value['filename'],
                        'DirectoryFileId' => (isset($value['directoryFileId']) && $value['directoryFileId'] != 0) ? $value['directoryFileId'] : null ,
                        'ConfigurationId' => $configurationData->ConfigurationId,
                        'HostedConfigurationId' => $hostedConfigData->ConfigurationId
                    );
                }
                
                $documentVault = DocumentVault::insert($documentVaultDataSaved);
            }
        }

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removedZipFileFromAzure(Request $request) {
        
        $request->validate([
            'zipfile_name' => 'required',
        ]);

        $azuredocname = env('AZURE_STORAGE_NAME_ZIP', '');
        $azurekey = env('AZURE_STORAGE_KEY_ZIP', '');
        $azurecontainer = env('AZURE_STORAGE_CONTAINER_ZIP', '');
        $azureurl = env('AZURE_STORAGE_URL_ZIP', '');

        \Config::set('filesystems.disks.azure.name', $azuredocname);
        \Config::set('filesystems.disks.azure.key', $azurekey);
        \Config::set('filesystems.disks.azure.container', $azurecontainer);
        \Config::set('filesystems.disks.azure.url', $azureurl);

        if (isset( $request->zipfile_name ) && $request->zipfile_name != "" ) {
            $disk = \Storage::disk('azure');
            if ($disk->exists($request->zipfile_name)) {
                $disk->delete($request->zipfile_name);
                return response()->json([
                    'status' => 'success',
                    'message' => 'File was deleted successfully.',
                    'errors' => [],
                    'data' => [],
                ], 200);
            } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'File not exist',
                    'errors' => [],
                    'data' => [],
                ], 200);
            }
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'File was deleted failed.',
                'errors' => [],
                'data' => [],
                'account'   => '1',
            ], 200);
        }
        
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadExistingCA(Request $request) {
        $databaseConnection = new DatabaseConnection();
        $currentUser = $request->user();
        $Agreement = new Agreement();
        $caResult = $Agreement->downloadedConfidencialAgreement($currentUser->Id, $request->docID, $request->user_ip);
        return response()->json(
            [
                'status'    => 'success',
                'message'   => [],
                'errors'    => [],
                'data'      => [],
                'URL'       => $caResult['URL'],
                'FileName'       => $caResult['FileName'],
                'type'      => 'agreement'
            ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function caDownloadDashboard(Request $request) {
        $NdaTracker = NdaTracker::where('DocId', $request->docID)->first();
        
        if( !empty($NdaTracker)) {
            $Agreement = new Agreement();
            $caResult = $Agreement->caDownloadDashboard($NdaTracker->UserId, $NdaTracker->DocId, $request->user_ip);
            return response()->json(
            [
                'status'    => 'success',
                'message'   => [],
                'errors'    => [],
                'data'      => [],
                'URL'       => $caResult['URL'],
                'FileName'       => $caResult['FileName'],
                'type'      => 'agreement'
            ], 200);
        } else {
            return response()->json(
            [
                'status'    => 'failed',
                'message'   => [],
                'errors'    => [],
                'data'      => [],
                'URL'       => URL::to('/ca-404'),
                'type'      => $agrement
            ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function sendOMRequest(Request $request){
        $databaseConnection = new DatabaseConnection();
        $currentUser = auth()->guard('api')->user();

        $array = array(
            'property_id' => $request->property_id,
            'currentUser' => $currentUser,
        );
        $om = $this->addOMAccessToUser((object) $array);
        if($om['status'] == "success" && $om['data']['access']) {
            return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    'data'      => [],
                    'URL'       => '',
                    'type'      => 'Pending'
                ], 200);
        } else {
            return response()->json(
                [
                    'status'    => 'success',
                    'message'   => [],
                    'errors'    => [],
                    'data'      => [],
                    'URL'       => '',
                    'type'      => 'agent-not-found',
                    'downloadCA'   => $URL,
                ], 200);
        }
    }

    /**
     * @param $property
     * @param $user
     * @return string
     */
    public function checkOMRequested ($property, $user) {
        $directory = Directory::where("PropertyId", $property->Id)->where("DirectoryName", 'Offering Memorandum')->first();

        $userQuery = User::query();
        $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $userQuery->where('Users.Id', $user->Id);
        $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.Username", "Users.Email", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId","UserContactMapping.ConfigurationId"]);
        $user = $userQuery->first();
        
        if( !empty($directory) && $directory != null ) {
            $documentVaultomAccess = DocumentVaultomAccess::where('UserId', $user->Id)->where('DatumDirectoryId', $directory->Id)->where('Access', 2)->orderBy('Id', 'desc')->first();

            if ( !empty($documentVaultomAccess) && $documentVaultomAccess != null ) {
                if ($property->PropertyStatusId != 4) {
                    $directory = Directory::query();
                    $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
                    $directory->where('DatumDirectory.PropertyId', $property->Id);
                    $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
                    $directory->where('DatumDirectory.ParentId', 0);
                    $directory->where('DocumentVaultOMAccess.IndustryRoleId', $user->IndustryRoleId);
                    $directory->where('DocumentVaultOMAccess.Access', 3);
                    $directory->select(['DocumentVaultOMAccess.*']);
                    $directoryomaccess1 = $directory->first();

                    if ( !empty($directoryomaccess1) && $directoryomaccess1 != null ) {
                        return 'No';
                    } else {
                        return 'Yes';
                    }
                } else {
                    return 'Yes';
                }
            } else {
                return 'No';
            }
        } else {
            return 'No';
        }
    }

    /**
     * @param $propertyId
     * @param $userId
     * @param $configurationId
     * @param $HostedConfigurationId
     */
    public function savePropertyConfigurationMapping($propertyId, $userId, $configurationId, $HostedConfigurationId) {

        $propetyConfigurationMapping = new PropetyConfigurationMapping();

        $propetyConfigurationMapping->PropertyId             = $propertyId;
        $propetyConfigurationMapping->ConfigurationId        = $configurationId;
        $propetyConfigurationMapping->HostedConfigurationId  = $HostedConfigurationId;
        $propetyConfigurationMapping->UserId                 = $userId;
        $propetyConfigurationMapping->CreatedBy              = $userId;
        $propetyConfigurationMapping->CreatedDate            = date('Y-m-d H:i:s');
        $propetyConfigurationMapping->save();

    }

    /**
     * @param Request $request
     * @return array
     */
    public function checkSequrityRoleAccess( Request $request ) {
        $users = $request->user();
        if ($request->type == 'Offering Memorandum') {
            $lead = WpOsdUserPropertiesRelationship::where('PropertyId', $request->property_id)->where('UserId', $users->Id)->first();
            if ( !empty($lead) && $lead != null ) {

                $documentAccess = self::checkOMAccess($request->property_id, $users);

                if ( $lead->DocumentRole != 'Public' && $documentAccess == 'Approved') {

                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'access' => true
                    ];

                } else {

                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'access' => false
                    ];

                }
            }
        } else if ($request->type == 'Due Diligence') {

            $lead = WpOsdUserPropertiesRelationship::where('PropertyId', $request->property_id)->where('UserId', $users->Id)->first();

            if ( !empty($lead) && $lead != null ) {

                if ( $lead->DuediligenceRequestStatus == 3) {
                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'access' => true
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'access' => false
                    ];
                }

            } else {

                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'access' => false
                ];
            }
        } else {

            return [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => [],
                'access' => true
            ];
            
        }
    }

    public function userNotifications($property_id = '',$userId = ''){
        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();

        $propertyAgent = PropertyAgents::where('PropertyId', $property_id)->where('EntityStatusId', 1)->get();

        $agent_to = [];
        foreach ( $propertyAgent as $key => $value ) {
            $agent = User::join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->where('Users.Id', $value->AgentId)->where('UserContactMapping.UserTypeId', '!=', 4)->first();
            $agent_to[] = $agent->Id;
        }

        if(!empty($agent_to)){
            foreach ($agent_to as $key => $value) {
                $userNotificationsData = array(
                    "UserId"                => $userId,
                    "IsRead"                => 0,
                    "EntityStatusId"        => 1,
                    "TypeId"                => OMVaultAccessRequest, //Om
                    "PropertyId"            => null,
                    "RelatedUserId"         => $value,
                    "ConfigurationId"       => $configurations->ConfigurationId,
                    "AdditionalData"        => null,
                    "HostedConfigurationId" => $configurations->ConfigurationId,
                    "CreatedDate"           => date('Y-m-d H:i:s'),
                    "InsightId"             => null,
                );
                $UserNotifications = new UserNotifications($userNotificationsData);
                $UserNotifications->save();
            }
        }
    }
}
