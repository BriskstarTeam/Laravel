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
 * Class PropertyController
 * @package App\Http\Controllers
 */
class PropertyController extends Controller {
    use AgentApi, Common, EncryptionDecryption;

    /**
     * @var int
     */
    public $configurationId = 0;

    /**
     * PropertyController constructor.
     */
    public function __construct() {
        $databaseConnection = new DatabaseConnection();
        $configuration = $databaseConnection->getConfiguration();
        $this->configurationId = $configuration->ConfigurationId;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        
        return response()
            ->json(
                [
                    'status'            => 'success',
                    'message'           => [],
                    'errors'            => [],
                    'data'              => $this->getProperty($request),
                    'map_property'      => $this->getProperty($request ,'1'),
                ], 200);
    }

    /**
     * @param array $request
     * @param string $property_map_listing
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getProperty($request  = [],$property_map_listing = ''){
        $userIP = $this->getIp();
        $notShowingData = '';
        $states_search_data = '';
        
        $databaseConnection = new DatabaseConnection();

        $configurations = $databaseConnection->getConfiguration();
        $currentUser = auth()->guard('api')->user();
        try {
        
        // Location Search by location
            $statesArray = [];
            if(isset($request->search_by_location) && !empty($request->search_by_location) && $request->search_by_location != null) {
                $states = States::query();
                $states->whereIn('StateName', $request->search_by_location);
                $states->select('Code', 'StateName');
                $states = $states->get();
                if(!empty($states) && $states != null ) {
                    foreach ( $states as $key => $value ) {
                        $statesArray[] = $value->Code;
                    }
                }
            }

            if(isset($request->property_search) && !empty($request->property_search) && $request->property_search != null) {
                $states = States::query();
                $states->where('StateName', $request->property_search);
                $states->select('Code', 'StateName');
                $states_search = $states->first();

                if(!empty($states_search) && $states_search != null ) {
                    $states_search_data = $states_search->Code;
                }else{
                    $states_search_data = $request->property_search;
                }
            }

            $property_filter = [];
            if( !empty ( $request->property_filter ) && $request->property_filter != '' ) {
                $property_filter = json_decode($request->property_filter);
            }

            $property_type = [];
            if( !empty ( $request->property_type ) && $request->property_type != '' ) {
                $property_type = json_decode($request->property_type);

                $main_filter_query = AcquisitionSubType::query();
                $main_filter_query->select(["AcquisitionCriteriaSubType.AcquisitionTypeId"]);
                $main_filter_query->whereIn('AcquisitionCriteriaSubType.Id',$property_type);
                $main_filter_query->groupBy('AcquisitionCriteriaSubType.AcquisitionTypeId');
                $main_filter_query_data = $main_filter_query->get();

                $d_sub_name = [];
                foreach ($main_filter_query_data as $key => $value) {
                    $d_sub_name[] = $value->AcquisitionTypeId;
                }
                $array_diff = array_merge(array_diff($property_filter,$d_sub_name),array_diff($d_sub_name,$property_filter));
            }

            $PropertyIds = [];
            if (isset($request->day_on_market_from) || isset($request->day_on_market_to) ) {
                $sql2 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id)) AS DaysOnMarkets";
                $query1 = Property::query();
                $query1->select(['Property.Id', DB::raw($sql2)]);
                $query1->orderBy('DaysOnMarkets', 'DESC');
                if ( $request->day_on_market_from != '' && $request->day_on_market_to != '' ) {
                    $db2 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id))";
                    $query1->where(DB::raw($db2),'>=',$request->day_on_market_from);
                    $db1 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id))";
                    $query1->where(DB::raw($db1),'<=',$request->day_on_market_to);
                    $pids = $query1->get();
                    if ( $pids->isEmpty() ) {
                        $notShowingData = 1;
                    }
                }else if($request->day_on_market_from != ''){
                    $db2 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id))";
                    $query1->where(DB::raw($db2),'>=',$request->day_on_market_from);
                    $pids = $query1->get();
                }else if($request->day_on_market_to != ''){
                    $db2 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id))";
                    $query1->where(DB::raw($db2),'<=',$request->day_on_market_to);
                    $pids = $query1->get();
                }else{
                    $pids = $query1->get();
                }
                if ( !empty ($pids) && $pids != null ) {
                    foreach ($pids as $key => $value ) {
                        $PropertyIds[] = $value->Id;
                    }
                }
            }   
            
            //DB::enableQueryLog();
            $query = Property::query();        
            $query->with('getPropertyImages');
            $query->leftJoin('SaveStatus', 'SaveStatus.Id', '=', 'property.SaveStatusId');
            $query->leftJoin('PropertyStatus', 'PropertyStatus.Id', '=', 'Property.PropertyStatusId');
            $query->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyFinancialDetails', 'PropertyFinancialDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyPhysicalDetails', 'PropertyPhysicalDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('BuildingClass', 'BuildingClass.Id', '=', 'PropertyPhysicalDetails.BuildingClassId');
            $query->leftJoin('PropertyListingDetails', 'PropertyListingDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyPressRelease', 'PropertyPressRelease.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyTenancy', 'PropertyTenancy.PropertyId', '=', 'Property.Id');
            $query->leftJoin('AcquisitionCriteriaPropertyRelation', 'AcquisitionCriteriaPropertyRelation.PropertyId', '=', 'Property.Id');

            $query->leftJoin('AcquisitionCriteriaSubType', 'AcquisitionCriteriaSubType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId');
            $query->leftJoin('PropertySalesDetailsStatus', 'PropertySalesDetailsStatus.PropertyId', '=', 'Property.Id');

            $query->leftJoin('AcquisitionCriteriaType', 'AcquisitionCriteriaType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId'  );
            
            $query->leftJoin('PropertyAgents', 'PropertyAgents.PropertyId', '=', 'Property.Id');
            $query->leftJoin('Users', 'Users.Id', '=', 'PropertyAgents.AgentId');
            $query->leftJoin('NonHostPropertyMapping', 'Property.Id', '=', 'NonHostPropertyMapping.PropertyId');
            
            $confId = $configurations->ConfigurationId;

            $query->where(function ($q) use ($confId) {
                $q->where(function ($q1) use ($confId) {
                    $q1->where('Property.SaveStatusId', 2);
                    $q1->where('Property.ConfigurationId', $confId);
                });
                $q->orWhere(function ($q2) use ($confId) {
                    $q2->where('NonHostPropertyMapping.SaveStatusId', 2);
                    $q2->where('NonHostPropertyMapping.ConfigurationId', $confId);
                });
            });
            $query->where('property.EntityStatusId', 1);
            $query->where('AcquisitionCriteriaType.ModuleId', 1);

            //$query->where('Property.ConfigurationId',$configurations->ConfigurationId);
            
            if( !empty ( $property_type ) && !empty ( $property_filter ) ) {
                if(empty($array_diff)){
                    $query->whereIn('AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId', $property_type);
                    $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
                }else{
                    $query->where(function ($q) use ($request,$array_diff,$property_type) {
                        $q->whereIn('AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId', $array_diff);
                        $q->orWhere('AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId', $property_type);
                    });
                    $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
                }
            
            } else if( !empty ( $property_type ) ) {
                $query->whereIn('AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId', $property_type);
                $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
            }else if( !empty ( $property_filter ) ) {
                $query->whereIn('AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId', $property_filter);
                $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
            }else{
                $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
            }

            if( isset($request->is_featured_home) && $request->is_featured_home != '' && $request->is_featured_home == 1) {
                $query->leftJoin('PropertyFeaturedListings', 'Property.Id', '=','PropertyFeaturedListings.PropertyId');
                $query->leftJoin('FeaturedListingMapping', 'PropertyFeaturedListings.FeaturedHomeTypeId', '=','FeaturedListingMapping.Id');
                $query->where('PropertyFeaturedListings.DisplayOrder','!=', 0);
                $query->where('PropertyFeaturedListings.ConfigurationId','=', $configurations->ConfigurationId);
            }

            if( isset($request->featured_closed_deal) && $request->featured_closed_deal != '' && $request->featured_closed_deal == 1) {
                $query->where('Property.FeaturedHome', '1');
            }
            if( !empty($PropertyIds)) {
                $query->whereIn('Property.Id', $PropertyIds);
            }
            if( $notShowingData == 1 ) {
                $query->where('Property.Id','<',1);
            }
            if (isset($request->property_status) && $request->property_status != '') {
                if( is_array($request->property_status) ) {
                    $query->whereIn('Property.PropertyStatusId', $request->property_status);
                    $query->where('Property.PrivateList', 0);
                } else {
                    if ($request->property_status == 5) {
                        $query->where('Property.PropertyStatusId', '=', 5);
                    } else {
                        $query->whereIn('Property.PropertyStatusId', $request->property_status);
                        $query->where('Property.PrivateList', 0);
                    }
                }
            } else {
                $query->where('Property.PropertyStatusId', '!=', 5);
                $query->where('Property.PrivateList', 0);
            }

            if (isset($request->property_status) && $request->property_status == '5') {

                if ( isset ( $request->askin_min) || isset ( $request->askin_max) ) {
                    if (isset ( $request->include_unprice) || $request->include_unprice != '') {
                        if ( $request->askin_min != '' && $request->askin_max != '' ) {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyListingDetails.SalesPrice', '>=', $request->askin_min);
                                $q->where('PropertyListingDetails.SalesPrice', '<=', $request->askin_max);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }elseif ($request->askin_min != '') {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyListingDetails.SalesPrice', '>=', $request->askin_min);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }elseif ($request->askin_max != '') {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyListingDetails.SalesPrice', '<=', $request->askin_max);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }
                    }else{
                        if ($request->askin_min != '' && $request->askin_max != '' ) {
                            $query->where('PropertyListingDetails.SalesPrice', '>=', $request->askin_min);
                            $query->where('PropertyListingDetails.SalesPrice', '<=', $request->askin_max);
                        }elseif ($request->askin_min != '') {
                            $query->where('PropertyListingDetails.SalesPrice', '>=', $request->askin_min);
                        }elseif ($request->askin_max != '') {
                            $query->where('PropertyListingDetails.SalesPrice', '<=', $request->askin_max);
                        }                
                        $query->where('PropertyFinancialDetails.IsUnpriced', '!=', '1');
                    }
                }
            }else{
                if ( isset ( $request->askin_min) || isset ( $request->askin_max) ) {
                    if (isset ( $request->include_unprice) || $request->include_unprice != '') {
                        if ( $request->askin_min != '' && $request->askin_max != '' ) {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyFinancialDetails.AskingPrice', '>=', $request->askin_min);
                                $q->where('PropertyFinancialDetails.AskingPrice', '<=', $request->askin_max);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }elseif ($request->askin_min != '') {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyFinancialDetails.AskingPrice', '>=', $request->askin_min);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }elseif ($request->askin_max != '') {
                            $query->where(function ($q) use ($request) {
                                $q->where('PropertyFinancialDetails.AskingPrice', '<=', $request->askin_max);
                                $q->orWhere('PropertyFinancialDetails.IsUnpriced', 1);
                            });
                        }

                    }else{
                        if ( $request->askin_min != '' && $request->askin_max != '' ) {
                            $query->where('PropertyFinancialDetails.AskingPrice', '>=', $request->askin_min);
                            $query->where('PropertyFinancialDetails.AskingPrice', '<=', $request->askin_max);
                        }elseif ($request->askin_min != '') {
                            $query->where('PropertyFinancialDetails.AskingPrice', '>=', $request->askin_min);
                        }elseif ($request->askin_max != '') {
                            $query->where('PropertyFinancialDetails.AskingPrice', '<=', $request->askin_max);
                        }                
                        $query->where('PropertyFinancialDetails.IsUnpriced', '!=', '1');
                    }
                }
            }

            if ( isset($request->property_search) && $request->property_search != '' ) {
                $query->where(function ($q) use ($request,$states_search_data) {
                    $q->orWhere('Property.Name', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('Property.PropertyContent', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Address1', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Address2', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.City', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.State', 'like', '%' .$states_search_data . '%');
                    $q->orWhere('PropertyAddress.Country', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Zipcode', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('AcquisitionCriteriaType.Name', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('AcquisitionCriteriaSubType.Name', 'like', '%' .$request->property_search . '%');
                    $q->orWhere(DB::raw("CONCAT(LTRIM(RTRIM(Users.FirstName)),' ',LTRIM(RTRIM(Users.LastName)))"), 'like', '%' .$request->property_search. '%');
                });
            }

            if ( isset($request->keyword) && $request->keyword != '' ) {
                $query->where(function ($q) use ($request) {
                    $q->orWhere('Property.Name', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('Property.PropertyContent', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.Address1', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.Address2', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.City', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.State', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.Country', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('PropertyAddress.Zipcode', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('AcquisitionCriteriaType.Name', 'like', '%' .$request->keyword . '%');
                    $q->orWhere('AcquisitionCriteriaSubType.Name', 'like', '%' .$request->keyword . '%');
                    $q->orWhere(DB::raw("CONCAT(LTRIM(RTRIM(Users.FirstName)),' ',LTRIM(RTRIM(Users.LastName)))"), 'like', '%' .$request->keyword. '%');
                });
            }

            $sql1 = "(SELECT dbo.fn_GetPropertyDaysOnMarket(Property.Id)) AS DaysOnMarkets";

            if ( (isset($request->lat) && $request->lat != "" ) && ( isset($request->long) && $request->long != "" ) && ( isset($request->radius_in_km) && $request->radius_in_km != '' )) {
                $sql2 = "( 6371 * acos( cos( radians(" . $request->lat . ") ) * cos( radians( PropertyAddress.Latitude ) ) * cos( radians( PropertyAddress.Longitude ) - radians(" . $request->long . ") ) + sin( radians(" . $request->lat . ") ) * sin(radians(PropertyAddress.Latitude)) ) ) as ps_distance_inkm";
                $sql3 = "( 6371 * acos( cos( radians(" . $request->lat . ") ) * cos( radians( PropertyAddress.Latitude ) ) * cos( radians( PropertyAddress.Longitude ) - radians(" . $request->long . ") ) + sin( radians(" . $request->lat . ") ) * sin(radians(PropertyAddress.Latitude)) ) )";
                $query->select([
                    'Property.Id', 'Property.CreatedDate', 'Property.ModifiedDate', 
                    'Property.Title', 'Property.FeaturedImage', 'Property.BannerImage', 
                    'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                    'PropertyStatus.Description AS ListingStatus', 
                    'SaveStatus.Description AS SaveStatus', 'Property.PropertyStatusId', 
                    'AcquisitionCriteriaType.Name AS PropertyType', 'Property.StatusDate', 
                    'Property.StatusDate AS PropertyStatusDate', 
                    "PropertyAddress.Latitude", "PropertyAddress.Longitude", 
                    "PropertyAddress.Address1", "PropertyAddress.Address2", 
                    "PropertyAddress.City", "PropertyAddress.State", 
                    "PropertyAddress.Country", "PropertyAddress.Zipcode",
                    "PropertyFinancialDetails.AskingPrice","PropertyFinancialDetails.IsConfidential",
                    "PropertyFinancialDetails.IsUnpriced","PropertySalesDetailsStatus.IsSalePrice",
                    "PropertyListingDetails.SalesPrice","PropertyFinancialDetails.AcresPrice",
                    "Property.ConfigurationId",
                    DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated'),
                    "PropertyPhysicalDetails.SqFeet",
                    "PropertyTenancy.Units",
                    "PropertyFinancialDetails.T12CapRate",
                    "Property.Occupancy",
                    "Property.HashId",
                    DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX)) AS CDDescription'),
                    DB::raw($sql2),
                    DB::raw($sql1)
                ]);
                $query->groupBy(
                    'Property.Id', 'property.CreatedDate', 
                    'Property.ModifiedDate', 'Property.Title', 
                    'Property.FeaturedImage', 'Property.BannerImage', 
                    'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                    'PropertyStatus.Description', 'SaveStatus.Description',
                    'Property.PropertyStatusId', 'AcquisitionCriteriaType.Name',
                    'Property.StatusDate', 'Property.StatusDate',"PropertyAddress.Latitude",
                    "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2",
                    "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", 
                    "PropertyAddress.Zipcode","PropertyFinancialDetails.AskingPrice",
                    "PropertyFinancialDetails.IsConfidential","PropertyFinancialDetails.IsUnpriced",
                    "PropertySalesDetailsStatus.IsSalePrice","PropertyListingDetails.SalesPrice",
                    "PropertyFinancialDetails.AcresPrice", "Property.ConfigurationId",
                    "PropertyPhysicalDetails.SqFeet",
                    "PropertyTenancy.Units",
                    "PropertyFinancialDetails.T12CapRate",
                    "Property.Occupancy",
                    "Property.HashId",
                    DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX))'),
                    //$sql1
                );
                $query->having( DB::raw($sql3),'<=',$request->radius_in_km );
            } else {
                if( isset($request->is_featured_home) && $request->is_featured_home != '' && $request->is_featured_home == 1) {
                    $query->select([
                        'Property.Id', 'Property.CreatedDate', 'Property.ModifiedDate', 
                        'Property.Title', 'Property.FeaturedImage', 'Property.BannerImage', 
                        'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                        'PropertyStatus.Description AS ListingStatus', 
                        'SaveStatus.Description AS SaveStatus', 'Property.PropertyStatusId', 
                        'AcquisitionCriteriaType.Name AS PropertyType', 'Property.StatusDate', 
                        'Property.StatusDate AS PropertyStatusDate', 
                        "PropertyAddress.Latitude", "PropertyAddress.Longitude", 
                        "PropertyAddress.Address1", "PropertyAddress.Address2", 
                        "PropertyAddress.City", "PropertyAddress.State", 
                        "PropertyAddress.Country", "PropertyAddress.Zipcode",
                        "PropertyFinancialDetails.AskingPrice","PropertyFinancialDetails.IsConfidential",
                        "PropertyFinancialDetails.IsUnpriced","PropertySalesDetailsStatus.IsSalePrice",
                        "PropertyListingDetails.SalesPrice","PropertyFinancialDetails.AcresPrice",
                        "Property.ConfigurationId",
                        "PropertyPhysicalDetails.SqFeet",
                        "PropertyTenancy.Units",
                        "PropertyFinancialDetails.T12CapRate",
                        "Property.Occupancy", 
                        "Property.HashId", 
                        "PropertyFeaturedListings.DisplayOrder",
                        DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated'),
                        DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX)) AS CDDescription'),
                        DB::raw($sql1)
                    ]);
                    $query->groupBy(
                        'Property.Id', 'property.CreatedDate', 
                        'Property.ModifiedDate', 'Property.Title', 
                        'Property.FeaturedImage', 'Property.BannerImage', 
                        'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                        'PropertyStatus.Description', 'SaveStatus.Description',
                        'Property.PropertyStatusId', 'AcquisitionCriteriaType.Name',
                        'Property.StatusDate', 'Property.StatusDate',"PropertyAddress.Latitude",
                        "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2",
                        "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", 
                        "PropertyAddress.Zipcode","PropertyFinancialDetails.AskingPrice",
                        "PropertyFinancialDetails.IsConfidential","PropertyFinancialDetails.IsUnpriced",
                        "PropertySalesDetailsStatus.IsSalePrice","PropertyListingDetails.SalesPrice",
                        "PropertyFinancialDetails.AcresPrice", "Property.ConfigurationId",
                        "PropertyPhysicalDetails.SqFeet",
                        "PropertyTenancy.Units",
                        "Property.Occupancy",
                        "Property.HashId",
                        "PropertyFinancialDetails.T12CapRate", "PropertyFeaturedListings.DisplayOrder",
                        DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX))'),
                        //$sql1
                    );
                } else {
                    $query->select([
                        'Property.Id', 'Property.CreatedDate', 'Property.ModifiedDate', 
                        'Property.Title', 'Property.FeaturedImage', 'Property.BannerImage', 
                        'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                        'PropertyStatus.Description AS ListingStatus', 
                        'SaveStatus.Description AS SaveStatus', 'Property.PropertyStatusId', 
                        'AcquisitionCriteriaType.Name AS PropertyType', 'Property.StatusDate', 
                        'Property.StatusDate AS PropertyStatusDate', 
                        "PropertyAddress.Latitude", "PropertyAddress.Longitude", 
                        "PropertyAddress.Address1", "PropertyAddress.Address2", 
                        "PropertyAddress.City", "PropertyAddress.State", 
                        "PropertyAddress.Country", "PropertyAddress.Zipcode",
                        "PropertyFinancialDetails.AskingPrice","PropertyFinancialDetails.IsConfidential",
                        "PropertyFinancialDetails.IsUnpriced","PropertySalesDetailsStatus.IsSalePrice",
                        "PropertyListingDetails.SalesPrice","PropertyFinancialDetails.AcresPrice",
                        "Property.ConfigurationId",
                        "PropertyPhysicalDetails.SqFeet",
                        "PropertyTenancy.Units",
                        "PropertyFinancialDetails.T12CapRate",
                        "Property.Occupancy",
                        "Property.HashId",
                        DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated'),
                        DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX)) AS CDDescription'),
                        DB::raw($sql1)
                    ]);
                    $query->groupBy(
                        'Property.Id', 'property.CreatedDate', 
                        'Property.ModifiedDate', 'Property.Title', 
                        'Property.FeaturedImage', 'Property.BannerImage', 
                        'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
                        'PropertyStatus.Description', 'SaveStatus.Description',
                        'Property.PropertyStatusId', 'AcquisitionCriteriaType.Name',
                        'Property.StatusDate', 'Property.StatusDate',"PropertyAddress.Latitude",
                        "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2",
                        "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", 
                        "PropertyAddress.Zipcode","PropertyFinancialDetails.AskingPrice",
                        "PropertyFinancialDetails.IsConfidential","PropertyFinancialDetails.IsUnpriced",
                        "PropertySalesDetailsStatus.IsSalePrice","PropertyListingDetails.SalesPrice",
                        "PropertyFinancialDetails.AcresPrice", "Property.ConfigurationId",
                        "PropertyPhysicalDetails.SqFeet",
                        "PropertyTenancy.Units",
                        "Property.Occupancy",
                        "Property.HashId",
                        "PropertyFinancialDetails.T12CapRate",
                        DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX))'),
                        //$sql1
                    );
                }
            }
            
            /**
             * Advance search
             * Property filter
             * PropertyAddress table filter
             * Latitude and Longitude
             * City and State
             */

