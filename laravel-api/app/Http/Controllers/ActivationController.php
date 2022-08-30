<?php

namespace App\Http\Controllers;

use App\Congigurations;
use App\PluginActivation;
use App\Refund;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Helpers\DatabaseConnection;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\isEmpty;
use App\Traits\Common;
use App\Companies;

/**
 * Class ActivationController
 * @package App\Http\Controllers
 */
class ActivationController extends Controller
{
    use Common;
    /**
     * Login user and create token
     *
     * @param  [string] activation_key
     * @return [array] activation data
     */
    public function store(Request $request) {
        if(( isset(getallheaders()['site_url']) && getallheaders()['site_url'] !='' ) ) {
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);

            $congigurations = Congigurations::where('ActivationKey', $request->activation_key)
                ->where('SiteUrl', $siteUrl)
                ->where('Status', 1)
                ->first();

            if( !empty($congigurations) && $congigurations != null ) {
                $congigurations->ActiveDate = date('Y-m-d H:i:s');
                $congigurations->ActiveDate = date('Y-m-d H:i:s');
                $congigurations->save();
                
                return response()->json(
                [
                    'status'=>'success',
                    'message' => '',
                    'errors' => [],
                    'data'=> $congigurations
                ], 200);
            } else {
                return response()
                    ->json(
                        [
                            'status'=>'failed',
                            'message' => 'Activation key is not registered. Please contact to administrator.',
                            'errors' => [],
                            'data'=> []
                        ], 200);
            }
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPluginActivationKey( Request $request ) {
        $profileContainerName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_CONTAINER');
        $profileContainerLGName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        $profileContainerSMName = env('AZURE_STORAGE_USER_PROFILE_PICTURE_SM_CONTAINER');
        $azurePath = env('AZURE_STORAGE_URL');
        $user = Auth::guard('api')->user();
        $userData = [];

        if(!empty($user) && $user != null) {

            $query = User::query();
            $query->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
            $query->where('Users.Id', '=', $user->Id);
            $query->with('getAcquisitioncriteriaContactRelation');
            $query->with('getUserAddressDetailsRelation');
            $data = $query->get(array('Users.Id', 'Users.FirstName', "Users.LastName", "Users.Email", "Users.ProfileImage", "Users.Title", "Users.CompanyId", "Users.ExchangeStatusId", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId", "Users.NextUpdateDate", "Users.IsContactCreatedByDashboard"));

            if($data[0]->CompanyId != '' && $data[0]->CompanyId != null ) {
                $company = Companies::where('Id', $data[0]->CompanyId)->first();
                if(!empty($company)) {
                    $data[0]->CompanyName = $company->CompanyName;
                } else {
                    $data[0]->CompanyName = "";
                }
            }
            if( $data[0]->ProfileImage != '' && $data[0]->ProfileImage != null) {
                $data[0]->ProfileSMImage = $azurePath.'/'.$profileContainerSMName.'/'.$data[0]->ProfileImage;
                $data[0]->ProfileImage = $azurePath.'/'.$profileContainerLGName.'/'.$data[0]->ProfileImage;
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

            if($data[0]->NextUpdateDate <= date('Y-m-d H:i:s') || $data[0]->NextUpdateDate == null) {
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
                    $data[0]->IsupdateDashbord = false;
                    $data[0]->isNextUpdateDate = $isNextUpdateDate;
                    $data[0]->IsContactCreatedByDashboard = false;
                    $userUpdate = User::where('Id', $data[0]->Id)->first();
                    $userUpdate->ExchangeStatusId = null;
                    $userUpdate->save();
                }
            }
            $data[0]->isNextUpdateDate = $isNextUpdateDate;
            $userData = $data[0];
        }

        if( isset( getallheaders()['activation_key'] ) && getallheaders()['activation_key'] != '') {
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);

            $congiguration = Congigurations::where('ActivationKey', getallheaders()['activation_key'])
                ->where('Status', 1)
                ->first();

            if( !empty($congiguration) ) {
                return response()
                    ->json(
                        [
                            'status'=>'success',
                            'message' => '',
                            'errors' => [],
                            'data'=> $congiguration,
                            "user_data" => $userData,
                        ], 200);

            } else {
                return response()
                    ->json(
                        [
                            'status'=>'failed',
                            'message' => 'Plugin key is not valid please contact administrator',
                            'errors' => [],
                            'data'=> []
                        ], 200);
            }

        } else {
            return response()
                ->json(
                    [
                        'status'=>'failed',
                        'message' => 'Plugin activation key is not found. Please try again',
                        'errors' => [],
                        'data'=> []
                    ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPage(Request $request){
        $siteUrl = $this->removeHttp(getallheaders()['site_url']);

        $congigurations = Congigurations::where('ActivationKey', $request->activation_key)
            ->where('SiteUrl', $siteUrl)
            ->where('Status', 1)
            ->first();
        if( !empty($congigurations) && $congigurations != null ) {
            $congigurations->ActiveDate = date('Y-m-d H:i:s');
            $congigurations->save();
            return response()
            ->json(
                [
                    'status'=>'success',
                    'message' => '',
                    'errors' => [],
                ], 200);
        }else{
            return response()
            ->json(
                [
                    'status'=>'success',
                    'message' => '',
                    'errors' => [],
                ], 200);
        }
    }
}
