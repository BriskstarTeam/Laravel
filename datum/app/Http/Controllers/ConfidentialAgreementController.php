<?php

namespace App\Http\Controllers;

use App\Mail\Email;
use App\WpOsdUserPropertiesRelationship;
use App\DueDiligenceRequestHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Property\Property;
use App\Property\NdaTracker;
Use App\OeplPropertyTracker;
use App\Directory;
use App\Database\DatabaseConnection;
use App\Traits\Common;
use App\User;
use App\IndustryRole;
use App\BrokerType;
use App\InvestorType;
use App\Companies;
use App\Property\PropertyAgents;
use App\Property\DocumentVaultomAccess;
use App\Property\DocumentVaultOMAccessHistory;
use Illuminate\Support\Facades\DB;
use App\Document\Document;
use App\Documentvaultomddhistory;
use App\PropetyConfigurationMapping;

class ConfidentialAgreementController extends Controller {
    use Common;
    /**
     * @var array
     */
    public $currentUser = [];

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkCaSignUser(Request $request) {
        $this->currentUser = $request->user();
        $request->validate([
            'property_id' => 'required|numeric',
        ]);

        $databaseConnection = new DatabaseConnection();
        $property = Property::where('Id', $request->property_id)->first();
        $user_email = $this->currentUser->Email;

        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property->Id)
            ->where('UserId', $this->currentUser->Id)
            ->first();