            if(isset($request->search_by_location) && !empty($request->search_by_location) && $request->search_by_location != '') {
                $query->where(function ($q) use ($request, $statesArray) {

                    $q->orWhereIn('PropertyAddress.City', $request->search_by_location);

                    if(!empty($statesArray)) {
                        $q->orWhereIn('PropertyAddress.State', $statesArray);
                    }
                    $q->orWhereIn('PropertyAddress.Zipcode', $request->search_by_location);
                });
            }

            if ( (isset($request->asking_price_from) && $request->asking_price_from != '') &&  (isset($request->asking_price_to) && $request->asking_price_to != '') ) {
                $query->whereBetween('PropertyFinancialDetails.AskingPrice', [$request->asking_price_from, $request->asking_price_to]);
            }


            if (isset($request->price_sf_from) || isset($request->price_sf_to) ) {
                if ( $request->price_sf_from != '' && $request->price_sf_to != '' ) {
                    $query->where('PropertyFinancialDetails.PricePSF', '>=', $request->price_sf_from);
                    $query->where('PropertyFinancialDetails.PricePSF', '<=', $request->price_sf_to);
                }elseif ($request->price_sf_from != '') {
                    $query->where('PropertyFinancialDetails.PricePSF', '>=', $request->price_sf_from);
                }elseif ($request->price_sf_to != '') {
                    $query->where('PropertyFinancialDetails.PricePSF', '<=', $request->price_sf_to);
                }
            }

            if (isset($request->price_unit_from) || isset($request->price_unit_to) ) {
                if ( $request->price_unit_from != '' && $request->price_unit_to != '' ) {
                    $query->where('PropertyFinancialDetails.Price', '>=', $request->price_unit_from);
                    $query->where('PropertyFinancialDetails.Price', '<=', $request->price_unit_to);
                }elseif ($request->price_unit_from != '') {
                    $query->where('PropertyFinancialDetails.Price', '>=', $request->price_unit_from);
                }elseif ($request->price_unit_to != '') {
                    $query->where('PropertyFinancialDetails.Price', '<=', $request->price_unit_to);
                }
            }

            if (isset($request->price_acre_from) || isset($request->price_acre_to) ) {
                if ( $request->price_acre_from != '' && $request->price_acre_to != '' ) {
                    $query->where('PropertyFinancialDetails.AcresPrice', '>=', $request->price_acre_from);
                    $query->where('PropertyFinancialDetails.AcresPrice', '<=', $request->price_acre_to);
                }elseif ($request->price_acre_from != '') {
                    $query->where('PropertyFinancialDetails.AcresPrice', '>=', $request->price_acre_from);
                }elseif ($request->price_acre_to != '') {
                    $query->where('PropertyFinancialDetails.AcresPrice', '<=', $request->price_acre_to);
                }
            }

            if (isset($request->square_feet_from) || isset($request->square_feet_to) ) {
                if ( $request->square_feet_from != '' && $request->square_feet_to != '' ) {
                    $query->where('PropertyPhysicalDetails.SqFeet', '>=', $request->square_feet_from);
                    $query->where('PropertyPhysicalDetails.SqFeet', '<=', $request->square_feet_to);
                }elseif ($request->square_feet_from != '') {
                    $query->where('PropertyPhysicalDetails.SqFeet', '>=', $request->square_feet_from);
                }elseif ($request->square_feet_to != '') {
                    $query->where('PropertyPhysicalDetails.SqFeet', '<=', $request->square_feet_to);
                }
            }

            if (isset($request->unit_from) || isset($request->unit_to) ) {
                if ( $request->unit_from != '' && $request->unit_to != '' ) {
                    $query->where('PropertyTenancy.Units', '>=', $request->unit_from);
                    $query->where('PropertyTenancy.Units', '<=', $request->unit_to);
                }elseif ($request->unit_from != '') {
                    $query->where('PropertyTenancy.Units', '>=', $request->unit_from);
                }elseif ($request->unit_to != '') {
                    $query->where('PropertyTenancy.Units', '<=', $request->unit_to);
                }
            }

            if (isset($request->walt_from) || isset($request->walt_to) ) {
                if ( $request->walt_from != '' && $request->walt_to != '' ) {
                    $query->where('PropertyFinancialDetails.Walt', '>=', $request->walt_from);
                    $query->where('PropertyFinancialDetails.Walt', '<=', $request->walt_to);
                }elseif ($request->walt_from != '') {
                    $query->where('PropertyFinancialDetails.Walt', '>=', $request->walt_from);
                }elseif ($request->walt_to != '') {
                    $query->where('PropertyFinancialDetails.Walt', '<=', $request->walt_to);
                }
            }

            if (isset($request->cap_rate_from) || isset($request->cap_rate_to) ) {
                if ( $request->cap_rate_from != '' && $request->cap_rate_to != '' ) {
                    $query->where('PropertyFinancialDetails.Year1CapRate', '>=', str_replace('%','',$request->cap_rate_from));
                    $query->where('PropertyFinancialDetails.Year1CapRate', '<=', str_replace('%','',$request->cap_rate_to));
                }elseif ($request->cap_rate_from != '') {
                    $query->where('PropertyFinancialDetails.Year1CapRate', '>=', str_replace('%','',$request->cap_rate_from));
                }elseif ($request->cap_rate_to != '') {
                    $query->where('PropertyFinancialDetails.Year1CapRate', '<=', str_replace('%','',$request->cap_rate_to));
                }
            }
            if (isset($request->occupancy_from) || isset($request->occupancy_to) ) {

                if ( $request->occupancy_from != '' && $request->occupancy_to != '' ) {
                    $query->where('property.Occupancy', '>=', str_replace('%','',$request->occupancy_from) );
                    $query->where('property.Occupancy', '<=', str_replace('%','',$request->occupancy_to));
                }elseif ($request->occupancy_from != '') {
                    $query->where('property.Occupancy', '>=', str_replace('%','',$request->occupancy_from));
                }elseif ($request->occupancy_to != '') {
                    $query->where('property.Occupancy', '<=', str_replace('%','',$request->occupancy_to));
                }
            }

            if (isset($request->build_year_from) || isset($request->build_year_to) ) {
                if ( $request->build_year_from != '' && $request->build_year_to != '' ) {
                    $query->where('PropertyPhysicalDetails.YearBuilt', '>=', $request->build_year_from);
                    $query->where('PropertyPhysicalDetails.YearBuilt', '<=', $request->build_year_to);
                }elseif ($request->build_year_from != '') {
                    $query->where('PropertyPhysicalDetails.YearBuilt', '>=', $request->build_year_from);
                }elseif ($request->build_year_to != '') {
                    $query->where('PropertyPhysicalDetails.YearBuilt', '<=', $request->build_year_to);
                }
            }

            if (isset($request->acres_from) || isset($request->acres_to) ) {
                if ( $request->acres_from != '' && $request->acres_to != '' ) {
                    $query->where('PropertyPhysicalDetails.LotSize', '>=', $request->acres_from);
                    $query->where('PropertyPhysicalDetails.LotSize', '<=', $request->acres_to);
                }elseif ($request->acres_from != '') {
                    $query->where('PropertyPhysicalDetails.LotSize', '>=', $request->build_year_from);
                }elseif ($request->acres_to != '') {
                    $query->where('PropertyPhysicalDetails.LotSize', '<=', $request->acres_to);
                }
            }


            if (isset($request->tenancy) && !empty($request->tenancy)) {
                if(count($request->tenancy) > 1) {
                    $query->where(function ($q) use ($request) {
                        foreach( $request->tenancy as $key => $val ) {
                            if($key == 0) {
                                $q->where('PropertyTenancy.Tenancy', '=', $val);
                            } else {
                                $q->orwhere('PropertyTenancy.Tenancy', '=', $val);
                            }
                        }
                    });
                } else {
                    $query->where('PropertyTenancy.Tenancy', '=', $request->tenancy[0]);
                }
            }

            if (isset($request->building_class) && !empty($request->building_class)) {
                if(count($request->building_class) > 1) {
                    $query->where(function ($q) use ($request) {
                        foreach( $request->building_class as $key => $val ) {
                            if($key == 0) {
                                $q->where('BuildingClass.Description', '=', $val);
                            } else {
                                $q->orwhere('BuildingClass.Description', '=', $val);
                            }
                        }
                    });
                } else {
                    $query->where('BuildingClass.Description', '=', $request->building_class[0]);
                }
            }

            if ( isset($request->sorting) && $request->sorting == 'asking_price_high') {
                $query->orderBy('PropertyFinancialDetails.AskingPrice', 'DESC');
            } elseif ( isset($request->sorting) && $request->sorting == 'asking_price_low' ) {
                $query->orderBy('PropertyFinancialDetails.AskingPrice', 'ASC');
            } elseif ( isset($request->sorting) && $request->sorting == 'square_feet_high' ) {
                $query->orderBy('PropertyPhysicalDetails.SqFeet', 'DESC');
            } elseif ( isset($request->sorting) && $request->sorting == 'square_feet_low' ) {
                $query->orderBy('PropertyPhysicalDetails.SqFeet', 'ASC');
            } elseif ( isset($request->sorting) && $request->sorting == 'unites_high' ) {
                $query->orderBy('PropertyTenancy.Units', 'DESC');
            } elseif ( isset($request->sorting) && $request->sorting == 'unites_low' ) {
                $query->orderBy('PropertyTenancy.Units', 'ASC');
            } elseif ( isset($request->sorting) && $request->sorting == 'occupancy_high' ) {
                $query->orderBy('property.Occupancy', 'DESC');
            } elseif ( isset($request->sorting) && $request->sorting == 'occupancy_low' ) {
                $query->orderBy('property.Occupancy', 'ASC');
            }  elseif ( isset($request->sorting) && $request->sorting == 'cap_rate_high' ) {
                $query->orderBy('PropertyFinancialDetails.T12CapRate', 'DESC');
            } elseif ( isset($request->sorting) && $request->sorting == 'cap_rate_low' ) {
                $query->orderBy('PropertyFinancialDetails.T12CapRate', 'ASC');
            } elseif ( isset($request->sorting) && $request->sorting == 'days_on_market_new' ) {
                $query->orderBy('DaysOnMarkets', 'ASC');
            } elseif ( isset($request->sorting) && $request->sorting == 'days_on_market_high' ) {
                $query->orderBy('DaysOnMarkets', 'DESC');
            } else {
                //$query->orderBy('property.CreatedDate', 'DESC');
            }
            
            if( isset($request->is_featured_home) && $request->is_featured_home != '' && $request->is_featured_home == 1) {
                $query->orderBy('PropertyFeaturedListings.DisplayOrder', 'ASC');
            } else {
                if (isset($request->property_status) && $request->property_status != '') {
                    if( $request->property_status == 5 ) {
                        $query->orderBy('PropertyListingDetails.CloseDate', 'DESC');
                    } else {
                        $query->orderByRaw(
                            'CASE WHEN 
                            Property.PropertyStatusId = 3 
                                THEN 1 WHEN
                            Property.PropertyStatusId = 1
                                THEN 2 WHEN 
                            Property.PropertyStatusId = 2 
                                THEN 3 WHEN 
                            Property.PropertyStatusId = 4 
                                THEN 4 END ASC,
                            CASE WHEN  Property.PropertyStatusId = 3 THEN Property.StatusDate END ASC,
                            CASE WHEN  Property.PropertyStatusId = 3 THEN PropertyStatusId END ASC,
                            Property.CreatedDate DESC',
                        );
                    }
                } else {
                    $query->orderByRaw(
                            'CASE WHEN 
                            Property.PropertyStatusId = 3 
                                THEN 1 WHEN
                            Property.PropertyStatusId = 1
                                THEN 2 WHEN 
                            Property.PropertyStatusId = 2 
                                THEN 3 WHEN 
                            Property.PropertyStatusId = 4 
                                THEN 4 END ASC,
                            CASE WHEN  Property.PropertyStatusId = 3 THEN Property.StatusDate END ASC,
                            CASE WHEN  Property.PropertyStatusId = 3 THEN PropertyStatusId END ASC,
                            Property.CreatedDate DESC',
                        );
                }
            }

            if( isset($request->property_per_page) && $request->property_per_page != '' && $request->property_per_page == "-1") {
                $property = $query->get();
            } else if(isset($request->property_limit) && $request->property_limit != '') {
                $property = $query->paginate(4);    
            }else{
                if( isset($request->is_featured_home) && $request->is_featured_home != '' && $request->is_featured_home == 1) {
                    $featured_query = FeaturedListingMapping::query();
                    $featured_query->where('ConfigurationId', $this->configurationId);
                    $featuredListingMapping = $featured_query->first();
                    if( !empty($featuredListingMapping) ) {
                        $fetuaredLimit = 4;
                        if(isset($featuredListingMapping->MainFeaturedListing) && $featuredListingMapping->MainFeaturedListing != null ) {
                            $fetuaredLimit = $featuredListingMapping->MainFeaturedListing;
                        } else {
                            $fetuaredLimit = $request->is_featured_home_limit;
                        }
                        $property = $query->paginate($fetuaredLimit);
                    } else {
                        $property = $query->paginate(4);
                    }
                } else {
                    if(isset($property_map_listing) && $property_map_listing == "1") {
                        $property = $query->get();
                    }else{                    
                        if(isset($request->property_map_search) && $request->property_map_search == "1") {
                            $property = $query->paginate(10);
                        } else {
                            $property = $query->paginate(9);
                        }
                    }
                }
            }

