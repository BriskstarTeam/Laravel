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
use App\InsightsPageAccess;
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
use App\DocumentVault;
use App\Directory;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use App\Mail\Email;
use App\Traits\EncryptionDecryption;
Use App\OeplPropertyTracker;
use App\States;
use App\FeaturedListingMapping;
use Illuminate\Support\Facades\Storage;

use Illuminate\Pagination\Paginator;

use Illuminate\Pagination\LengthAwarePaginator;
use Hashids\Hashids;

/**
 * Class InsightAccessController
 * @package App\Http\Controllers
 */
class InsightAccessController extends Controller {
    use AgentApi, Common, EncryptionDecryption;

    /**
     * @var int
     */
    public $configurationId = 0;

    /**
     * InsightAccessController constructor.
     */
    public function __construct() {
        $databaseConnection = new DatabaseConnection();
        $configuration = $databaseConnection->getConfiguration();
        $this->configurationId = $configuration->ConfigurationId;
    }

    /**
     * @param array $data
     * @return string
     */
    public function checkUserPageAccessData($data = null){
        $currentUser = auth()->guard('api')->user();

        $databaseConnection = new DatabaseConnection();
        $currentConnection  = $databaseConnection->getConnectionName();
        $configurations     = $databaseConnection->getConfiguration();
        if(empty($currentUser)){
            $checkIndustryRole  = InsightsPageAccess::query();
            $checkIndustryRole->where('HasDefaultAccess', 1);
            $checkIndustryRole->where(function ($q){
                $q->where('IndustryRoleId','=', 1);
                $q->orWhere('IndustryRoleId','=', 2);
                $q->orWhere('IndustryRoleId','=', 3);
            });
            $checkIndustryRole->Where('ConfigurationId', $configurations->ConfigurationId);
            $checkIndustryRole = $checkIndustryRole->get();
        
            if(count($checkIndustryRole) == 3){
                return 'Approved';
            }else{
                return 'userNotlogin';
            }

        }else{
            $userQuery = User::query();
            $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
            $userQuery->where('Users.Id', $currentUser->Id);
            $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
            $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.Username", "Users.Email", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId","UserContactMapping.ConfigurationId"]);
            $userData = $userQuery->first();

            $IndustryRoleId     = $userData->IndustryRoleId;
            $checkIndustryRole  = InsightsPageAccess::query();
            $checkIndustryRole->where('IndustryRoleId', $IndustryRoleId);
            $checkIndustryRole->Where('ConfigurationId', $configurations->ConfigurationId);
            $checkIndustryRole = $checkIndustryRole->first();
            if(empty($checkIndustryRole)){
                
                $IndustryRoleId     = $currentUser->IndustryRoleId;
                $checkIndustryRole  = InsightsPageAccess::query();
                $checkIndustryRole->Where('ConfigurationId', $configurations->ConfigurationId);
                $checkIndustryRole->where(function($q) use ($currentUser) {
                    $q->where('UserId',$currentUser->Id)
                    ->orWhere('Email', $currentUser->Email);
                });
                $checkIndustryRole = $checkIndustryRole->first();
                if(empty($checkIndustryRole)){
                    $type = $this->checkRequestStatus($currentUser->Id,$data);
                }else{
                    $type = $this->checkRequestStatus($currentUser->Id,$data,$checkIndustryRole->Access,$checkIndustryRole);
                }

            }else{
                if($checkIndustryRole->HasDefaultAccess == 0){
                    $IndustryRoleId     = $userData->IndustryRoleId;
                    $checkIndustryRole  = InsightsPageAccess::query();
                    $checkIndustryRole->where('UserId', $currentUser->Id);
                    $checkIndustryRole->Where('ConfigurationId', $configurations->ConfigurationId);
                    $checkIndustryRole = $checkIndustryRole->first();

                    if(empty($checkIndustryRole)){
                        $type = $this->checkRequestStatus($currentUser->Id,$data,$checkIndustryRole);

                    }else{

                        $type = $this->checkRequestStatus($currentUser->Id,$data,$checkIndustryRole->Access,$checkIndustryRole);
                    }
                }else{
                   
                    $IndustryRoleId     = $userData->IndustryRoleId;

                    $checkIndustryRoledata  = InsightsPageAccess::query();
                    $checkIndustryRoledata->where('UserId', $currentUser->Id);
                    $checkIndustryRoledata->Where('ConfigurationId', $configurations->ConfigurationId);
                    $checkIndustryRoledata = $checkIndustryRoledata->first();

                    if(empty($checkIndustryRoledata)){
                        
                        $type = $this->checkRequestStatus($currentUser->Id,$data,3,$checkIndustryRole);
                    }else{
                        if($checkIndustryRoledata->Access == 2){
                            $type = $this->checkRequestStatus($currentUser->Id,$data,3,$checkIndustryRole);
                        }else{
                            $type = $this->checkRequestStatus($currentUser->Id,$data,$checkIndustryRoledata->Access,$checkIndustryRoledata);
                        }
                    }
                }
            }

            //}

            return $type;
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkUserPageAccess(Request $request){
        $type = $this->checkUserPageAccessData($request);
        return response()->json(
            [
                'status'            => 'success',
                'type'              => $type,
                'errors'            => [],
                'data'              => [],
            ], 200);
    }

    /**
     * @param string $userId
     * @param string $postData
     * @param string $Access
     * @param array $data
     * @return string
     */
    public function checkRequestStatus($userId= '',$postData='',$Access='',$data = null){
        if(!empty($data)){
            switch ($Access) {
                case '1':
                case '4':
                    if(isset($postData) && $postData->is_request == 1){
                        $userSaved  = new InsightsPageAccess();
                        $user       = $userSaved->addUpdatePageAccess($userId,$postData,$data->Id);
                        $type = 'Requested';
                    }else{
                         $type = 'Pending';
                    }
                    break;
                case '2':
                    $type = 'Requested';
                    break;
                case '3':
                    $type = 'Approved';
                    break;
                default:
                    $type = 'Pending';
                    // code...
                    break;
            }
        }else{
            if(isset($postData) && $postData->is_request == 1){
                $userSaved  = new InsightsPageAccess();
                $user       = $userSaved->addUpdatePageAccess($userId);
                $type = 'Requested';
            }else{
                $type = 'Pending';
            }
        }
        return $type;
    }
}