        if (!empty($propertyUserRelationship) && $propertyUserRelationship != null) {

            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $propertyUserRelationship
                ], 200);

        } else {

            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
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
    public function sendDDRequest( Request $request ) {
        $currentUser = $request->user();
        $request->validate([
            'property_id' => 'required|numeric',
            'docuement_role' => 'required'
        ]);

        $userQuery = User::query();
        $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $userQuery->where('Users.Id', $currentUser->Id);
        $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
        $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.CreatedDate", "Users.Guid", "Users.ProfileImage", "Users.IsSuperAuthorizedAccount", "Users.Title", "Users.Username", "Users.LinkedIn", "Users.ExchangeStatusId", "Users.CompanyId", "Users.Email", "Users.Bio", "Users.IsContactCreatedByDashboard", "Users.NextUpdateDate", "Users.SubscriptionTypeId", "Users.TeamSubCategoryId", "Users.Password", "Users.IsRegistrationCompleted", "Users.IsSocial", "Users.SocialMediaId", "Users.IsParentSiteUser", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "UserContactMapping.SubscriptionTypeId", "UserContactMapping.ConfigurationId"]);
        $userData = $userQuery->first();
        $databaseConnection = new DatabaseConnection();
        $configurationData              = $databaseConnection->getConfiguration();
        $userId                         = $currentUser->Id;
        $nonHostedConfiguration         = $configurationData->ConfigurationId;

        $nda_signed = date('Y-m-d H:i:s');
        $user_email = $currentUser->Email;
        $user_ip    = $request->user_ip;

        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $request->property_id)->where('UserId', $currentUser->Id)->first();
        $property = Property::where('Id', $request->property_id)
            ->first();

        
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
        
        $propertyConfig = $databaseConnection->getPropertyConfiguration($property->Id);
        $hostedConfigurationId = $propertyConfig->ConfigurationId;
        $confDataId            = $configurationData->ConfigurationId;

        $propertyAgent = PropertyAgents::where('PropertyId', $request->property_id)->where('IsNotificationEnabled', 1)->where('EntityStatusId', 1)->get();

        $agent_to = array();
        $i = 0;
        foreach ( $propertyAgent as $key => $value ) {
            $agent = User::join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->where('Users.Id', $value->AgentId)->where('UserContactMapping.UserTypeId', '!=', 4)->first();
            $agent_to[$i]['email'] = $agent->Email;
            $agent_to[$i]['name']  = $agent->FirstName.' '.$agent->LastName;
            $i++;
        }
        
        $encrypt_arr = array();
        $encrypt_arr['PropertyId']      = $property->Id;
        $encrypt_arr['UserId']          = $currentUser->Id;
        $encrypt_arr['type']            = 'dd';
        $encrypt_arr['ConfigurationId'] = $confDataId;

        $encrypt_param  = base64_encode(json_encode($encrypt_arr));
        $dashboardUrl   = env('DASHBOARD_URL').'/';

        $has = strlen($property->HashId);
        if($has == 7){
            $string         = $property->PrivateURLSlug.'/'.'0'.$property->HashId;
        }else{
            $string         = $property->PrivateURLSlug.'/'.$property->HashId;
        }

        //$string         = $property->PrivateURLSlug.'/'.$property->HashId;
        $dd_request_url = $dashboardUrl.'securitylevelrequest/'.$string.'/'.$encrypt_param;
        
        $email_subject  = "Due Diligence Request | {$property->Name}";
        $companies      = Companies::where('Id', $currentUser->CompanyId)->first();
        $companyName    = '';

        if( !empty( $companies ) && $companies != null ) {
            $companyName = $companies->CompanyName;
        }
        if($companyName == '') {
            $companyName = 'Individual';
        } 
        $content      = "<p><b>Requestor Name: </b>".$currentUser->FirstName." ".$currentUser->LastName."<br>";

        $current_time = date("M d, Y h:i A",strtotime(getallheaders()['current_date_time']));
        
        $content     .= "<b>Requestor Company: </b>{$companyName}<br>";
        $content     .="<b>Requestor Type: </b>{$requestor_type}<br><br>";
        $content     .= "<b>Listing Name: </b>{$property->Name}<br>";
        $content     .= "<b>Requested Date/Time: </b>".$current_time."<br>";
        $content     .= "<b>Approve/Deny: </b><a href='{$dd_request_url}' >Click Here</a></p>";
        if( !empty($agent_to)) {
            $email   = new Email();
            $message = $email->email_content('', $content, true);
            $email->sendEmail( $email_subject, $agent_to, $message);
        }

        if( !empty($propertyUserRelationship) && $propertyUserRelationship != null ) {
            $BeforeDocumentRole = $propertyUserRelationship->DocumentRole;
            $documentAccessRole = 'Due Diligence';
            $documentAccessRole = $propertyUserRelationship->DocumentRole;
            $propertyUserRelationship->DuediligenceRequestStatus   = 2;
            $propertyUserRelationship->DuediligenceRequestDateTime = $nda_signed;
            $propertyUserRelationship->NDASentEmail                = $user_email;
            $propertyUserRelationship->DocumentRole                = $documentAccessRole;
            $propertyUserRelationship->save();

            $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
            $DocumentVaultOMDDHistory->UserId          = $userId;
            $DocumentVaultOMDDHistory->PropertyId      = $request->property_id;
            $DocumentVaultOMDDHistory->DocumentRole    = $documentAccessRole;
            $DocumentVaultOMDDHistory->CreatedDate     = date('Y-m-d H:i:s');
            $DocumentVaultOMDDHistory->CreatedBy       = $userId;
            $DocumentVaultOMDDHistory->ConfigurationId = $confDataId;
            $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
            $DocumentVaultOMDDHistory->save();

            $userCurrentData = User::where('Id', $userId)->first();
            $userCurrentData->HasPendingDD = 1;
            $userCurrentData->UpdatedBy = $userId;
            $userCurrentData->UpdatedDate = date('Y-m-d H:i:s');
            $userCurrentData->save();

            if($hostedConfigurationId != $confDataId) {
                self::savePropertyConfigurationMapping($request->property_id, $userId, $confDataId, $hostedConfigurationId, $documentAccessRole);
            }
            $dueDiligenceRequestHistory = new DueDiligenceRequestHistory(); 
            $dueDiligenceRequestHistory->saveDueDiligenceRequestHistory(
                $propertyUserRelationship->Id,
                $userId,
                $BeforeDocumentRole,
                null,
                $user_ip,
                $confDataId,
                $hostedConfigurationId
            );

        } else {
            $documentAccessRole = 'Public';
            $propertyUserRelationship = new WpOsdUserPropertiesRelationship([
                'UserId'                      => $userId,
                'PropertyId'                  => $request->property_id,
                'DuediligenceRequestStatus'   => 2,
                'DuediligenceRequestDateTime' => $nda_signed,
                'NDASignedDateTime'           => date('Y-m-d H:i:s'),
                'DocumentRole'                => $documentAccessRole,
                'NDASigned'                   => 0,
                'NDAIP'                       => $user_ip,
                'NDAPDF'                      => '',
                'DocId'                       => '',
                'ConfigurationId'             => $confDataId,
                'HostedConfigurationId'       => $hostedConfigurationId
            ]);
            $propertyUserRelationship->save();

            $DocumentVaultOMDDHistory = new Documentvaultomddhistory();
            $DocumentVaultOMDDHistory->UserId          = $userId;
            $DocumentVaultOMDDHistory->PropertyId      = $request->property_id;
            $DocumentVaultOMDDHistory->DocumentRole    = 'Due Diligence';
            $DocumentVaultOMDDHistory->CreatedDate     = date('Y-m-d H:i:s');
            $DocumentVaultOMDDHistory->CreatedBy       = $userId;
            $DocumentVaultOMDDHistory->ConfigurationId = $confDataId;
            $DocumentVaultOMDDHistory->HostedConfigurationId = $hostedConfigurationId;
            $DocumentVaultOMDDHistory->save();

            $userCurrentData = User::where('Id', $userId)->first();
            $userCurrentData->HasPendingDD = 1;
            $userCurrentData->UpdatedBy = $userId;
            $userCurrentData->UpdatedDate = date('Y-m-d H:i:s');
            $userCurrentData->save();
            if($hostedConfigurationId != $confDataId) {
                self::savePropertyConfigurationMapping($request->property_id, $userId, $confDataId, $hostedConfigurationId, $documentAccessRole);
            }

            $dueDiligenceRequestHistory = new DueDiligenceRequestHistory(); 
            $dueDiligenceRequestHistory->saveDueDiligenceRequestHistory(
                $propertyUserRelationship->Id,
                $userId,
                $documentAccessRole,
                null,
                $user_ip,
                $confDataId,
                $hostedConfigurationId
            );
        }

        return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => $propertyUserRelationship
            ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDDRequest( Request $request ) {

        $html = '';
        $request->validate([
            'property_id' => 'required',
        ]);

        $this->currentUser = $request->user();
        $user = $this->currentUser;

            $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $request->property_id)->where('UserId', $this->currentUser->Id)->first();
            if( !empty($propertyUserRelationship) && $propertyUserRelationship != null ) {

                if($propertyUserRelationship->DuediligenceRequestStatus == 3) {
                    $document = new Document();
                    $html = $document->propertyDocumentPreview ( $request->property_id, $user->Id, $request->user_ip, 'Due Diligence');
                }
            }

            return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'html' => $html,
                'data' => $propertyUserRelationship
            ], 200);
        
    }

    /**
     * @param $propertyId
     * @param $userId
     * @param $configurationId
     * @param $HostedConfigurationId
     * @param $documentRole
     */
    public function savePropertyConfigurationMapping($propertyId, $userId, $configurationId, $HostedConfigurationId, $documentRole) {

        $propetyConfigurationMapping = new PropetyConfigurationMapping();
        $propetyConfigurationMapping->PropertyId             = $propertyId;
        $propetyConfigurationMapping->ConfigurationId        = $configurationId;
        $propetyConfigurationMapping->HostedConfigurationId  = $HostedConfigurationId;
        $propetyConfigurationMapping->UserId                 = $userId;
        $propetyConfigurationMapping->DocumentRole           = $documentRole;
        $propetyConfigurationMapping->CreatedBy              = $userId;
        $propetyConfigurationMapping->CreatedDate            = date('Y-m-d H:i:s');
        $propetyConfigurationMapping->save();

    }
}