            if( isset($request->featured_closed_deal) && $request->featured_closed_deal != '' && $request->featured_closed_deal == 1) {
                $featured_query = FeaturedListingMapping::query();
                    $featured_query->where('ConfigurationId', $this->configurationId);
                $featuredListingMapping = $featured_query->first();

                $fetuaredClosedLimit = 0;
                if( !empty($featuredListingMapping) && $featuredListingMapping != null ) {
                    $fetuaredClosedLimit = $featuredListingMapping->ClosedFeaturedListing;
                } else {
                    $fetuaredClosedLimit = 2;    
                }
                $query->orderBy('Property.FeaturedCloseDealOrder', 'ASC');
                $property = $query->paginate($fetuaredClosedLimit);
            }
            //dd(DB::getQueryLog());
        } catch(Exception $e) {
            echo 'Message: ' .$e->getMessage();
        }



        $siteUrl = $this->removeHttp(getallheaders()['site_url']);
        $childUrl = $siteUrl;

        $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
        $defaultPathAzurePropertyPath = env('AZURE_STOGARGE_CONTAINER_PROPERTY_IMAGES');
        $defaultPathAzure = $defaultPathAzure.'/'.$defaultPathAzurePropertyPath;

        $featuredImage = env('AZURE_STORAGE_CONTAINER_FEATURED_IMAGE');
        $bannerImage = env('AZURE_STOGARGE_CONTAINER_BANNER_IMAGE');
        $otherImage = env('AZURE_STOGARGE_CONTAINER_OTHER_IMAGE');

        if( !empty( $property ) ) {
            foreach ( $property as $key => $value ) {
                $propertyName = $value->URLSlug;
                
                $FeaturedImage = $value->FeaturedImage;
                $BannerImage = $value->BannerImage;

                if( $value->FeaturedImage != null ) {
                    $value->FeaturedImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->FeaturedImage;
                }

                if( $value->BannerImage ) {
                    $value->BannerImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->BannerImage;
                }
                if( !empty( $value->getPropertyImages ) ) {
                    $OriginalOtherImg = $value->getPropertyImages;
                } else {
                    $OriginalOtherImg = [];
                }
                
                if( !empty( $value->getPropertyImages ) ) {
                    $otherImageArray = [];
                    foreach ( $value->getPropertyImages as $key1 => $value1 ) {
                        $otherImageArray[] = $defaultPathAzure.'/'.$propertyName.'/'.$value1->Filename;
                    }
                    $value->OtherImage = $otherImageArray;
                } else {
                    $value->OtherImage = [];
                }
                unset($value->getPropertyImages);

                if( !empty( $currentUser ) && $currentUser != null && $currentUser->Id != "" && $currentUser->Id != null ) {
                    $favoriteProperty = FavoriteProperty::where('PropertyId', $value->Id)->where('adminId', $currentUser->Id)->first();
                    if(!empty($favoriteProperty)) {
                        $value->favorite = $favoriteProperty->Favorite;
                    } else {
                        $value->favorite = "";
                    }
                }

                $SMOtherImage = [];
                if( !empty( $OriginalOtherImg ) ) {
                    $OTHSM = [];
                    foreach ( $OriginalOtherImg as $key1 => $value1 ) {
                        $fileUrl = $defaultPathAzure.'/'.$propertyName.'/sm/'.$value1->Filename;
                        $OTHSM[] = $fileUrl;
                    }
                    $SMOtherImage = $OTHSM;
                }

                $LGOtherImage = [];
                if( !empty( $OriginalOtherImg ) ) {
                    $OTHLG = [];
                    foreach ( $OriginalOtherImg as $key1 => $value1 ) {
                        $OTHLG[] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$value1->Filename;
                    }
                    $LGOtherImage = $OTHLG;
                }
                
                $SM = [];
                $LG = [];
                if( $value->FeaturedImage != null ) {
                    $SM['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$FeaturedImage;
                    $LG['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$FeaturedImage;
                }
                
                $SM['OtherImage'] = $SMOtherImage;
                if( $value->BannerImage ) {
                    $SM['BannerImage'] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$BannerImage;
                    $LG['BannerImage'] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$BannerImage;
                }
                $LG['OtherImage'] = $LGOtherImage;

                $value->SM = $SM;
                $value->LG = $LG;
            }
        }
        return $property;
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id = '',$name = '') {
        
        if($name == 1){        
            $hashids = new Hashids('NEWmark_2022',8,'1234567890abcdef');
            $id = $this->decodeId($id);
        }
        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();
        $confId = $configurations->ConfigurationId;
        $currentUser = auth()->guard('api')->user();
        $property = Property::query();
        $userIP = $this->getIp();

        $property->where('Property.Id', $id);
        /*if(is_numeric($id)) {
        } else {
            $property->where('Property.PrivateURLSlug', $id);
        }*/
        $property->leftJoin('SaveStatus', 'SaveStatus.ID', '=', 'Property.SaveStatusID');
        $property->leftJoin('PropertyStatus', 'PropertyStatus.Id', '=', 'Property.PropertyStatusId');
        $property->where('SaveStatus.Description', 'Active');
        $property->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyFinancialDetails', 'PropertyFinancialDetails.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyPhysicalDetails', 'PropertyPhysicalDetails.PropertyId', '=', 'Property.Id');
        $property->leftJoin('BuildingClass', 'BuildingClass.Id', '=', 'PropertyPhysicalDetails.BuildingClassId');
        $property->leftJoin('PropertyCAContent', 'PropertyCAContent.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyListingDetails', 'PropertyListingDetails.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');
        $property->leftJoin('Users', 'Users.Id', '=', 'PropertyContactMapping.UserId');
        /**
         * Pressrelease
         */
        $property->leftJoin('PropertyPressRelease', 'PropertyPressRelease.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyTenancy', 'PropertyTenancy.PropertyId', '=', 'Property.Id');
        $property->leftJoin('AcquisitionCriteriaPropertyRelation', 'AcquisitionCriteriaPropertyRelation.PropertyId', '=', 'Property.Id');
        $property->where('AcquisitionCriteriaPropertyRelation.Status', 1);
        $property->leftJoin('AcquisitionCriteriaType', 'AcquisitionCriteriaType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId');
        $property->leftJoin('PropertySalesDetailsStatus', 'PropertySalesDetailsStatus.PropertyId', '=', 'Property.Id');
        $property->leftJoin('NonHostPropertyMapping', 'Property.Id', '=', 'NonHostPropertyMapping.PropertyId');
        $property->select(['Property.*', 'PropertyStatus.Description AS ListingStatus', 'SaveStatus.Description AS SaveStatus', 'AcquisitionCriteriaType.Name AS PropertyType'
            , "PropertyAddress.Latitude", "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2", "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", "PropertyAddress.Zipcode", "PropertyFinancialDetails.PricePSF", "PropertyFinancialDetails.AskingPrice", "PropertyFinancialDetails.T12CapRate", "PropertyFinancialDetails.InvestmentPeriod", "PropertyFinancialDetails.LeveredIRR", "PropertyFinancialDetails.UnleveredIRR", "PropertyFinancialDetails.ReturnOnCost", "PropertyFinancialDetails.GRM", "PropertyFinancialDetails.PotentialGRM", "PropertyFinancialDetails.InPlaceCapRate", "PropertyFinancialDetails.InPlaceNOI", "PropertyFinancialDetails.Year1CapRate", "PropertyFinancialDetails.MarkToMarketCapRate", "PropertyFinancialDetails.InPlaceRents", "PropertyFinancialDetails.MarketRents", "PropertyFinancialDetails.Walt", "PropertyFinancialDetails.AvgInPlaceRentsBelowMarket", "PropertyFinancialDetails.YrlRR10", "PropertyFinancialDetails.Yr10EquityMultiple", "PropertyFinancialDetails.CashOnCash", "PropertyFinancialDetails.Year1NOI", "PropertyFinancialDetails.T12NOI", "PropertyFinancialDetails.CapitalInvested", "PropertyFinancialDetails.Price", "PropertyFinancialDetails.IsUnpriced", "PropertyFinancialDetails.IsConfidential", "PropertyPhysicalDetails.Building", "PropertyPhysicalDetails.SqFeet", "PropertyPhysicalDetails.YearBuilt", "PropertyPhysicalDetails.LotSize", "PropertyPhysicalDetails.Stories", "PropertyPhysicalDetails.ParkingRatio", "PropertyPhysicalDetails.YearRenovated", 'BuildingClass.Description AS BuildingClass', "PropertyPhysicalDetails.ZoningType AS zoning", "PropertyPhysicalDetails.ZoningType", "PropertyListingDetails.SalesPrice", "PropertyListingDetails.ListingExpiration", "PropertyListingDetails.SalesPricePSF", "PropertyListingDetails.ClosingCapRate", "PropertyListingDetails.DaysOnMarket", "PropertyListingDetails.NoOfOffers", "PropertyListingDetails.CloseDate", "PropertyListingDetails.EstimatedCommission", "PropertyListingDetails.SPvsAP", "PropertyListingDetails.InternalSalesNotes", "PropertyListingDetails.LastTransfer", "PropertyListingDetails.LastTransferPrice", "PropertyListingDetails.PricingExpectation", "PropertyListingDetails.SellerMotivation", "PropertyPressRelease.IsPressReleaseFile", "PropertyPressRelease.PressReleaseFile", "PropertyPressRelease.PressReleaseLink", "PropertyTenancy.Tenancy", "PropertyTenancy.NumberOfTenants", "PropertyTenancy.Units", "PropertyCAContent.CAPdfDocument", "Users.FirstName AS contactfirstname", "Users.LastName AS contactlastname", "PropertySalesDetailsStatus.IsSalePrice", "PropertySalesDetailsStatus.IsSalesPricePSF", "PropertySalesDetailsStatus.IsCloseCAPRate", "PropertySalesDetailsStatus.IsBuyer", "PropertySalesDetailsStatus.IsSeller", "PropertySalesDetailsStatus.IsClosingDate", "PropertySalesDetailsStatus.IsSPvsAP", "PropertySalesDetailsStatus.IsOfferReceived", "PropertySalesDetailsStatus.IsDaysOnMarket", "PropertySalesDetailsStatus.IsPageView", "PropertySalesDetailsStatus.IsExecutedCA", "PropertySalesDetailsStatus.IsOMDownload", "PropertySalesDetailsStatus.SalePriceUnit",
                "PropertySalesDetailsStatus.IsSalePriceUnit",
                "Property.HashId",
                "PropertySalesDetailsStatus.IsSalesPricePSF",
                "PropertyFinancialDetails.IsSaleConfidentialPSF",
                "PropertyFinancialDetails.AcresPrice",
                "PropertySalesDetailsStatus.IsSaleDescription", 
                "PropertySalesDetailsStatus.IsSellerCompany", 
                "Property.EncumbranceTypeId",
                "Property.HotelAssetTypeId",
                "Property.HotelClassificationTypeId",
                "Property.LaborTypeId",
                "Property.OwnershipInterestTypeId",

                DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated'),
            ]);

        $property->with('getPropertyImages');
        $property->where('AcquisitionCriteriaType.ModuleId', 1);
        $property->where('Property.SaveStatusId', 2);
        $property->where('Property.EntityStatusId', 1);
        $property->with('getPropertyHighlights');
        $property->where(function ($q) use ($confId) {
            $q->where(function ($q1) use ($confId) {
                $q1->where('Property.SaveStatusId', 2);
                $q1->where('Property.ConfigurationId', $confId);
            });
            $q->orWhere(function ($q2) use ($confId) {
                $q2->where('NonHostPropertyMapping.SaveStatusId', 2);
                $q2->where('NonHostPropertyMapping.ConfigurationId', $confId);
            });
        });
        $property = $property->first();
        if ( !empty( $property ) ) {

            if($property->PropertyType == 'Hospitality'){
                $EncumbranceName                = $property->getEncumbranceTypeData($property->EncumbranceTypeId); 
                
                $LaborTypeData                  = $property->getLaborTypeData($property->LaborTypeId);
                $HotelClassificationTypeData    = $property->getHotelClassificationTypeData($property->HotelClassificationTypeId);
                $HotelAssetTypeData             = $property->getHotelAssetTypeData($property->HotelAssetTypeId);
                
                $OwnershipInterestTypeData      = $property->getOwnershipInterestTypeData($property->OwnershipInterestTypeId);
                
                $SubPropertyData      = $property->getSubPropertyData($property->Id);
                
                $property->EncumbranceName          = $EncumbranceName->Name;
                $property->LaborName                = $LaborTypeData->Name;
                $property->HotelClassificationName  = $HotelClassificationTypeData->Name;
                $property->HotelAssetName           = $HotelAssetTypeData->Name;
                $property->OwnershipInterestName    = $OwnershipInterestTypeData->Name;
                if($SubPropertyData->Name != ''){
                    $property->SubPropertyData          = $SubPropertyData->Name;
                }else{
                    $property->SubPropertyData          = 'None';
                }
            }

            $property->DaysOnMarket = $property->getPropertyDaysOnMarket($property->Id);
            $property->LeaseType = $property->getPropertyLeaseType($property->Id);

            $siteUrl = $this->removeHttp(getallheaders()['site_url']);
            $childUrl = $siteUrl;
            $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
            $defaultPathAzurePropertyPath = env('AZURE_STOGARGE_CONTAINER_PROPERTY_IMAGES');
            $defaultPathAzure = $defaultPathAzure.'/'.$defaultPathAzurePropertyPath;
            $featuredImage = env('AZURE_STORAGE_CONTAINER_FEATURED_IMAGE');
            $bannerImage = env('AZURE_STOGARGE_CONTAINER_BANNER_IMAGE');
            $otherImage = env('AZURE_STOGARGE_CONTAINER_OTHER_IMAGE');
            $propertyName = $property->URLSlug;
            $FeaturedImageLSM = '';
            $FeaturedImageLG = '';
            $FeaturedImageMD = '';
            $FeaturedImageSM = '';
            $FeaturedImage1 = $property->FeaturedImage;

            if( !empty( $property->getPropertyImages ) ) {
                $OriginalOtherImg1 = $property->getPropertyImages;
            } else {
                $OriginalOtherImg1 = [];
            }

            if( $property->BannerImage ) {
                $property->BannerImage = $defaultPathAzure.'/'.$propertyName.'/l-lg/'.$property->BannerImage;
            } else {
                $property->BannerImage = $defaultPathAzure.'/'.$propertyName.'/'.$property->BannerImage;
            }

            if( $property->FeaturedImage ) {
                $FeaturedImageLSM = $defaultPathAzure.'/'.$propertyName.'/l-sm/'.$property->FeaturedImage;
                $FeaturedImageLG = $defaultPathAzure.'/'.$propertyName.'/lg/'.$property->FeaturedImage;
                $FeaturedImageMD = $defaultPathAzure.'/'.$propertyName.'/md/'.$property->FeaturedImage;
                $FeaturedImageSM = $defaultPathAzure.'/'.$propertyName.'/sm/'.$property->FeaturedImage;
                $property->FeaturedImage = $defaultPathAzure.'/'.$propertyName.'/'.$property->FeaturedImage;
            }

            if( !empty( $property->getPropertyImages ) ) {
                $otherImageArray = [];
                foreach ( $property->getPropertyImages as $key => $value ) {
                    $otherImageArray[] = $defaultPathAzure.'/'.$propertyName.'/'.$value->Filename;
                }
                $property->OtherImage = $otherImageArray;
            } else {
                $property->OtherImage = [];
            }
            $property->SMFeaturedImage = $FeaturedImageSM;

            $SMOtherImage = [];
            if( !empty( $OriginalOtherImg1 ) ) {
                $OTHSM = [];
                foreach ( $OriginalOtherImg1 as $key1 => $value1 ) {
                    $OTHSM[] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$value1->Filename;
                }
                $SMOtherImage = $OTHSM;
            }

            $LGOtherImage = [];
            if( !empty( $OriginalOtherImg1 ) ) {
                $OTHLG = [];
                foreach ( $OriginalOtherImg1 as $key1 => $value1 ) {
                    $OTHLG[] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$value1->Filename;
                }
                $LGOtherImage = $OTHLG;
            }

            $SM = [];
            $LG = [];
            if( $FeaturedImage1 != null ) {
                $SM['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$FeaturedImage1;
                $LG['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$FeaturedImage1;
            }

            $SM['OtherImage'] = $SMOtherImage;
            $LG['OtherImage'] = $LGOtherImage;
            $property->SM = $SM;
            $property->LG = $LG;
            unset($property->getPropertyImages);
            $property->AcquisitioncriteriaPropertyRelation = $property->getAcquisitioncriteriaPropertyRelation($property->Id);
            
            if( !empty( $currentUser ) && $currentUser != null && $currentUser->Id != "" && $currentUser->Id != null ) {
                $property->favorite = $property->getFavoriteProperty($property->Id, $currentUser->Id);
            } else {
                $property->favorite = "";
            }

            $propertyagents = PropertyAgents::query()
                ->select('AgentID')
                ->where('PropertyId', $property->Id)
                ->where('IsAdditionalAgent', 0)
                ->where('EntityStatusId', 1)
                ->orderBy('SortOrder', 'ASC')
                ->paginate(4);

            $agentIds = [];
            if ( !empty( $propertyagents ) ) {
                foreach ( $propertyagents as $key => $value ) {
                    $agentIds[] = $value->AgentID;
                }
            }

            if(!empty($currentUser) && $currentUser != null) {
                $ddrequest = $property->getPropertyRelation($property->Id, $currentUser->Id);
                if(!empty($ddrequest) && $ddrequest != null ) {
                    $property->property_relation = $ddrequest;
                } else {
                    $property->property_relation = [];
                }
            } else {
                $property->property_relation = [];
            }

            /**
             * Set highlight
             */
            $seller = DB::select("SELECT PropertyContactMapping.UserId FROM PropertyContactMapping INNER JOIN PropertyContactType ON PropertyContactType.ID = PropertyContactMapping.PropertyContactTypeId WHERE PropertyContactType.Description = 'Seller' AND PropertyContactMapping.PropertyId = ".$property->Id);

            $sallerId = "";
            if( !empty($seller) && $seller != null && isset($seller[0]) && isset($seller[0]->UserId)) {
                $sallerId = $seller[0]->UserId;
            }
            $buyer = DB::select("SELECT PropertyContactMapping.UserId FROM PropertyContactMapping INNER JOIN PropertyContactType ON PropertyContactType.ID = PropertyContactMapping.PropertyContactTypeID WHERE PropertyContactType.Description = 'Buyer' AND PropertyContactMapping.PropertyId = ".$property->Id);

            $buyerId = "";
            if( !empty($buyer) && $buyer != null && isset($buyer[0]) && isset($buyer[0]->UserId)) {
                $buyerId = $buyer[0]->UserId;
            }

            if(isset($property->PropertyStatusId) && $property->PropertyStatusId != '' && $property->PropertyStatusId == 5 ) {
                if($buyerId != "") {
                    $buyer = DB::select("SELECT CONCAT(Users.FirstName, ' ', Users.LastName) AS name, Companies.CompanyName FROM Users LEFT JOIN Companies ON Companies.Id = Users.CompanyId WHERE Users.Id = ".$buyerId);

                    if( (!empty($buyer) && $buyer != null && isset($buyer[0])) && (isset($buyer[0]->name) || isset($buyer[0]->CompanyName))) {
                        $property->Buyer = $buyer[0]->name;
                        $property->BuyerCompanyName = $buyer[0]->CompanyName;
                    } else {
                        $property->Buyer = '';
                        $property->BuyerCompanyName = '';
                    }
                }
                if($sallerId != "") {
                    $seller = DB::select("SELECT CONCAT(Users.FirstName, ' ', Users.LastName) AS name, Companies.CompanyName FROM Users LEFT JOIN Companies ON Companies.Id = Users.CompanyId WHERE Users.Id = ".$sallerId);

                    if( (!empty($seller) && $seller != null && isset($seller[0])) && (isset($seller[0]->name) || isset($seller[0]->CompanyName))) {
                        $property->Seller = $seller[0]->name;
                        $property->SellerCompanyName = $seller[0]->CompanyName;
                    } else {
                        $property->Seller = '';
                        $property->SellerCompanyName = '';
                    }
                }
            } else {
                if($sallerId != "") {
                    $seller = DB::select("SELECT CONCAT(Users.FirstName, ' ', Users.LastName) AS name, Companies.CompanyName FROM Users INNER JOIN Companies ON Companies.Id = Users.CompanyId WHERE Users.Id = ".$sallerId);

                    if( (!empty($seller) && $seller != null && isset($seller[0])) && (isset($seller[0]->name) || isset($seller[0]->CompanyName))) {
                        $property->Seller = $seller[0]->name;
                        $property->SellerCompanyName = $seller[0]->CompanyName;
                    } else {
                        $property->Seller = '';
                        $property->SellerCompanyName = '';
                    }
                }
            }

            $propertyCustomeGrid = self::getCustomeGrid($property);

            if(!empty($propertyCustomeGrid)) {
                $property->propertyHighlights = $propertyCustomeGrid;
                unset($property->getPropertyHighlights);
            } else {
                $property->propertyHighlights = [];
                unset($property->getPropertyHighlights);
            }
            if(!empty($currentUser)) {
                $userQuery = User::query();
                $userQuery->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
                $userQuery->where('Users.Id', $currentUser->Id);
                $userQuery->orderBy('UserContactMapping.UserTypeId', 'DESC');
                $userQuery->select(["Users.Id", "Users.FirstName", "Users.LastName", "Users.LastName", "Users.Username", "Users.Email", "UserContactMapping.UserTypeId", "UserContactMapping.Status", "UserContactMapping.IndustryRoleId", "UserContactMapping.InvestorTypeId", "UserContactMapping.BrokerTypeId","UserContactMapping.ConfigurationId"]);
                $user = $userQuery->first();
                
                $directory = Directory::query();
                $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
                $directory->where('DatumDirectory.PropertyId', $property->Id);
                $directory->where('DocumentVaultOMAccess.Access', 3);
                $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
                $directory->where('DatumDirectory.ParentId', 0);

                $directory->where(function ($q) use ($user) {
                   $q->orWhere('DocumentVaultOMAccess.UserId', $user->Id);
                   $q->orWhere('DocumentVaultOMAccess.IndustryRoleId', $user->IndustryRoleId);
                   $q->orWhere('DocumentVaultOMAccess.UserEmail', $user->Email);
                });
                $directory->select(['DocumentVaultOMAccess.*']);
                $directoryomaccess = $directory->first();
                if (!empty($directoryomaccess) && $directoryomaccess != null) {
                    $property->documentvaultomaccess = true;
                } else {
                    $property->documentvaultomaccess = false;
                }

                $documentVaultomAccess12 = Directory::query();
                $documentVaultomAccess12->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
                $documentVaultomAccess12->where('DatumDirectory.PropertyId', $property->ID);
                $documentVaultomAccess12->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
                $documentVaultomAccess12->where('DatumDirectory.ParentId', 0);
                $documentVaultomAccess12->where('DocumentVaultOMAccess.UserId', $currentUser->Id);
                $documentVaultomAccess12->select(['DocumentVaultOMAccess.*']);
                $documentVaultomAccess12 = $documentVaultomAccess12->first();

                if(!empty($documentVaultomAccess12) && $documentVaultomAccess12 != null) {
                    if($documentVaultomAccess12->Access == 1) {
                        $property->documentaccess = 'Pending';
                    } elseif ($documentVaultomAccess12->Access == 2) {
                        $property->documentaccess = 'Requested';
                    } elseif($documentVaultomAccess12->Access == 3) {
                        $property->documentaccess = 'Approved';
                    } elseif ($documentVaultomAccess12->Access == 4 ) {
                        $property->documentaccess = 'Rejected';
                    }
                } else{
                    $property->documentaccess = 'None';
                }
                if($property->documentvaultomaccess) {
                    $property->documentaccess = 'Approved';
                }
            } else {
                $property->documentvaultomaccess = false;
                $property->documentaccess = 'None';
            }

            if(isset($property->PropertyStatusId) && $property->PropertyStatusId != '' && $property->PropertyStatusId == 5 ) {

                $TotalOfferCounts = DB::select("
                SELECT
                    W.Id, COUNT(DISTINCT PL.UserId) as offer
                FROM
                    WPOsdUserPropertiesRelations PR
                    INNER JOIN Property W ON W.id = PR.PropertyId
                    INNER JOIN Users U ON PR.UserId = U.Id
                    INNER JOIN LeadAdminProperty PL ON PL.UserId = PR.UserId AND PL.PropertyId = PR.PropertyId
                    LEFT JOIN LeadAdminProperty LA ON LA.PropertyId = W.Id AND LA.UserId = PR.UserId
                WHERE
                    W.Id = ".$property->Id."
                    AND
                        (LA.StatusId != 11
                    OR
                        LA.StatusId IS NULL)
                    GROUP BY W.id;
                ");
                if(!empty($TotalOfferCounts) && $TotalOfferCounts != null) {
                    $property->TotalOfferCounts = $TotalOfferCounts[0]->offer;
                } else {
                    $property->TotalOfferCounts = 0;
                }


                $TotalPageViews = DB::select("
                    SELECT
                        W.Id, COUNT(PT.PropertyId) as page_view
                    FROM
                        Property W
                    LEFT JOIN OEPLPropertyTracker PT ON PT.PropertyId = W.Id
                    WHERE W.Id = ".$property->Id." GROUP BY W.Id
                ");
                if(!empty($TotalPageViews) && $TotalPageViews != null) {
                    $property->TotalPageViews = $TotalPageViews[0]->page_view;
                } else {
                    $property->TotalPageViews = 0;
                }

                $TotalDownloadCounts = DB::select("
                    SELECT
                        W.Id, COUNT(DISTINCT(DV.UserId)) as download
                    FROM
                        Property W
                    INNER JOIN DocumentVault DV ON DV.PropertyId = W.Id
                    INNER JOIN Users UU ON DV.UserId = UU.Id
                    LEFT JOIN LeadAdminProperty LA ON LA.PropertyId = W.Id AND LA.UserId = DV.UserId
                    WHERE
                        W.Id = ".$property->Id." AND DV.DocumentType = 'OM' AND ( LA.StatusId != 11 OR LA.StatusId IS NULL )
                    GROUP BY W.Id
                ");
                if(!empty($TotalDownloadCounts) && $TotalDownloadCounts != null) {
                    $property->TotalDownloadCounts = $TotalDownloadCounts[0]->download;
                } else {
                    $property->TotalDownloadCounts = 0;
                }

                //$TotalCACounts = DB::select("SELECT W.id, COUNT(RR.id) ca_count FROM property W INNER JOIN WPOsdUserPropertiesRelations RR ON RR.property_id = W.id INNER JOIN dashboardparent_admin UU ON UU.adminid = RR.user_id LEFT JOIN leadadminproperty LA ON LA.PropertyId = W.id AND LA.LeadId = RR.user_id WHERE W.id = ".$property->ID." AND RR.nda_signed AND( LA.StatusId != 11 OR LA.StatusId IS NULL ) GROUP BY W.id");
                $TotalCACounts = DB::select("
                    SELECT
                        W.Id, COUNT(RR.Id) ca_count
                    FROM Property W
                        INNER JOIN WPOsdUserPropertiesRelations RR ON RR.PropertyId = W.Id
                        INNER JOIN Users UU ON UU.Id = RR.UserId
                        LEFT JOIN LeadAdminProperty LA ON LA.PropertyId = W.Id AND LA.UserId = RR.UserId
                    WHERE W.Id = ".$property->Id." AND( LA.StatusId != 11 OR LA.StatusId IS NULL ) GROUP BY W.Id
                ");
                if(!empty($TotalCACounts) && $TotalCACounts != null ) {
                    $property->TotalCACounts = $TotalCACounts[0]->ca_count;
                } else {
                    $property->TotalCACounts = 0;
                }
                
                $Byyer = DB::select("
                    SELECT
                        CONCAT(Users.FirstName,' ', Users.LastName) AS name
                    FROM propertycontacttype
                        LEFT JOIN PropertyContactMapping ON PropertyContactMapping.PropertyContactTypeId = PropertyContactType.Id
                        LEFT JOIN Users ON Users.Id = PropertyContactMapping.UserId
                    WHERE PropertyContactType.Id = 3 AND PropertyContactMapping.PropertyId = ".$property->Id
                );
                if( !empty($Byyer) && $Byyer != null && isset($Byyer[0])) {
                    $property->Buyer = $Byyer[0]->name;
                } else {
                    $property->Buyer = "";
                }

                $TrueBuyer = DB::select("
                    SELECT
                        CONCAT(Users.FirstName,' ', Users.LastName) AS name
                    FROM PropertyContactType
                        LEFT JOIN PropertyContactMapping ON PropertyContactMapping.PropertyContactTypeId = PropertyContactType.Id
                        LEFT JOIN Users ON Users.Id = PropertyContactMapping.UserId
                    WHERE PropertyContactType.Id = 4 AND propertycontactmapping.PropertyId = ".$property->Id);

                if( !empty($TrueBuyer) && $TrueBuyer != null && isset($TrueBuyer[0])) {
                    $property->TrueBuyer = $TrueBuyer[0]->name;
                } else {
                    $property->TrueBuyer = "";
                }


                $Seller = DB::select("
                    SELECT
                        CONCAT(Users.FirstName,' ', Users.LastName) AS name
                    FROM PropertyContactType
                        LEFT JOIN PropertyContactMapping ON PropertyContactMapping.PropertyContactTypeId = PropertyContactType.Id
                        LEFT JOIN Users ON Users.Id = PropertyContactMapping.UserId
                    WHERE PropertyContactType.Id = 2 AND PropertyContactMapping.PropertyId = ".$property->Id);
                
                if( !empty($Seller) && $Seller != null && isset($Seller[0])) {
                    $property->Seller = $Seller[0]->name;
                } else {
                    $property->Seller = "";
                }
            }

            $agentData = [];
            $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
            $agentPath = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
            if( !empty ( $agentIds )) {
                foreach($agentIds as $key => $value ) {
                    $users = User::query();
                    $users->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
                    $users->where('Users.Id', $value);
                    $users->where('UserContactMapping.Status', 1);
                    $users->where('UserContactMapping.UserTypeId', '!=', 4);
                    $users->where('Users.IsSuperAuthorizedAccount', '!=', 1);
                    $users->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', '=', 'Users.Id');
                    $users->leftJoin('Configurations', 'Configurations.ConfigurationId', '=', 'Users.ConfigurationId');
                    $users->with('getLicense');
                    $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email", "Users.Title", "Users.ProfileImage", "Users.Username",
                        'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.Country', 'UserAddressDetails.WorkPhone', 'UserAddressDetails.MobilePhone','Configurations.SiteUrl','Configurations.SiteName','Configurations.AgentPageUrl']);
                    $agent = $users->first();
                    if(!empty($agent) && $agent != null ) {
                        if(isset($agent->ProfileImage) && $agent->ProfileImage != '' && $agent->ProfileImage != null ) {
                            $agent->ProfileImage = $defaultPathAzure.'/'.$agentPath.'/'.$agent->ProfileImage;
                        } else {
                            $agent->ProfileImage = 'https://datumdoc.blob.core.windows.net/datumfilecontainer/placeholders/agent-placeholder.png';
                        }
                        $agentData[] = $agent;
                    }
                }
            }
            $property->PropertyAgents = $agentData;
            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $property
                ], 200);
        } else {
            return response()->json(
                [
                    'status' => 'failed',
                    'message' => 'Property id is invalid',
                    'errors' => [],
                    'data' => []
                ], 200);
        }

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pageView( Request $request ) {
        $databaseConnection = new DatabaseConnection();
        $configurationData  = $databaseConnection->getConfiguration();
        $currentUser        = auth()->guard('api')->user();
        $userIP = $this->getIp();
        
        $property = Property::query();
        if(is_numeric($request->id)) {
            $property->where('Property.Id', $request->id);
        } else {
            $property->where('Property.PrivateURLSlug', $request->id);
        }
        $property = $property->first();
        $propertyConfig = $databaseConnection->getPropertyConfiguration($property->Id);
        
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
            $oeplPropertyTracker->UserIp = $request->ip;
            $oeplPropertyTracker->BrowseFromMobile = $browse_from_mobile;
            $oeplPropertyTracker->ConfigurationId = $configurationData->ConfigurationId;
            $oeplPropertyTracker->HostedConfigurationId = $propertyConfig->ConfigurationId;
            $oeplPropertyTracker->save();

            if($oeplPropertyTracker) {
                return response()->json(
                [
                    'status' => 'success',
                    'message' => '',
                    'errors' => [],
                    'data' => []
                ], 200);
            } else {
                return response()->json(
                [
                    'status' => 'failed',
                    'message' => '',
                    'errors' => [],
                    'data' => []
                ], 200);
            }
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyPropertyList(Request $request) {
        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();

        $currentUser = $request->user();
        $userId = $currentUser->Id;
        $favoriteProperty = FavoriteProperty::query();
        $favoriteProperty->where('FavoriteProperty.AdminId', '=', $userId);
        $favoriteProperty->where('FavoriteProperty.Favorite', 1);
        $favoriteProperty = $favoriteProperty->get();

        $favProperty = [];
        if( !empty( $favoriteProperty ) ) {
            foreach ($favoriteProperty as $favoriteProperty => $value ) {
                $favProperty[] = $value->PropertyId;
            }
        }

        $wpOsd = WpOsdUserPropertiesRelationship::query();
        $wpOsd->where('UserId', '=', $currentUser->Id);
        $wpOsd->select(["PropertyId"]);
        $wpOsd = $wpOsd->get();

        $myProperty = [];
        if( !empty( $wpOsd ) ) {
            foreach ( $wpOsd as $key => $value ) {
                $myProperty[] = $value->PropertyId;
            }
        }

        $bothIds = array_merge($favProperty, $myProperty);
        $bothIds = array_unique($bothIds);
        $confId = $configurations->ConfigurationId;
        if ( !empty($bothIds) ) {
            //DB::enableQueryLog();
            $query = Property::query();
            $query->select(["Property.Id",
                "property.CreatedDate",
                "Property.ModifiedDate",
                "Property.Title",
                "Property.FeaturedImage",
                "Property.BannerImage",
                "Property.URLSlug",
                "Property.PrivateURLSlug", 
                "Property.Name",
                "Property.HashId",
                "PropertyStatus.Description AS ListingStatus",
                "SaveStatus.Description AS SaveStatus",
                "Property.StatusDate",
                "AcquisitionCriteriaType.Name AS PropertyType",
                "PropertyAddress.Latitude",
                "PropertyAddress.Longitude",
                "PropertyAddress.Address1",
                "PropertyAddress.Address2",
                "PropertyAddress.City",
                "PropertyAddress.State",
                "PropertyAddress.Country",
                "PropertyAddress.Zipcode",
                "PropertyFinancialDetails.AskingPrice",
                "property.Occupancy",
                "PropertyFinancialDetails.Walt",
                "PropertyPhysicalDetails.SqFeet",
                "PropertyFinancialDetails.AcresPrice",
                "PropertyFinancialDetails.Year1CapRate",
                "PropertyPhysicalDetails.LotSize",
                "PropertyPhysicalDetails.Building",
                "PropertyFinancialDetails.CapitalInvested",
                "PropertyFinancialDetails.CashOnCash",
                "PropertyFinancialDetails.Yr10EquityMultiple",
                "PropertyFinancialDetails.GRM",
                "PropertyFinancialDetails.InPlaceNOI",
                "PropertyFinancialDetails.InPlaceRents",
                "PropertyFinancialDetails.InvestmentPeriod",
                "PropertyFinancialDetails.LeveredIRR",
                "PropertyFinancialDetails.MarkToMarketCapRate",
                "PropertyFinancialDetails.MarketRents",
                "PropertyFinancialDetails.Year1NOI",
                "PropertyFinancialDetails.T12NOI",
                "PropertyFinancialDetails.PotentialGRM",
                "PropertyFinancialDetails.PricePSF",
                "PropertyFinancialDetails.Price",
                "PropertyFinancialDetails.AvgInPlaceRentsBelowMarket",
                "PropertyFinancialDetails.ReturnOnCost",
                "PropertyListingDetails.ClosingCapRate",
                "PropertyListingDetails.SPvsAP",
                "propertysalesdetailsstatus.SalePriceUnit",
                "PropertyPhysicalDetails.Stories",
                "PropertyFinancialDetails.T12CapRate",
                "propertytenancy.Tenancy",
                "propertytenancy.Units",
                "PropertyFinancialDetails.UnleveredIRR",
                "PropertyPhysicalDetails.YearBuilt",
                "PropertyPhysicalDetails.YearRenovated",
                "PropertyPhysicalDetails.ZoningType",
                "BuildingClass.Description AS buildingclass",
                "PropertyPhysicalDetails.BuildingClassId",
                "PropertyPhysicalDetails.ParkingRatio",
                "PropertyFinancialDetails.InPlaceCapRate",
                "PropertyListingDetails.SalesPrice",
                "PropertyFinancialDetails.IsConfidential",
                "PropertyFinancialDetails.IsUnpriced",
                "PropertyListingDetails.SalesPricePSF",
                "PropertyListingDetails.CloseDate",
                "PropertyFinancialDetails.IsSaleConfidentialPSF",
                'Property.ConfigurationId',
                DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated')
            ]);
            $query->groupBy("Property.Id",
                "property.CreatedDate",
                "Property.ModifiedDate",
                "Property.Title",
                "Property.FeaturedImage",
                "Property.BannerImage",
                "Property.URLSlug",
                "Property.PrivateURLSlug",
                "Property.Name",
                "Property.HashId",
                "PropertyStatus.Description",
                "SaveStatus.Description",
                "Property.StatusDate",
                "AcquisitionCriteriaType.Name",
                "PropertyAddress.Latitude",
                "PropertyAddress.Longitude",
                "PropertyAddress.Address1",
                "PropertyAddress.Address2",
                "PropertyAddress.City",
                "PropertyAddress.State",
                "PropertyAddress.Country",
                "PropertyAddress.Zipcode",
                "PropertyFinancialDetails.AskingPrice",
                "property.Occupancy",
                "PropertyFinancialDetails.Walt",
                "PropertyPhysicalDetails.SqFeet",
                "PropertyFinancialDetails.AcresPrice",
                "PropertyFinancialDetails.Year1CapRate",
                "PropertyPhysicalDetails.LotSize",
                "PropertyPhysicalDetails.Building",
                "PropertyFinancialDetails.CapitalInvested",
                "PropertyFinancialDetails.CashOnCash",
                "PropertyFinancialDetails.Yr10EquityMultiple",
                "PropertyFinancialDetails.GRM",
                "PropertyFinancialDetails.InPlaceNOI",
                "PropertyFinancialDetails.InPlaceRents",
                "PropertyFinancialDetails.InvestmentPeriod",
                "PropertyFinancialDetails.LeveredIRR",
                "PropertyFinancialDetails.MarkToMarketCapRate",
                "PropertyFinancialDetails.MarketRents",
                "PropertyFinancialDetails.Year1NOI",
                "PropertyFinancialDetails.T12NOI",
                "PropertyFinancialDetails.PotentialGRM",
                "PropertyFinancialDetails.PricePSF",
                "PropertyFinancialDetails.Price",
                "PropertyFinancialDetails.AvgInPlaceRentsBelowMarket",
                "PropertyFinancialDetails.ReturnOnCost",
                "PropertyListingDetails.ClosingCapRate",
                "PropertyListingDetails.SPvsAP",
                "propertysalesdetailsstatus.SalePriceUnit",
                "PropertyPhysicalDetails.Stories",
                "PropertyFinancialDetails.T12CapRate",
                "propertytenancy.Tenancy",
                "propertytenancy.Units",
                "PropertyFinancialDetails.UnleveredIRR",
                "PropertyPhysicalDetails.YearBuilt",
                "PropertyPhysicalDetails.YearRenovated",
                "PropertyPhysicalDetails.ZoningType",
                "BuildingClass.Description",
                "PropertyPhysicalDetails.BuildingClassId",
                "PropertyPhysicalDetails.ParkingRatio",
                "PropertyFinancialDetails.InPlaceCapRate",
                "PropertyListingDetails.SalesPrice",
                "PropertyFinancialDetails.IsConfidential",
                "PropertyFinancialDetails.IsUnpriced",
                "PropertyListingDetails.SalesPricePSF",
                "PropertyListingDetails.CloseDate",
                "PropertyFinancialDetails.IsSaleConfidentialPSF",
                'Property.ConfigurationId'
            );
            $query->with('getPropertyImages');
            $query->with('getPropertyHighlights');
            // $query->leftJoin('FavoriteProperty', 'FavoriteProperty.PropertyId', '=', 'Property.Id');
            // $query->leftJoin('WPOsdUserPropertiesRelations', 'WPOsdUserPropertiesRelations.PropertyId', '=', 'Property.Id');
            $query->leftJoin('SaveStatus', 'SaveStatus.Id', '=', 'Property.SaveStatusId');
            $query->leftJoin('PropertyStatus', 'PropertyStatus.Id', '=', 'Property.PropertyStatusId');
            $query->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyFinancialDetails', 'PropertyFinancialDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyPhysicalDetails', 'PropertyPhysicalDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('BuildingClass', 'BuildingClass.Id', '=', 'PropertyPhysicalDetails.BuildingClassId');
            $query->leftJoin('PropertyListingDetails', 'PropertyListingDetails.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyPressRelease', 'PropertyPressRelease.PropertyId', '=', 'Property.Id');
            $query->leftJoin('PropertyTenancy', 'PropertyTenancy.PropertyId', '=', 'Property.Id');
            $query->leftJoin('AcquisitionCriteriaPropertyRelation', 'AcquisitionCriteriaPropertyRelation.PropertyId', '=', 'Property.Id');
            $query->leftJoin('AcquisitionCriteriaSubType', 'AcquisitionCriteriaSubType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId');
            $query->leftJoin('PropertySalesDetailsStatus', 'PropertySalesDetailsStatus.PropertyId', '=', 'Property.Id');
            $query->leftJoin('AcquisitionCriteriaType', 'AcquisitionCriteriaType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId');

            $query->leftJoin('PropertyAgents', 'PropertyAgents.PropertyId', '=', 'Property.Id');
            $query->leftJoin('Users', 'Users.Id', '=', 'PropertyAgents.AgentId');
            $query->leftJoin('NonHostPropertyMapping', 'Property.Id', '=', 'NonHostPropertyMapping.PropertyId');
            $query->where('property.EntityStatusId', 1);
            $query->where('AcquisitionCriteriaType.ModuleId', 1); 
            $query->whereIn('Property.Id', $bothIds);
            $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);

            $query->where(function ($q) use ($confId) {
                $q->where(function ($q1) use ($confId) {
                    $q1->where('Property.SaveStatusId', 2);
                    $q1->where('Property.ConfigurationId', $confId);
                });
                $q->orWhere(function ($q2) use ($confId) {
                    $q2->where('NonHostPropertyMapping.SaveStatusId', 2);
                    $q2->where('NonHostPropertyMapping.ConfigurationId', $confId);
                });
            });

            if ( isset($request->top_bottom) && $request->top_bottom != '' ) {
                if ( !empty($favProperty)) {
                    $favIds = implode(',', $favProperty);
                    if($request->top_bottom == 'ASC') {
                        $query->orderByRaw('CASE WHEN  Property.Id in ('.$favIds.') THEN  Property.Id END DESC');
                    }

                    if($request->top_bottom == 'DESC') {
                        $query->orderByRaw('CASE WHEN  Property.Id in ('.$favIds.') THEN  Property.Id END ASC');
                    }
                }
            }

            if (isset($request->property_status) && $request->property_status != '') {
                if( is_array($request->property_status) ) {
                    $status = [];
                    if( !empty( $request->property_status ) ) {
                        foreach ($request->property_status as $key => $value ) {
                            $status[] = (int)$value;
                        }
                    }
                    $query->whereIn('Property.PropertyStatusId', $status);
                }
            }

            if ( isset($request->property_search) && $request->property_search != '' ) {
                $query->where(function ($q) use ($request) {
                    $q->orWhere('Property.Name', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('Property.PropertyContent', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Address1', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Address2', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.City', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.State', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Country', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('PropertyAddress.Zipcode', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('AcquisitionCriteriaType.Name', 'like', '%' .$request->property_search . '%');
                    $q->orWhere('AcquisitionCriteriaSubType.Name', 'like', '%' .$request->property_search . '%');

                    $q->orWhere(DB::raw("CONCAT(LTRIM(RTRIM(FirstName)),' ',LTRIM(RTRIM(LastName)))"), 'like', '%' .$request->property_search. '%');
                });
            }

            
            $property = $query->paginate(9);
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);
            $childUrl = $siteUrl;

            $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
            $defaultPathAzurePropertyPath = env('AZURE_STOGARGE_CONTAINER_PROPERTY_IMAGES');
            $defaultPathAzure = $defaultPathAzure.'/'.$defaultPathAzurePropertyPath;
            $featuredImage = env('AZURE_STORAGE_CONTAINER_FEATURED_IMAGE');
            $bannerImage = env('AZURE_STOGARGE_CONTAINER_BANNER_IMAGE');
            $otherImage = env('AZURE_STOGARGE_CONTAINER_OTHER_IMAGE');

            if( !empty( $property ) ) {
                foreach ( $property as $key => $value ) {
                    $propertyName = $value->URLSlug;
                    
                    $FeaturedImage = $value->FeaturedImage;
                    $BannerImage = $value->BannerImage;
                    
                    if( !empty( $value->getPropertyImages ) ) {
                        $OriginalOtherImg = $value->getPropertyImages;
                    } else {
                        $OriginalOtherImg = [];
                    }

                    if( $value->FeaturedImage != null ) {
                        $value->FeaturedImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->FeaturedImage;
                    }

                    if( $value->BannerImage ) {
                        $value->BannerImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->BannerImage;
                    }

                    if( !empty( $value->getPropertyImages ) ) {
                        $otherImageArray = [];
                        foreach ( $value->getPropertyImages as $key1 => $value1 ) {
                            $otherImageArray[] = $defaultPathAzure.'/'.$propertyName.'/'.$value1->Filename;
                        }
                        $value->OtherImage = $otherImageArray;
                    } else {
                        $value->OtherImage = [];
                    }

                    $pr = WpOsdUserPropertiesRelationship::where('PropertyId', $value->Id)->where("UserId", $currentUser->Id)->first();

                    $value->osd_property = $pr;
                    
                    $fav1 = FavoriteProperty::where('AdminId', '=', $currentUser->Id)->where('Favorite', 1)->where('PropertyId', $value->Id)->first();

                    if( !empty($fav1) && $fav1 != null ) {
                        $value->Favorite = 1;
                    } else {
                        $value->Favorite = 0;
                    }

                    unset($value->getPropertyImages);

                    $SMOtherImage = [];
                    if( !empty( $OriginalOtherImg ) ) {
                        $OTHSM = [];
                        foreach ( $OriginalOtherImg as $key1 => $value1 ) {
                            $fileUrl = $defaultPathAzure.'/'.$propertyName.'/sm/'.$value1->Filename;
                            $OTHSM[] = $fileUrl;
                        }
                        $SMOtherImage = $OTHSM;
                    }
                    $LGOtherImage = [];
                    if( !empty( $OriginalOtherImg ) ) {
                        $OTHLG = [];
                        foreach ( $OriginalOtherImg as $key1 => $value1 ) {
                            $OTHLG[] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$value1->Filename;
                        }
                        $LGOtherImage = $OTHLG;
                    }
                    $SM = [];
                    $LG = [];
                    if( $value->FeaturedImage != null ) {
                        $SM['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$FeaturedImage;
                        $LG['FeaturedImage'] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$FeaturedImage;
                    }
                    $SM['OtherImage'] = $SMOtherImage;
                    if( $value->BannerImage ) {
                        $SM['BannerImage'] = $defaultPathAzure.'/'.$propertyName.'/sm/'.$BannerImage;
                        $LG['BannerImage'] = $defaultPathAzure.'/'.$propertyName.'/lg/'.$BannerImage;
                    }
                    $LG['OtherImage'] = $LGOtherImage;
                    $value->SM = $SM;
                    $value->LG = $LG;
                    $value->DaysOnMarket = $value->getPropertyDaysOnMarket($value->Id);
                    $value->LeaseType = $value->getPropertyLeaseType($value->Id);

                    $seller = DB::select("SELECT PropertyContactMapping.UserId FROM PropertyContactMapping INNER JOIN PropertyContactType ON PropertyContactType.ID = PropertyContactMapping.PropertyContactTypeId WHERE PropertyContactType.Description = 'Seller' AND PropertyContactMapping.PropertyId = ".$value->Id);

                    $sallerId = "";
                    if( !empty($seller) && $seller != null && isset($seller[0]) && isset($seller[0]->UserId)) {
                        $sallerId = $seller[0]->UserId;
                    }
                    $buyer = DB::select("SELECT PropertyContactMapping.UserId FROM PropertyContactMapping INNER JOIN PropertyContactType ON PropertyContactType.ID = PropertyContactMapping.PropertyContactTypeID WHERE PropertyContactType.Description = 'Buyer' AND PropertyContactMapping.PropertyId = ".$value->Id);

                    $buyerId = "";
                    if( !empty($buyer) && $buyer != null && isset($buyer[0]) && isset($buyer[0]->UserId)) {
                        $buyerId = $buyer[0]->UserId;
                    }

                    if($buyerId != "") {
                        $buyer = DB::select("SELECT CONCAT(Users.FirstName, ' ', Users.LastName) AS name, Companies.CompanyName FROM Users LEFT JOIN Companies ON Companies.Id = Users.CompanyId WHERE Users.Id = ".$buyerId);

                        if( (!empty($buyer) && $buyer != null && isset($buyer[0])) && (isset($buyer[0]->name) || isset($buyer[0]->CompanyName))) {
                            $value->Buyer = $buyer[0]->name;
                            $value->BuyerCompanyName = $buyer[0]->CompanyName;
                        } else {
                            $value->Buyer = '';
                            $value->BuyerCompanyName = '';
                        }
                    }
                    
                    if($sallerId != "") {
                        $seller = DB::select("SELECT CONCAT(Users.FirstName, ' ', Users.LastName) AS name, Companies.CompanyName FROM Users LEFT JOIN Companies ON Companies.Id = Users.CompanyId WHERE Users.Id = ".$sallerId);

                        if( (!empty($seller) && $seller != null && isset($seller[0])) && (isset($seller[0]->name) || isset($seller[0]->CompanyName))) {
                            $value->Seller = $seller[0]->name;
                            $value->SellerCompanyName = $seller[0]->CompanyName;
                        } else {
                            $value->Seller = '';
                            $value->SellerCompanyName = '';
                        }
                    }

                    $propertyCustomeGrid = self::getCustomeGrid($value);
                    if(!empty($propertyCustomeGrid)) {
                        $value->propertyHighlights = $propertyCustomeGrid;
                        unset($value->getPropertyHighlights);
                    } else {
                        $value->propertyHighlights = [];
                        unset($value->getPropertyHighlights);
                    }
                }
                return response()->json(
                [
                    'status' => 'success',
                    'message' => '',
                    'errors' => [],
                    'data' => $property
                ], 200);

            } else {
                return response()->json(
                [
                    'status' => 'success',
                    'message' => '',
                    'errors' => [],
                    'data' => []
                ], 200);                
            }
        } else {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => '',
                    'errors' => [],
                    'data' => []
                ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function favorite(Request $request) { 
        $request->validate([
            'user_id' => 'required|integer',
            'property_id' => 'required|integer',
        ]);
        
        $databaseConnection = new DatabaseConnection();
        $configurationData  = $databaseConnection->getConfiguration();
        $currentUser        = auth()->guard('api')->user();

        $favoriteProperty = FavoriteProperty::where('PropertyId', $request->property_id)->where('AdminId', $request->user_id)->where('ConfigurationId', $configurationData->ConfigurationId)->first();
        if( !empty( $favoriteProperty ) ) {
            if($favoriteProperty->Favorite == 1) {
                $favoriteProperty->Favorite = 0;
                $favoriteProperty->save();
                return response()
                    ->json(
                        [
                            'status' => 'success',
                            'message' => 'Removed favorites',
                            'errors' => [],
                            'data' => array(
                                'favorite' => false
                            )
                        ], 200);
            } else{
                $favoriteProperty->Favorite = 1;
                $favoriteProperty->save();
                return response()
                    ->json(
                        [
                            'status' => 'success',
                            'message' => 'Favorite list added successfully',
                            'errors' => [],
                            'data' => array(
                                'favorite' => true
                            )
                        ], 200);
            }
        } else {
            $favoriteProperty = new FavoriteProperty([
                'PropertyId' => $request->property_id,
                'AdminId' => $request->user_id,
                'Favorite' => 1,
                'ConfigurationId' => $configurationData->ConfigurationId
            ]);
            $favoriteProperty->save();

            return response()
                ->json(
                    [
                        'status' => 'success',
                        'message' => 'Favorite list added successfully',
                        'errors' => [],
                        'data' => array(
                            'favorite' => true
                        )
                    ], 200);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFavoriteProperty( Request $request ) {
        $currentUser = $request->user();

        $query = FavoriteProperty::query();
        $query->where('FavoriteProperty.AdminId', '=', $currentUser->Id);
        $query->where('FavoriteProperty.Favorite', 1);
        $propertyData = $query->get();

        $PropertyIds = [];
        if( !empty( $propertyData ) ) {
            foreach ($propertyData as $key => $value ) {
                $PropertyIds[] = $value->PropertyId;
            }
        }

        $query = Property::query();
        $query->with('getPropertyImages');
        $query->leftJoin('SaveStatus', 'SaveStatus.ID', '=', 'property.SaveStatusID');
        $query->leftJoin('PropertyStatus', 'PropertyStatus.ID', '=', 'Property.PropertyStatusId');
        $query->leftJoin('AcquisitionCriteriaType', 'AcquisitionCriteriaType.id', '=', 'Property.PropertyStatusId');
        $query->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
        $query->leftJoin('PropertyFinancialDetails', 'PropertyFinancialDetails.PropertyId', '=', 'Property.Id');
        $query->leftJoin('PropertyPhysicalDetails', 'PropertyPhysicalDetails.PropertyId', '=', 'Property.Id');
        $query->leftJoin('BuildingClass', 'BuildingClass.ID', '=', 'PropertyPhysicalDetails.BuildingClassID');
        $query->leftJoin('PropertyListingDetails', 'PropertyListingDetails.PropertyId', '=', 'Property.Id');
        $query->leftJoin('PropertyPressRelease', 'PropertyPressRelease.PropertyId', '=', 'Property.Id');
        $query->leftJoin('PropertyTenancy', 'PropertyTenancy.PropertyId', '=', 'Property.Id');
        $query->whereIn('Property.Id', $PropertyIds);

        $query->select(['Property.Id', 'property.CreatedDate', 'Property.ModifiedDate', 'Property.Title', 'Property.FeaturedImage', 'Property.BannerImage', 'Property.URLSlug', 'Property.Name', 'PropertyStatus.Description AS ListingStatus', 'SaveStatus.Description AS SaveStatus', 'Property.StatusDate', 'AcquisitionCriteriaType.Name AS PropertyType'
            , "PropertyAddress.Latitude", "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2", "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", "PropertyAddress.Zipcode",
            "PropertyFinancialDetails.AskingPrice", "property.Occupancy", "PropertyFinancialDetails.Walt", "PropertyPhysicalDetails.SqFeet", "PropertyFinancialDetails.Year1CapRate","PropertyFinancialDetails.AcresPrice"]);
        $property = $query->paginate(15);

        if(!empty($property)) {
            $siteUrl = $this->removeHttp(getallheaders()['site_url']);
            $childUrl = $siteUrl;
            $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
            $defaultPathAzurePropertyPath = env('AZURE_STOGARGE_CONTAINER_PROPERTY_IMAGES');
            $defaultPathAzure = $defaultPathAzure.'/'.$defaultPathAzurePropertyPath;
            $featuredImage = env('AZURE_STORAGE_CONTAINER_FEATURED_IMAGE');
            $bannerImage = env('AZURE_STOGARGE_CONTAINER_BANNER_IMAGE');
            $otherImage = env('AZURE_STOGARGE_CONTAINER_OTHER_IMAGE');
            foreach ( $property as $key => $value ) {
                $propertyName = $value->URLSlug;
                if( $value->FeaturedImage != null ) {
                    $value->FeaturedImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->FeaturedImage;
                }

                if( $value->BannerImage ) {
                    $value->BannerImage = $defaultPathAzure.'/'.$propertyName.'/'.$value->BannerImage;
                }

                if( !empty( $value->getPropertyImages ) ) {
                    $otherImageArray = [];
                    foreach ( $value->getPropertyImages as $key1 => $value1 ) {
                        $otherImageArray[] = $defaultPathAzure.'/'.$propertyName.'/'.$value1->Filename;
                    }
                    $value->OtherImage = $otherImageArray;
                } else {
                    $value->OtherImage = [];
                }
                unset($value->getPropertyImages);
            }

            return response()
                ->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => $property
                    ], 200);
        } else {
            return response()
                ->json(
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
     */
    public function addPressReleaseHistory( Request $request ) {
        $request->validate([
            'property_id' => 'required',
        ]);
        $currentUser        = $request->user();

        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();

        $propertyPressRelease = PropertyPressRelease::query();
        $propertyPressRelease->where('PropertyId', $request->property_id);
        $propertyPressRelease->select(['PropertyPressRelease.IsPressReleaseFile', 'PropertyPressRelease.PressReleaseFile', 'PropertyPressRelease.PressReleaseLink']);
        $propertyPressRelease = $propertyPressRelease->first();

        $pressRelease = env('PROPERTY_PRESS_DOCUMENT_URL', '');
        $property = Property::where("Id", $request->property_id)->first();

        if(!empty($propertyPressRelease)) {
            $pressReleaseURL = $pressRelease.'/'.$property->URLSlug;

            $fileURL = $pressReleaseURL.'/'.$propertyPressRelease->PressReleaseFile;

            if($propertyPressRelease->IsPressReleaseFile ==1) {
                $pressreleaseHistory = new PressreleaseHistory([
                    'PropertyId' => $request->property_id,
                    'UserId' => $currentUser->Id,
                    'FileName' => $propertyPressRelease->PressReleaseFile,
                    'IP' => $this->getIp(),
                    'CreateDate' => date('Y-m-d H:i:s'),
                    'ConfigurationId' => $configurations->ConfigurationId
                ]);
                $pressreleaseHistory->save();
                return response()->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => $propertyPressRelease,
                        'FileURL' => $fileURL,
                    ], 200);
            } else {
                $pressreleaseHistory = new PressreleaseHistory([
                    'PropertyId' => $request->property_id,
                    'UserId' => $currentUser->Id,
                    'FileName' => $propertyPressRelease->PressReleaseLink,
                    'IP' => $this->getIp(),
                    'CreateDate' => date('Y-m-d H:i:s'),
                    'ConfigurationId' => $configurations->ConfigurationId
                ]);
                $pressreleaseHistory->save();
                return response()->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => $propertyPressRelease,
                        'FileURL' => $propertyPressRelease->PressReleaseLink,
                    ], 200);
            }
            
        } else {
            return response()
                ->json(
                    [
                        'status' => 'success',
                        'message' => "property press release data has been empty.",
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
    public function sharePropertyOnEmail( Request $request ) {
        $request->validate([
            'property_id' => 'required',
            'share_email' => 'required',
            'your_email' => 'required|email',
            'your_name' => 'required',
            'subject' => 'required',
            'message' => 'required'
        ]);

        $str = str_replace(PHP_EOL, ',', trim($request->share_email));
        $str = str_replace('/', ',', $str);
        $requestedEmails = explode(",",$str);

        $subjectName = $request->subject;

        $content = "<div>";
            $content .= "{$request->message}";
        $content .= "</div>";

        $email = new Email();
        $message = $email->email_content('', $content,true);

        $bulkMail = [];
        if(!empty($requestedEmails)) {
            $i = 0;
            foreach ($requestedEmails as $key => $value ) {
                if($value != "") {
                    $bulkMail[$i]['email'] = trim($value);
                    $bulkMail[$i]['name'] = "";
                    $i++;
                }
            }
        }

        $fromTo['name'] = $request->your_name;

        $replyTo = $request->your_email;

        $email->sendEmail( $subjectName, $bulkMail, $message, array(), '', $replyTo, $fromTo);

        return response()->json(
            [
                'status' => 'success',
                'message' => "Email send successfully.",
                'errors' => [],
                'data' => []
            ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function contactProperty( Request $request ) {
        $request->validate([
            'subject' => 'required',
            'message' => 'required',
            'property_id' => 'required',
        ]);
        $databaseConnection = new DatabaseConnection();
        $property = Property::where("Id", $request->property_id)->first();

        $propertyagents = PropertyAgents::query();
        $propertyagents->select(['Users.Id',
                'Users.FirstName',
                'Users.LastName', 
                'Users.Email', 
                'PropertyAgents.AgentId'
            ]);
        $propertyagents->join('Users', 'Users.Id', '=', 'PropertyAgents.AgentId');
        $propertyagents->where('PropertyId', $property->Id);
        $propertyagents->where('IsNotificationEnabled', 1);
        $propertyagents->where('EntityStatusId', 1);
        $propertyagents = $propertyagents->get();
        
        $agentIds = [];
        if ( !empty( $propertyagents ) ) {
            foreach ( $propertyagents as $key => $value ) {
                $agentIds[] = $value->AgentID;
            }
        }

        if(!empty($propertyagents)) {
            $agent_to = array();
            $i = 0;
            foreach ( $propertyagents as $key => $value ) {
                $agent_to[$i]['email'] = $value->Email;
                $agent_to[$i]['name'] = $value->FirstName.' '.$value->LastName;
                $i++;
            }
            $email = new Email();
            $email_subject = "{$request->subject}";
            $content = "";
            $content .= "<b>Property: </b>{$property->Name}<br>";
            $content .= "<b>Message: </b>{$request->message}<br>";
            $message = $email->email_content('', $content, true);
            if ( !empty($agent_to)) {
                $email->sendEmail( $email_subject, $agent_to, $message);
            }

            return response()->json(
                [
                    'status' => 'success',
                    'message' => "Email send successfully",
                    'errors' => [],
                    'data' => []
                ], 200);
        } else {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => "Email send successfully",
                    'errors' => [],
                    'data' => []
                ], 200);
        }
        
        return response()->json(
            [
                'status' => 'success',
                'message' => "Email send successfully",
                'errors' => [],
                'data' => []
            ], 200);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPropertyByGUID ( Request $request ) {
        $request->validate([
            'docID' => 'required',
        ]);
        $property = WpOsdUserPropertiesRelationship::query();
        $property->leftJoin('Property', 'Property.Id', '=', 'WPOsdUserPropertiesRelations.PropertyId');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');
        $property->leftJoin('Users', 'Users.Id', '=', 'PropertyContactMapping.UserId');
        $property->select(["Property.Name", "Users.FirstName AS contactfirstname", "Users.LastName AS contactlastname"]);
        $property->where('WPOsdUserPropertiesRelations.DocId', $request->docID);
        $property = $property->first();
        if( !empty($property) && $property != null ) {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => "",
                    'errors' => [],
                    'data' => $property
                ], 200);
        } else {
            return response()->json(
                [
                    'status' => 'success',
                    'message' => "",
                    'errors' => [],
                    'data' => []
                ], 200);
        }

    }

    /**
     * @param $customeGridJson
     * @return array
     */
    public function getCustomeGrid ($customeGridJson) {
        if(!empty($customeGridJson->getPropertyHighlights)) {
            $price = '$'.number_format($customeGridJson->AskingPrice);
            $price = number_format($customeGridJson->AskingPrice);
            $cap_rate = $customeGridJson->Year1CapRate.'%';
            $occupancy = $customeGridJson->Occupancy.'%';
            $walt = $customeGridJson->Walt.' Years';
            $property_type = $customeGridJson->PropertyType;
            $sq_feet = number_format($customeGridJson->SqFeet);

            if (empty($customeGridJson->AskingPrice)) {
                $price = '-';
            }
            if (empty($cap_rate)) {
                $cap_rate = '-';
            }
            if (empty($occupancy)) {
                $occupancy = '-';
            }
            if (empty($customeGridJson->Walt)) {
                $walt = '-';
            }
            if (empty($property_type)) {
                $property_type = '-';
            }
            if (empty($customeGridJson->SqFeet)) {
                $sq_feet = '-';
            }
            if ($customeGridJson->IsUnpriced == 1) {
                $price = 'Unpriced';
            }



            $customeGridData = [];

            $img_01 = 'cg04';
            $field_01 = $price;
            $label_01 = 'Asking Price';
            $class_01 = '';

            if (!empty($customeGridJson->getPropertyHighlights[0]->Field)) {
                $img_01 = $customeGridJson->getPropertyHighlights[0]->Icon;
                $field_01 = $customeGridJson->getPropertyHighlights[0]->Field;
                $field_01 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_01 = self::getFieldValue($customeGridJson->getPropertyHighlights[0]->Field, $field_01, $customeGridJson->Id, $customeGridJson->IsUnpriced, $customeGridJson->IsConfidential, $customeGridJson);
                $label_01 = $customeGridJson->getPropertyHighlights[0]->DisplayLabel;
                $class_01 = $customeGridJson->getPropertyHighlights[0]->Field;
                $customeGridData[] = array(
                    'name' => $img_01,
                    'icon' => self::getImage64FromName($img_01),
                    'lable' => $label_01,
                    'field' => ($field_val_01 != '') ? $field_val_01 : '-',
                    'class' => $class_01
                );
            }

            $img_02 = 'cg03';
            $field_02 = $cap_rate;
            $label_02 = 'Cap Rate';
            $class_02 = '';
            if (!empty($customeGridJson->getPropertyHighlights[1]->Field)) {
                $img_02 = $customeGridJson->getPropertyHighlights[1]->Icon;
                $field_02 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_02 = self::getFieldValue($customeGridJson->getPropertyHighlights[1]->Field,$field_02, $customeGridJson->Id, $customeGridJson->IsUnpriced, $customeGridJson->IsConfidential, $customeGridJson);
                $label_02 = $customeGridJson->getPropertyHighlights[1]->DisplayLabel;
                $class_02 = $customeGridJson->getPropertyHighlights[1]->Field;
                $customeGridData[] = array(
                    'name' => $img_02,
                    'icon' => self::getImage64FromName($img_02),
                    'lable' => $label_02,
                    'field' => ($field_val_02 != '') ? $field_val_02 : '-',
                    'class' => $class_02
                );
            }

            $img_03 = 'cg05';
            $field_03 = $occupancy;
            $label_03 = 'Occupancy';
            $class_03 = '';
            if (!empty($customeGridJson->getPropertyHighlights[2]->Field)) {
                $img_03 = $customeGridJson->getPropertyHighlights[2]->Icon;
                $field_03 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_03 = self::getFieldValue($customeGridJson->getPropertyHighlights[2]->Field,$field_03, $customeGridJson->id, $customeGridJson->IsUnpriced, $customeGridJson->IsConfidential, $customeGridJson);
                $label_03 = $customeGridJson->getPropertyHighlights[2]->DisplayLabel;
                $class_03 = $customeGridJson->getPropertyHighlights[2]->Field;
                $customeGridData[] = array(
                    'name' => $img_03,
                    'icon' => self::getImage64FromName($img_03),
                    'lable' => $label_03,
                    'field' => ($field_val_03 != '') ? $field_val_03 : '-',
                    'class' => $class_03
                );
            }

            $img_04 = 'cg02';
            $field_04 = $walt;
            $label_04 = 'WALT';
            $class_04 = '';
            if (!empty($customeGridJson->getPropertyHighlights[3]->Field)) {
                $img_04 = $customeGridJson->getPropertyHighlights[3]->Icon;
                $field_04 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_04 = self::getFieldValue($customeGridJson->getPropertyHighlights[3]->Field,$field_04, $customeGridJson->id, $customeGridJson->IsUnpriced, $customeGridJson->IsConfidential, $customeGridJson);
                $label_04 = $customeGridJson->getPropertyHighlights[3]->DisplayLabel;
                $class_04 = $customeGridJson->getPropertyHighlights[3]->Field;
                $customeGridData[] = array(
                    'name' => $img_04,
                    'icon' => self::getImage64FromName($img_04),
                    'lable' => $label_04,
                    'field' => ($field_val_04 != '') ? $field_val_04 : '-',
                    'class' => $class_04
                );
            }

            $img_05 = 'cg06';
            $field_05 = $property_type;
            $label_05 = 'Property Type';
            $class_05 = '';
            if (!empty($customeGridJson->getPropertyHighlights[4]->Field)) {
                $img_05 = $customeGridJson->getPropertyHighlights[4]->Icon;
                $field_05 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_05 = self::getFieldValue($customeGridJson->getPropertyHighlights[4]->Field,$field_05, $customeGridJson->id, $customeGridJson->IsUnpriced, $customeGridJson->IsConfidential, $customeGridJson);
                $label_05 = $customeGridJson->getPropertyHighlights[4]->DisplayLabel;
                $class_05 = $customeGridJson->getPropertyHighlights[4]->Field;
                $customeGridData[] = array(
                    'name' => $img_05,
                    'icon' => self::getImage64FromName($img_05),
                    'lable' => $label_05,
                    'field' => ($field_val_05 != '') ? $field_val_05 : '-',
                    'class' => $class_05
                );
            }

            $img_06 = 'cg01';
            $field_06 = $sq_feet;
            $label_06 = 'Square Feet';
            $class_06 = '';
            if (!empty($customeGridJson->getPropertyHighlights[5]->Field)) {
                $img_06 = $customeGridJson->getPropertyHighlights[5]->Icon;
                $field_06 = $customeGridJson->{$customeGridJson->getPropertyHighlights[4]->Field};
                $field_val_06 = self::getFieldValue($customeGridJson->getPropertyHighlights[5]->Field,$field_06, $customeGridJson->id, $customeGridJson->is_unpriced, $customeGridJson->is_confidential, $customeGridJson);

                $label_06 = $customeGridJson->getPropertyHighlights[5]->DisplayLabel;
                $class_06 = $customeGridJson->getPropertyHighlights[5]->Field;
                $customeGridData[] = array(
                    'name' => $img_06,
                    'icon' => self::getImage64FromName($img_06),
                    'lable' => $label_06,
                    'field' => ($field_val_06 != '') ? $field_val_06 : '-',
                    'class' => $class_06
                );
            }
            return $customeGridData;
        } else {
            return [];
        }

    }

    /**
     * @param $imgname
     * @return string
     */
    public function getImage64FromName ($imgname) {
        switch ($imgname) {
            case 'cg01':
                return '<svg id="menu_2_" data-name="menu (2)" xmlns="http://www.w3.org/2000/svg" width="32.116" height="32.116" viewBox="0 0 32.116 32.116"><g id="Group_75" data-name="Group 75"><g id="Group_74" data-name="Group 74"><path id="Path_189" data-name="Path 189" d="M13.56,0H1.189A1.193,1.193,0,0,0,0,1.189V13.56A1.193,1.193,0,0,0,1.189,14.75H13.56A1.193,1.193,0,0,0,14.75,13.56V1.189A1.193,1.193,0,0,0,13.56,0ZM12.371,12.371H2.379V2.379h9.992Z"/></g></g><g id="Group_77" data-name="Group 77" transform="translate(17.367)"><g id="Group_76" data-name="Group 76"><path id="Path_190" data-name="Path 190" d="M159.56,0H147.189A1.193,1.193,0,0,0,146,1.189V13.56a1.193,1.193,0,0,0,1.189,1.189H159.56a1.193,1.193,0,0,0,1.189-1.189V1.189A1.193,1.193,0,0,0,159.56,0Zm-1.189,12.371h-9.992V2.379h9.992Z" transform="translate(-146)"/></g></g><g id="Group_79" data-name="Group 79" transform="translate(0 17.367)"><g id="Group_78" data-name="Group 78"><path id="Path_191" data-name="Path 191" d="M13.56,146H1.189A1.193,1.193,0,0,0,0,147.189V159.56a1.193,1.193,0,0,0,1.189,1.189H13.56a1.193,1.193,0,0,0,1.189-1.189V147.189A1.193,1.193,0,0,0,13.56,146Zm-1.189,12.371H2.379v-9.992h9.992Z" transform="translate(0 -146)"/></g></g><g id="Group_81" data-name="Group 81" transform="translate(17.367 17.367)"><g id="Group_80" data-name="Group 80"><path id="Path_192" data-name="Path 192" d="M159.56,146H147.189A1.193,1.193,0,0,0,146,147.189V159.56a1.193,1.193,0,0,0,1.189,1.189H159.56a1.193,1.193,0,0,0,1.189-1.189V147.189A1.193,1.193,0,0,0,159.56,146Zm-1.189,12.371h-9.992v-9.992h9.992Z" transform="translate(-146 -146)"/></g></g></svg>';
                break;

            case 'cg02':
                return '<svg id="accept" xmlns="http://www.w3.org/2000/svg" width="34.113" height="35.606" viewBox="0 0 34.113 35.606"><path id="Path_199" data-name="Path 199" d="M69.027,98.637H62.8a1.043,1.043,0,0,0,0,2.086h6.226a1.043,1.043,0,1,0,0-2.086Zm0,0" transform="translate(-57.463 -91.777)"/><path id="Path_200" data-name="Path 200" d="M69.027,158.641H62.8a1.043,1.043,0,1,0,0,2.086h6.226a1.043,1.043,0,1,0,0-2.086Zm0,0" transform="translate(-57.463 -147.609)"/><path id="Path_201" data-name="Path 201" d="M69.027,218.645H62.8a1.043,1.043,0,1,0,0,2.086h6.226a1.043,1.043,0,1,0,0-2.086Zm0,0" transform="translate(-57.463 -203.44)"/><path id="Path_202" data-name="Path 202" d="M69.027,278.648H62.8a1.043,1.043,0,0,0,0,2.086h6.226a1.043,1.043,0,1,0,0-2.086Zm0,0" transform="translate(-57.463 -259.27)"/><path id="Path_203" data-name="Path 203" d="M69.027,339.656H62.8a1.043,1.043,0,1,0,0,2.086h6.226a1.043,1.043,0,1,0,0-2.086Zm0,0" transform="translate(-57.463 -316.035)"/><path id="Path_204" data-name="Path 204" d="M202.7,100.723h6.226a1.043,1.043,0,0,0,0-2.086H202.7a1.043,1.043,0,1,0,0,2.086Zm0,0" transform="translate(-187.632 -91.777)"/><path id="Path_205" data-name="Path 205" d="M202.7,160.727h6.226a1.043,1.043,0,0,0,0-2.086H202.7a1.043,1.043,0,1,0,0,2.086Zm0,0" transform="translate(-187.632 -147.609)"/><path id="Path_206" data-name="Path 206" d="M206.741,219.688a1.043,1.043,0,0,0-1.043-1.043h-3a1.043,1.043,0,1,0,0,2.086h3A1.043,1.043,0,0,0,206.741,219.688Zm0,0" transform="translate(-187.632 -203.44)"/><path id="Path_207" data-name="Path 207" d="M316.9,342.917a1.043,1.043,0,0,0-1.466.161l-2.687,3.35-1.663-1.205a1.043,1.043,0,1,0-1.224,1.689l2.467,1.788a1.043,1.043,0,0,0,1.426-.192l3.308-4.124A1.043,1.043,0,0,0,316.9,342.917Zm0,0" transform="translate(-287.913 -318.856)"/><path id="Path_208" data-name="Path 208" d="M31.627,20.858a8.6,8.6,0,0,0-5-2.511V4.2A4.206,4.206,0,0,0,22.43,0H4.2A4.206,4.206,0,0,0,0,4.2V28.365a4.206,4.206,0,0,0,4.2,4.2H18.862a8.587,8.587,0,0,0,6.588,3.039,8.67,8.67,0,0,0,6.177-14.747ZM2.086,28.365V4.2A2.118,2.118,0,0,1,4.2,2.086H22.43A2.117,2.117,0,0,1,24.544,4.2V18.314a8.675,8.675,0,0,0-7,12.165H4.2a2.117,2.117,0,0,1-2.115-2.115ZM25.45,33.519a6.583,6.583,0,0,1,0-13.166h.119a6.583,6.583,0,0,1-.119,13.165Zm0,0" transform="translate(0 0.001)"/></svg>';
                break;

            case 'cg03':
                return '<svg id="surface1" xmlns="http://www.w3.org/2000/svg" width="38.64" height="38.64" viewBox="0 0 38.64 38.64"><path id="Path_177" data-name="Path 177" d="M38.418,11.371,30.334.458a1.132,1.132,0,0,0-1.819,0L20.431,11.371a1.132,1.132,0,0,0,.91,1.806h2.91v5.471a4.134,4.134,0,0,0-1.9-.461H20.33a4.168,4.168,0,0,0-4.163,4.163v2.36a4.135,4.135,0,0,0-1.9-.461H12.247a4.168,4.168,0,0,0-4.163,4.163v2.36a4.14,4.14,0,0,0-1.9-.461H4.163A4.168,4.168,0,0,0,0,34.477v3.031A1.132,1.132,0,0,0,1.132,38.64H33.466A1.132,1.132,0,0,0,34.6,37.508V13.177h2.91a1.132,1.132,0,0,0,.91-1.806ZM2.264,34.477a1.9,1.9,0,0,1,1.9-1.9H6.184a1.9,1.9,0,0,1,1.9,1.9v1.9H2.264Zm8.083,0V28.414a1.9,1.9,0,0,1,1.9-1.9h2.021a1.9,1.9,0,0,1,1.9,1.9v7.962H10.347Zm8.084-6.063V22.351a1.9,1.9,0,0,1,1.9-1.9h2.021a1.9,1.9,0,0,1,1.9,1.9V36.376H18.431Zm15.035-17.5a1.132,1.132,0,0,0-1.132,1.132V36.376H26.515V12.045a1.132,1.132,0,0,0-1.132-1.132H23.588l5.836-7.879,5.836,7.879Zm0,0" transform="translate(0 0)"/></svg>';
                break;

            case 'cg04':
                return '<svg id="Group_73" data-name="Group 73" xmlns="http://www.w3.org/2000/svg" width="42.189" height="42.202" viewBox="0 0 42.189 42.202"><path id="Path_174" data-name="Path 174" d="M188.449,9.064A9.064,9.064,0,1,0,197.513,0,9.075,9.075,0,0,0,188.449,9.064Zm16.481,0a7.416,7.416,0,1,1-7.416-7.416A7.425,7.425,0,0,1,204.93,9.064Zm0,0" transform="translate(-172.957)"/><path id="Path_175" data-name="Path 175" d="M9.993,236.777a.824.824,0,0,0,1.163.007l4-3.936a2.49,2.49,0,0,0,.587-2.557l.859-.829a2.457,2.457,0,0,1,1.716-.693H29.267a7.378,7.378,0,0,0,5.173-2.1c.057-.056-.435.524,7.473-8.928a3.3,3.3,0,0,0-5.016-4.278l-4.862,5a3.312,3.312,0,0,0-2.562-1.227H20.284a10.631,10.631,0,0,0-4.126-.824,10.2,10.2,0,0,0-9.292,5.269,2.461,2.461,0,0,0-2.2.683l-3.92,3.932a.824.824,0,0,0,0,1.162Zm6.165-18.721a9,9,0,0,1,3.628.755.822.822,0,0,0,.33.069h9.358a1.648,1.648,0,1,1,0,3.3H22.75a.824.824,0,1,0,0,1.648h6.723A3.291,3.291,0,0,0,32.74,220.1c4.7-4.835,5.367-5.514,5.4-5.548a1.648,1.648,0,1,1,2.517,2.13l-7.391,8.836a5.738,5.738,0,0,1-3.994,1.606H18.312a4.1,4.1,0,0,0-2.86,1.155l-.7.676L8.3,222.5a8.508,8.508,0,0,1,7.861-4.44Zm-10.33,5.468a.822.822,0,0,1,1.02-.116c.143.087-.267-.286,7.137,7.108a.826.826,0,0,1,0,1.161l-3.406,3.355-8.1-8.159Zm0,0" transform="translate(-0.501 -194.818)"/><path id="Path_176" data-name="Path 176" d="M267.488,40.813v.966a2.472,2.472,0,0,0,.824,4.8.824.824,0,1,1,0,1.648,1.49,1.49,0,0,1-1.07-.623.824.824,0,0,0-1.262,1.061,3.418,3.418,0,0,0,1.508,1.073V50.7a.824.824,0,0,0,1.648,0v-.966a2.472,2.472,0,0,0-.824-4.8.824.824,0,0,1,0-1.648,1.323,1.323,0,0,1,.891.431.824.824,0,0,0,1.146-1.184,3.184,3.184,0,0,0-1.214-.759v-.96a.824.824,0,1,0-1.648,0Zm0,0" transform="translate(-243.92 -36.692)"/></svg>';
                break;

            case 'cg05':
                return '<svg id="interest" xmlns="http://www.w3.org/2000/svg" width="37.636" height="35.78" viewBox="0 0 37.636 35.78"><path id="Path_193" data-name="Path 193" d="M37.568,114.122a3.2,3.2,0,0,0-3.852-2.471l-3.923.9v-1.6a15.5,15.5,0,0,0,2.189-2.593,15.2,15.2,0,0,0,2.149-5.061,15.507,15.507,0,0,0,.03-6.582.735.735,0,0,0-1.438.308,14.019,14.019,0,0,1-.027,5.954,13.738,13.738,0,0,1-1.942,4.573,13.087,13.087,0,0,1-.961,1.268v-8.148a.735.735,0,0,0-.735-.735H23.243v-7.85a.735.735,0,0,0-.733-.735l-6.55-.022h0a.735.735,0,0,0-.735.735v4.083H9.407a.735.735,0,0,0-.735.735v7.279H7.034v-1a.735.735,0,0,0-.735-.735H5.545a13.913,13.913,0,0,1,.6-7.21c.137-.378.293-.755.462-1.119a.735.735,0,1,0-1.333-.621c-.188.4-.36.821-.512,1.239a15.361,15.361,0,0,0-.71,7.711H.735a.735.735,0,0,0-.735.735v12.921a.735.735,0,0,0,.735.735H6.3a.735.735,0,0,0,.735-.735v-.7H9.8L21,119.77a8.847,8.847,0,0,0,5.44.34l8.779-2.231a3.2,3.2,0,0,0,2.348-3.758ZM28.323,101.4v11.487l-1.965.45c-.211.048-.425.086-.64.116a7.462,7.462,0,0,1-2.465-.071l-.01,0v-1.115A3.4,3.4,0,0,0,23.7,109.4a3.3,3.3,0,0,0-.346-.771q-.05-.082-.105-.16V101.4h5.08Zm-11.63-8.6,5.08.017V107.3l-2.839-1.175-2.241-.928Zm-6.551,4.816h5.08v6.977l-.912-.377a.734.734,0,0,0-.281-.056H10.142ZM5.564,115.341H1.47V103.89H5.564Zm29.294,1.114-8.779,2.231a7.388,7.388,0,0,1-4.541-.284l-11.33-4.444a.734.734,0,0,0-.268-.051h-2.9v-8.284h6.849l4.756,1.969,2.6,1.077a1.839,1.839,0,0,1,.984,2.382,2.1,2.1,0,0,1-.294.517,1.9,1.9,0,0,1-1.927.665l-.017,0c-.042-.011-.085-.023-.127-.037l-4.946-1.9-.061-.024a.735.735,0,1,0-.527,1.372s2.789,1.072,4.915,1.892c.043.017.085.032.128.047l1.633.628a13.5,13.5,0,0,0,1.5.51,8.919,8.919,0,0,0,4.185.053l2.536-.58.008,0,4.814-1.1a1.734,1.734,0,0,1,.814,3.371Zm0,0" transform="translate(0 -84.607)"/><path id="Path_194" data-name="Path 194" d="M118.612,5.228a.732.732,0,0,0,.492-.19A13.813,13.813,0,0,1,129.311,1.5a13.787,13.787,0,0,1,4.008.873A.735.735,0,1,0,133.842,1a15.254,15.254,0,0,0-4.434-.966,15.284,15.284,0,0,0-11.288,3.915.735.735,0,0,0,.493,1.281Zm0,0" transform="translate(-109.212 0)"/><path id="Path_195" data-name="Path 195" d="M351.7,38.491a.736.736,0,0,0,1.037-.073l6.048-6.962a.735.735,0,0,0-1.11-.964l-6.048,6.962A.735.735,0,0,0,351.7,38.491Zm0,0" transform="translate(-325.611 -28.017)"/><path id="Path_196" data-name="Path 196" d="M405.357,99.738a1.845,1.845,0,1,0,1.845,1.845A1.848,1.848,0,0,0,405.357,99.738Zm0,2.221a.375.375,0,1,1,.375-.375A.376.376,0,0,1,405.357,101.959Zm0,0" transform="translate(-373.85 -92.407)"/><path id="Path_197" data-name="Path 197" d="M349.826,30.957a1.845,1.845,0,1,0-1.845-1.845A1.848,1.848,0,0,0,349.826,30.957Zm0-2.221a.375.375,0,1,1-.375.376A.376.376,0,0,1,349.826,28.735Zm0,0" transform="translate(-322.401 -25.261)"/><path id="Path_198" data-name="Path 198" d="M91.636,81.84a.735.735,0,1,0,.27-.892A.741.741,0,0,0,91.636,81.84Zm0,0" transform="translate(-84.849 -74.885)"/></svg>';
                break;

            case 'cg06':
                return '<svg id="buildings_1_" data-name="buildings (1)" xmlns="http://www.w3.org/2000/svg" width="37.636" height="37.636" viewBox="0 0 37.636 37.636"><path id="Path_178" data-name="Path 178" d="M396,274.168h2.088v2.969H396Zm0,0" transform="translate(-366.794 -253.917)"/><path id="Path_179" data-name="Path 179" d="M396,359.5h2.088v2.969H396Zm0,0" transform="translate(-366.794 -332.945)"/><path id="Path_180" data-name="Path 180" d="M86,241.332h2.088V244.3H86Zm0,0" transform="translate(-79.657 -223.506)"/><path id="Path_181" data-name="Path 181" d="M86,326.664h2.088v2.969H86Zm0,0" transform="translate(-79.657 -302.535)"/><path id="Path_182" data-name="Path 182" d="M35.6,35.431V14.873h-3.92V8.306H26.135L23.841,7.159V3.6h-3.92V0H17.716V3.6H13.8V7.159L10.659,8.728v3.5H2.034v23.2H0v2.205H37.636V35.431Zm-22.739,0V10.091L16,8.522V5.807h5.635V8.522l3.136,1.568V35.431Zm-8.625-21h6.42v21H4.239Zm25.238.441h-2.5V10.512h2.5Zm-2.5,2.205H33.4V35.431h-6.42Zm0,0" transform="translate(0)"/><path id="Path_183" data-name="Path 183" d="M209,188.832h2.088V191.8H209Zm0,0" transform="translate(-193.586 -174.884)"/><path id="Path_184" data-name="Path 184" d="M273,188.832h2.088V191.8H273Zm0,0" transform="translate(-252.866 -174.884)"/><path id="Path_185" data-name="Path 185" d="M209,274.168h2.088v2.969H209Zm0,0" transform="translate(-193.586 -253.917)"/><path id="Path_186" data-name="Path 186" d="M273,274.168h2.088v2.969H273Zm0,0" transform="translate(-252.866 -253.917)"/><path id="Path_187" data-name="Path 187" d="M209,359.5h2.088v2.969H209Zm0,0" transform="translate(-193.586 -332.945)"/><path id="Path_188" data-name="Path 188" d="M273,359.5h2.088v2.969H273Zm0,0" transform="translate(-252.866 -332.945)"/></svg>';
                break;

            default:
                return '<svg id="menu_2_" data-name="menu (2)" xmlns="http://www.w3.org/2000/svg" width="32.116" height="32.116" viewBox="0 0 32.116 32.116"><g id="Group_75" data-name="Group 75"><g id="Group_74" data-name="Group 74"><path id="Path_189" data-name="Path 189" d="M13.56,0H1.189A1.193,1.193,0,0,0,0,1.189V13.56A1.193,1.193,0,0,0,1.189,14.75H13.56A1.193,1.193,0,0,0,14.75,13.56V1.189A1.193,1.193,0,0,0,13.56,0ZM12.371,12.371H2.379V2.379h9.992Z"/></g></g><g id="Group_77" data-name="Group 77" transform="translate(17.367)"><g id="Group_76" data-name="Group 76"><path id="Path_190" data-name="Path 190" d="M159.56,0H147.189A1.193,1.193,0,0,0,146,1.189V13.56a1.193,1.193,0,0,0,1.189,1.189H159.56a1.193,1.193,0,0,0,1.189-1.189V1.189A1.193,1.193,0,0,0,159.56,0Zm-1.189,12.371h-9.992V2.379h9.992Z" transform="translate(-146)"/></g></g><g id="Group_79" data-name="Group 79" transform="translate(0 17.367)"><g id="Group_78" data-name="Group 78"><path id="Path_191" data-name="Path 191" d="M13.56,146H1.189A1.193,1.193,0,0,0,0,147.189V159.56a1.193,1.193,0,0,0,1.189,1.189H13.56a1.193,1.193,0,0,0,1.189-1.189V147.189A1.193,1.193,0,0,0,13.56,146Zm-1.189,12.371H2.379v-9.992h9.992Z" transform="translate(0 -146)"/></g></g><g id="Group_81" data-name="Group 81" transform="translate(17.367 17.367)"><g id="Group_80" data-name="Group 80"><path id="Path_192" data-name="Path 192" d="M159.56,146H147.189A1.193,1.193,0,0,0,146,147.189V159.56a1.193,1.193,0,0,0,1.189,1.189H159.56a1.193,1.193,0,0,0,1.189-1.189V147.189A1.193,1.193,0,0,0,159.56,146Zm-1.189,12.371h-9.992v-9.992h9.992Z" transform="translate(-146 -146)"/></g></g></svg>';
                break;
        }
    }

    /**
     * @param $fieldname
     * @param $fieldvalue
     * @param $id
     * @param $unpriced
     * @param $confidential
     * @param $customeGridJson
     * @return string
     */
    public function getFieldValue ($fieldname, $fieldvalue, $id, $unpriced, $confidential, $customeGridJson) {
        if($unpriced == 1 && $fieldname == 'AskingPrice') return 'Unpriced';

        if($confidential && $fieldname == 'sales_price') return 'Confidential';

        /*if ($fieldvalue == '' || empty($fieldvalue) || $fieldvalue == '0' || $fieldvalue == '0.00') {
            return '-';
        }*/
        switch ($fieldname) {
            case 'AskingPrice':
                if ($customeGridJson->AskingPrice == '' || empty($customeGridJson->AskingPrice) || $customeGridJson->AskingPrice == '0' || $customeGridJson->AskingPrice == '0.00') {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->AskingPrice);
                }
                break;
            case 'YearRenovated':
                if ($customeGridJson->YearRenovated == '' || empty($customeGridJson->YearRenovated) || $customeGridJson->YearRenovated == '0' || $customeGridJson->YearRenovated == '0.00') {
                    return '-';
                } else {
                    return ($customeGridJson->YearRenovated == 0) ? '-' : $customeGridJson->YearRenovated;
                }
                break;
            case 'T12NOI':
                /* Currency */
                if ($customeGridJson->T12NOI == '' || empty($customeGridJson->T12NOI) || $customeGridJson->T12NOI == '0' || $customeGridJson->T12NOI == '0.00' || $customeGridJson->T12NOI == 0) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->T12NOI);
                }
                break;
            case 'CapitalInvested':
                if ($customeGridJson->CapitalInvested == '' || empty($customeGridJson->CapitalInvested) || $customeGridJson->CapitalInvested == '0' || $customeGridJson->CapitalInvested == '0.00') {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->CapitalInvested);
                }
                break;
            case 'Units':
                if ($customeGridJson->Units == '' || empty($customeGridJson->Units) || $customeGridJson->Units == '0' || $customeGridJson->Units == '0.00') {
                    return '-';
                } else {
                    return ($customeGridJson->Units != 0) ? $customeGridJson->Units : '-';
                }
                break;
            case 'NumberOfTenants':
                if ($customeGridJson->NumberOfTenants == '' || empty($customeGridJson->NumberOfTenants) || $customeGridJson->NumberOfTenants == '0' || $customeGridJson->NumberOfTenants == '0.00' || $customeGridJson->NumberOfTenants == 0) {
                    return '-';
                } else {
                    return ($customeGridJson->NumberOfTenants != 0) ? $customeGridJson->NumberOfTenants : '-';
                }
                break;
            case 'Building':

                if ($customeGridJson->Building == '' || $customeGridJson->Building == '0' || $customeGridJson->Building == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->Building;
                }
                break;
            case 'BuildingClassID':
                if ($customeGridJson->BuildingClass == '' || empty($customeGridJson->BuildingClass) || $customeGridJson->BuildingClass == '0' || $customeGridJson->BuildingClass == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->BuildingClass;
                }
                break;
            case 'LotSize': 
                if ($customeGridJson->LotSize == '' || empty($customeGridJson->LotSize) || $customeGridJson->LotSize == '0' || $customeGridJson->LotSize == '0.00') {
                    return '-';
                } else {
                    return number_format($customeGridJson->LotSize, 2);
                }
                break;
            case 'GRM':
                if ($customeGridJson->GRM == '' || empty($customeGridJson->GRM) || $customeGridJson->GRM == '0' || $customeGridJson->GRM == '0.00') {
                    return '-';
                } else {
                    return ($customeGridJson->GRM != 0.00) ? $customeGridJson->GRM : '-';
                }
                break;
            case 'PotentialGRM':
                /*decimal value*/
                return $customeGridJson->PotentialGRM;
                break;
            case 'SqFeet':
                if ($customeGridJson->SqFeet == '' || empty($customeGridJson->SqFeet) || $customeGridJson->SqFeet == '0' || $customeGridJson->SqFeet == '0.00') {
                    return '-';
                } else {
                    return number_format($customeGridJson->SqFeet);
                }
                break;
            case 'DaysOnMarket':
                if(!empty($customeGridJson) && isset($customeGridJson->DaysOnMarket) && $customeGridJson->DaysOnMarket !='') {
                    return $customeGridJson->DaysOnMarket;
                } else {
                    return '-';
                }
                break;
            case 'YearBuilt':
                if ($customeGridJson->YearBuilt == '' || empty($customeGridJson->YearBuilt) || $customeGridJson->YearBuilt == '0' || $customeGridJson->YearBuilt == '0.00' || $customeGridJson->YearBuilt == null) {
                    return '-';
                } else {
                    return $customeGridJson->YearBuilt;
                }
                break;
            case 'Year1CapRate':
                if ($customeGridJson->Year1CapRate == '' || empty($customeGridJson->Year1CapRate) || $customeGridJson->Year1CapRate == '0' || $customeGridJson->Year1CapRate == '0.00') {
                    return '-';
                } else {
                    return number_format($customeGridJson->Year1CapRate,2).'%';
                }
                break;
            case 'InPlaceCapRate':
                if ($customeGridJson->InPlaceCapRate == '' || empty($customeGridJson->InPlaceCapRate) || $customeGridJson->InPlaceCapRate == '0' || $customeGridJson->InPlaceCapRate == '0.00' || $customeGridJson->InPlaceCapRate == 0.00) {
                    return '-';
                } else {
                    return number_format($customeGridJson->InPlaceCapRate,2).'%';
                }
                break;
            case 'T12CapRate':
                if ($customeGridJson->T12CapRate == '' || empty($customeGridJson->T12CapRate) || $customeGridJson->T12CapRate == '0' || $customeGridJson->T12CapRate == '0.00' || $customeGridJson->T12CapRate == 0.00) {
                    return '-';
                } else {
                    return number_format($customeGridJson->T12CapRate,2).'%';
                }
                break;
            case 'UnleveredIRR':
                if ($customeGridJson->UnleveredIRR == '' || empty($customeGridJson->UnleveredIRR) || $customeGridJson->UnleveredIRR == '0' || $customeGridJson->UnleveredIRR == '0.00') {
                    return '-';
                } else {
                    return number_format($customeGridJson->UnleveredIRR,2).'%';
                }
                break;
            case 'CashOnCash':
                if ($customeGridJson->CashOnCash == '' || empty($customeGridJson->CashOnCash) || $customeGridJson->CashOnCash == '0' || $customeGridJson->CashOnCash == '0.00') {
                    return '-';
                } else {
                    return number_format($customeGridJson->CashOnCash,2).'%';
                }
                break;
            case 'Occupancy':
                if ($customeGridJson->Occupancy == '' || empty($customeGridJson->Occupancy) || $customeGridJson->Occupancy == '0' || $customeGridJson->Occupancy == '0.00') {
                    return '0%';
                } else {
                    return $customeGridJson->Occupancy.'%';
                }
                break;
            case 'avg_inplace_rents_below_market':
                /*Percntage*/
                return $fieldvalue.'%';
                break;

            case 'Walt':
                if ($customeGridJson->Walt == '' || empty($customeGridJson->Walt) || $customeGridJson->Walt == '0' || $customeGridJson->Walt == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->Walt.' Years';
                }
            case 'investment_period':
                /*Years*/
                return floatval($fieldvalue).' Years';
                break;

            case 'Yr10EquityMultiple':
                if ($customeGridJson->Yr10EquityMultiple == '' || empty($customeGridJson->Yr10EquityMultiple) || $customeGridJson->Yr10EquityMultiple == '0' || $customeGridJson->Yr10EquityMultiple == '0.00' || $customeGridJson->Yr10EquityMultiple == 0.00) {
                    return '-';
                } else {
                    return number_format($customeGridJson->Yr10EquityMultiple,2).'X';
                }
                break;
            case 'CloseDate':
                if ($customeGridJson->CloseDate == '' || empty($customeGridJson->CloseDate) || $customeGridJson->CloseDate == '0' || $customeGridJson->CloseDate == '0000-00-00 00:00:00.000000') {
                    return '-';
                } else {
                    return date("m/d/Y", strtotime($customeGridJson->CloseDate));
                }
                break;
            case 'status_date':
                return date("m/d/Y", strtotime($fieldvalue));
                break;
            case 'ZoningType':
                if ($customeGridJson->ZoningType == '' || empty($customeGridJson->ZoningType) || $customeGridJson->ZoningType == '0' || $customeGridJson->ZoningType == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->ZoningType;
                }
                break;
            case 'Tenancy':
                if ($customeGridJson->Tenancy == '' || empty($customeGridJson->Tenancy) || $customeGridJson->Tenancy == '0' || $customeGridJson->Tenancy == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->Tenancy;
                }
                break;
            case 'AcquisitionCriteriaSubTypeId':
                $fieldvalue = $customeGridJson->PropertyType;
                
                /*$fieldvalue = explode(',', $fieldvalue);
                $fieldvalue = array_slice($fieldvalue,0,2);
                $fieldvalue = implode(', ', $fieldvalue);
                $fieldvalue = str_replace('Flex','R&D/Flex',str_replace(',',' | ', $fieldvalue));*/
                $propertyTypeArray = explode(',', $customeGridJson->PropertyType);
                if( !empty ( $propertyTypeArray ) ) {
                    return isset($propertyTypeArray[0]) ? $propertyTypeArray[0] : '-';
                } else {
                    return $customeGridJson->PropertyType;
                }
                //return $fieldvalue;
                break;
            case 'executed_ca':
                $cards = DB::select("
                    SELECT COUNT(RR.Id) as executed_ca FROM Property W 
                        INNER JOIN WPOsdUserPropertiesRelations RR ON RR.PropertyId = W.Id 
                        INNER JOIN Users UU ON UU.Id = RR.UserId 
                        LEFT JOIN leadadminproperty LA ON LA.PropertyId = W.id AND LA.UserId = RR.UserId 
                        WHERE W.Id = ".$customeGridJson->Id." AND RR.NDASigned=1 AND( LA.StatusId != 11 OR LA.StatusId IS NULL ) GROUP BY W.id");
                if(!empty($cards) && isset($cards[0]->executed_ca)) {
                    return number_format($cards[0]->executed_ca,2);
                } else {
                    return '-';
                }

            case 'om_downloaded':
            $cards = DB::select("
                        SELECT COUNT(DISTINCT(DV.UserId)) as om_downloaded FROM Property P
                        INNER JOIN DocumentVault DV ON DV.PropertyId = P.Id 
                        INNER JOIN Users UU ON DV.UserId = UU.Id 
                        LEFT JOIN LeadAdminProperty LA ON LA.PropertyId = P.id AND LA.UserId = DV.UserId 
                        WHERE P.Id = ".$customeGridJson->Id." AND DV.DocumentType = 'OM' AND( LA.StatusId != 11 OR LA.StatusId IS NULL )
                        GROUP BY P.id");
                if(!empty($cards) && isset($cards[0]->om_downloaded)) {
                    return number_format($cards[0]->om_downloaded,2);
                } else {
                    return '-';
                }
                //return '-';
                break;
            case 'listing_expiration':
                return '-';
                break;
            case 'sp_vs_ap':
                return '-';
                break;
            case 'AcresPrice':
                    if ($customeGridJson->AcresPrice == '' || empty($customeGridJson->AcresPrice) || $customeGridJson->AcresPrice == '0' || $customeGridJson->AcresPrice == '0.00') {
                        return '-';
                    } else {
                        return '$'.number_format($customeGridJson->AcresPrice);
                    }
                break;
            case 'return_on_cost':
                return '-';
                break;
            case 'MarkToMarketCapRate':
                if ($customeGridJson->MarkToMarketCapRate == '' || empty($customeGridJson->MarkToMarketCapRate) || $customeGridJson->MarkToMarketCapRate == '0' || $customeGridJson->MarkToMarketCapRate == '0.00' || $customeGridJson->MarkToMarketCapRate == 0.00) {
                    return '-';
                } else {
                    return number_format($customeGridJson->MarkToMarketCapRate,2).'%';
                }
                break;
            case 'PricePSF':
                if ($customeGridJson->PricePSF == '' || empty($customeGridJson->PricePSF) || $customeGridJson->PricePSF == '0' || $customeGridJson->PricePSF == '0.00' || $customeGridJson->PricePSF == 0.00 || $customeGridJson->PricePSF == 0) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->PricePSF);
                }
                break;
            case 'ParkingRatio':
                if ($customeGridJson->ParkingRatio == '' || empty($customeGridJson->ParkingRatio) || $customeGridJson->ParkingRatio == '0' || $customeGridJson->ParkingRatio == '0.00' || $customeGridJson->ParkingRatio == 0.00 || $customeGridJson->ParkingRatio == 0) {
                    return '-';
                } else {
                    return $customeGridJson->ParkingRatio;
                }
                break;
            case 'InPlaceNOI':
                if ($customeGridJson->InPlaceNOI == '' || empty($customeGridJson->InPlaceNOI) || $customeGridJson->InPlaceNOI == '0' || $customeGridJson->InPlaceNOI == '0.00' || $customeGridJson->InPlaceNOI == 0.00 || $customeGridJson->InPlaceNOI == 0) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->InPlaceNOI);
                }
                break;
            case 'BuyerUserID':
                if ($customeGridJson->Buyer != '' || !empty($customeGridJson->Buyer)) {
                    return $customeGridJson->Buyer;
                } else {
                    return '-';
                }
                break;
            case 'InvestmentPeriod':
                if ($customeGridJson->InvestmentPeriod == '' || empty($customeGridJson->InvestmentPeriod) || $customeGridJson->InvestmentPeriod == '0' || $customeGridJson->InvestmentPeriod == '0.00' || $customeGridJson->InvestmentPeriod == 0.00 || $customeGridJson->InvestmentPeriod == 0) {
                    return '-';
                } else {
                    return $customeGridJson->InvestmentPeriod.' Years';
                }
                break;
            case 'LeveredIRR':
                if ($customeGridJson->LeveredIRR == '' || empty($customeGridJson->LeveredIRR) || $customeGridJson->LeveredIRR == '0' || $customeGridJson->LeveredIRR == '0.00' || $customeGridJson->LeveredIRR == 0.00) {
                    return '-';
                } else {
                    return number_format($customeGridJson->LeveredIRR,2).'%';
                }
                break;
            case 'Price':
                if ($customeGridJson->Price == '' || empty($customeGridJson->Price) || $customeGridJson->Price == '0' || $customeGridJson->Price == '0.00' || $customeGridJson->Price == 0.00 || $customeGridJson->Price == 0) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->Price);
                }
                break;
            case 'Year1NOI':
                if ($customeGridJson->Year1NOI == '' || empty($customeGridJson->Year1NOI) || $customeGridJson->Year1NOI == '0' || $customeGridJson->Year1NOI == '0.00' || $customeGridJson->Year1NOI == 0.00 || $customeGridJson->Year1NOI == 0) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->Year1NOI);
                }
                break;
            case 'InPlaceRents':
                if($customeGridJson->InPlaceRents == '') {
                    return '-';
                } else {
                    return $customeGridJson->InPlaceRents;
                }
                break;
            case 'commission_earned':
                return '-';
                break;
            case 'SalesPrice':
                if ($customeGridJson->SalesPrice == '' || empty($customeGridJson->SalesPrice) || $customeGridJson->SalesPrice == '0' || $customeGridJson->SalesPrice == '0.00' || $customeGridJson->SalesPrice == 0.00 || $customeGridJson->SalesPrice == 0 || $customeGridJson->SalesPrice == null) {
                    return '-';
                } else {
                    if($customeGridJson->IsConfidential == 1) {
                        return "Confidential";
                    } else {
                        return '$'.number_format($customeGridJson->SalesPrice);
                    }
                }
                break;
            case 'SalesPricePSF':
                if ($customeGridJson->SalesPricePSF == '' || empty($customeGridJson->SalesPricePSF) || $customeGridJson->SalesPricePSF == '0' || $customeGridJson->SalesPricePSF == '0.00' || $customeGridJson->SalesPricePSF == 0.00 || $customeGridJson->SalesPricePSF == 0 || $customeGridJson->SalesPricePSF == null) {
                    return '-';
                } else {
                    if($customeGridJson->IsSaleConfidentialPSF == 1) {
                        return "Confidential";
                    } else {
                        return '$'.number_format($customeGridJson->SalesPricePSF);
                    }
                    return '$'.number_format($customeGridJson->SalesPricePSF);
                }
                break;
            case 'EstimatedCommission':
                if ($customeGridJson->EstimatedCommission == '' || empty($customeGridJson->EstimatedCommission) || $customeGridJson->EstimatedCommission == '0' || $customeGridJson->EstimatedCommission == '0.00' || $customeGridJson->EstimatedCommission == 0.00 || $customeGridJson->EstimatedCommission == 0 || $customeGridJson->EstimatedCommission == null) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->EstimatedCommission);
                }
                break;
            case 'MarketRents':
                if($customeGridJson->MarketRents == "") {
                    return '-';
                } else {
                    return $customeGridJson->MarketRents;
                }
                break;
            case 'NoOfOffers':
                $TotalOfferCounts = DB::select("
                SELECT
                    W.Id, COUNT(DISTINCT PL.UserId) as offer
                FROM
                    WPOsdUserPropertiesRelations PR
                    INNER JOIN Property W ON W.id = PR.PropertyId
                    INNER JOIN Users U ON PR.UserId = U.Id
                    INNER JOIN LeadAdminProperty PL ON PL.UserId = PR.UserId AND PL.PropertyId = PR.PropertyId
                    LEFT JOIN LeadAdminProperty LA ON LA.PropertyId = W.Id AND LA.UserId = PR.UserId
                WHERE
                    W.Id = ".$customeGridJson->Id."
                    AND
                        (LA.StatusId != 11
                    OR
                        LA.StatusId IS NULL)
                    GROUP BY W.id;");
                if(!empty($TotalOfferCounts) && $TotalOfferCounts != null) {
                    return $TotalOfferCounts[0]->offer;
                } else {
                    return '-';
                }
                break;
            case 'page_views':

            $cards = DB::select("SELECT COUNT(PT.PropertyId) as page_views FROM Property W LEFT JOIN OEPLPropertyTracker PT ON PT.PropertyId = W.id WHERE W.id = ".$customeGridJson->Id." GROUP BY W.id");    
                if(!empty($cards) && isset($cards[0]->page_views)) {
                    return $cards[0]->page_views;
                } else {
                   return '-'; 
                }
                break;
            case 'AvgInPlaceRentsBelowMarket':
                if ($customeGridJson->AvgInPlaceRentsBelowMarket == '' || empty($customeGridJson->AvgInPlaceRentsBelowMarket) || $customeGridJson->AvgInPlaceRentsBelowMarket == '0' || $customeGridJson->AvgInPlaceRentsBelowMarket == '0.00' || $customeGridJson->AvgInPlaceRentsBelowMarket == 0.00 || $customeGridJson->AvgInPlaceRentsBelowMarket == 0 || $customeGridJson->AvgInPlaceRentsBelowMarket == null) {
                    return '-';
                } else {
                    return number_format($customeGridJson->AvgInPlaceRentsBelowMarket,2).'%';
                }
                break;
            case 'ReturnOnCost':
                if ($customeGridJson->ReturnOnCost == '' || empty($customeGridJson->ReturnOnCost) || $customeGridJson->ReturnOnCost == '0' || $customeGridJson->ReturnOnCost == '0.00' || $customeGridJson->ReturnOnCost == 0.00 || $customeGridJson->ReturnOnCost == 0 || $customeGridJson->ReturnOnCost == null) {
                    return '-';
                } else {
                    return number_format($customeGridJson->ReturnOnCost,2).'%';
                }
                break;
            case 'ClosingCapRate':

                if ($customeGridJson->ClosingCapRate == '' || empty($customeGridJson->ClosingCapRate) || $customeGridJson->ClosingCapRate == '0' || $customeGridJson->ClosingCapRate == '0.00' || $customeGridJson->ClosingCapRate == 0.00 || $customeGridJson->ClosingCapRate == 0 || $customeGridJson->ClosingCapRate == null) {
                    return '-';
                } else {
                    return $customeGridJson->ClosingCapRate.'%';
                }
                 
                break;
            case 'SPvsAP':
                if ($customeGridJson->SPvsAP == '' || empty($customeGridJson->SPvsAP) || $customeGridJson->SPvsAP == '0' || $customeGridJson->SPvsAP == '0.00' || $customeGridJson->SPvsAP == 0.00 || $customeGridJson->SPvsAP == 0 || $customeGridJson->SPvsAP == null) {
                    return '-';
                } else {
                    return number_format($customeGridJson->SPvsAP).'%';
                }
                break;
            case 'SalePriceUnit':
                if ($customeGridJson->SalePriceUnit == '' || empty($customeGridJson->SalePriceUnit) || $customeGridJson->SalePriceUnit == '0' || $customeGridJson->SalePriceUnit == '0.00' || $customeGridJson->SalePriceUnit == 0.00 || $customeGridJson->SalePriceUnit == 0 || $customeGridJson->SalePriceUnit == null) {
                    return '-';
                } else {
                    return '$'.number_format($customeGridJson->SalePriceUnit);
                }
                break;
            case 'SellerUserID':
                return '-';
                break;
            case 'Stories':
                if ($customeGridJson->Stories == '' || empty($customeGridJson->Stories) || $customeGridJson->Stories == '0' || $customeGridJson->Stories == '0.00') {
                    return '-';
                } else {
                    return $customeGridJson->Stories;
                }
                break;
            case 'LeaseType':
                if ($customeGridJson->LeaseType == '' || $customeGridJson->LeaseType == null) {
                    return '-';
                } else {
                    return $customeGridJson->LeaseType;
                }
            default:
                return $fieldvalue;
                break;
        }
    }
}
