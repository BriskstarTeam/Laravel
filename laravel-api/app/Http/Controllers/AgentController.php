<?php
namespace App\Http\Controllers;

use App\Property\Property;
use Illuminate\Http\Request;
use App\Traits\AgentApi;
use App\Database\DatabaseConnection;
use App\User;
use App\TeamSubCategory;
use App\PluginActivation;
use App\Traits\Common;
use Illuminate\Support\Facades\DB;

/**
 * Class AgentController
 * @package App\Http\Controllers
 */
class AgentController extends Controller {
    
    use AgentApi, Common;

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show( Request $request ) {

        $databaseConnection = new DatabaseConnection();

        $users = User::query();
        $users->leftJoin('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $users->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', '=', 'Users.Id');
        $users->where('Username', $request->username);
        $users->where('UserContactMapping.Status', '!=', 3);
        $users->with('getLicense');
        $groupName = "(SELECT TeamSubCategory.Name FROM TeamSubCategory WHERE TeamSubCategory.Id = Users.TeamSubCategoryId ) AS TeamSubCategoryName";
        $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email", "Users.Title", "Users.ProfileImage", "Users.Username", "Users.Bio","Users.LinkedIn",
            'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.Country', 'UserAddressDetails.WorkPhone', 'UserAddressDetails.MobilePhone', DB::raw($groupName)]);
        $agent = $users->first();

        if(!empty($agent)) {
            $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
            $agentPath = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
            if( $agent->ProfileImage != null && $agent->ProfileImage != "" ) {
                $agent->ProfileImage = $defaultPathAzure.'/'.$agentPath.'/'.$agent->ProfileImage;
            } else {
                $agent->ProfileImage = "https://datumdoc.azureedge.net/datumfilecontainer/placeholders/agent-placeholder.png";
            }
            if( !empty ($agent) ) {
                $agentClosedProperty = $this->getAgentCloseProperty($agent->Id);
                $agent->closed_property = $agentClosedProperty;
                return response()
                    ->json(
                        [
                            'status' => 'success',
                            'message' => [],
                            'errors' => [],
                            'data' => $agent
                        ], 200);
            } else {
                return response()
                    ->json(
                        [
                            'status' => 'success',
                            'message' => "Agent is not found. Please try again",
                            'errors' => [],
                            'data' => []
                        ], 200);
            }
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getAgentCloseProperty($id) {
        $databaseConnection = new DatabaseConnection();
        $configurations = $databaseConnection->getConfiguration();
        $confId = $configurations->ConfigurationId;
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

        $query->leftJoin('AcquisitionCriteriaType', 'AcquisitionCriteriaType.Id', '=', 'AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId');
        $query->leftJoin('PropertyAgents', 'PropertyAgents.PropertyId', '=', 'Property.Id');
        $query->leftJoin('Users', 'Users.Id', '=', 'PropertyAgents.AgentId');
        $query->leftJoin('NonHostPropertyMapping', 'Property.Id', '=', 'NonHostPropertyMapping.PropertyId');

        $query->where('Property.PrivateList', 0);
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
        $query->where('PropertyAgents.AgentId', $id);
        $query->where('AcquisitionCriteriaPropertyRelation.Status', '=', 1);
        $query->where('property.PropertyStatusId', '=', 5);

        $query->select([
        'Property.Id', 'Property.CreatedDate', 'Property.ModifiedDate', 
        'Property.Title', 'Property.FeaturedImage', 'Property.BannerImage', 
        'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.DisplayOrder', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
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
        "Property.HashId",
        DB::raw('IIF(Property.ConfigurationId = '.$confId.',0,1) AS IsMigrated'),
        DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX)) AS CDDescription')]);
        $query->groupBy(
            'Property.Id', 'property.CreatedDate', 
            'Property.ModifiedDate', 'Property.Title', 
            'Property.FeaturedImage', 'Property.BannerImage', 
            'Property.URLSlug', 'Property.PrivateURLSlug', 'Property.Name', 'Property.DisplayOrder', 'Property.FeaturedCloseDealOrder','PropertyListingDetails.CloseDate', 
            'PropertyStatus.Description', 'SaveStatus.Description',
            'Property.PropertyStatusId', 'AcquisitionCriteriaType.Name',
            'Property.StatusDate', 'Property.StatusDate',"PropertyAddress.Latitude",
            "PropertyAddress.Longitude", "PropertyAddress.Address1", "PropertyAddress.Address2",
            "PropertyAddress.City", "PropertyAddress.State", "PropertyAddress.Country", 
            "PropertyAddress.Zipcode","PropertyFinancialDetails.AskingPrice",
            "PropertyFinancialDetails.IsConfidential","PropertyFinancialDetails.IsUnpriced",
            "PropertySalesDetailsStatus.IsSalePrice","PropertyListingDetails.SalesPrice",
            "PropertyFinancialDetails.AcresPrice", "Property.ConfigurationId", 
            "Property.HashId",
            DB::raw('CAST(Property.CDDescription AS VARCHAR(MAX))'),
        );
        $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
        
        $property = $query->get();

        $defaultPathAzure             = env('AZURE_STORAGE_URL');
        $defaultPathAzurePropertyPath = env('AZURE_STOGARGE_CONTAINER_PROPERTY_IMAGES');
        $defaultPathAzure             = $defaultPathAzure.'/'.$defaultPathAzurePropertyPath;
        $featuredImage                = env('AZURE_STORAGE_CONTAINER_FEATURED_IMAGE');
        $bannerImage                  = env('AZURE_STOGARGE_CONTAINER_BANNER_IMAGE');
        $otherImage                   = env('AZURE_STOGARGE_CONTAINER_OTHER_IMAGE');

        if( !empty( $property ) ) {

            foreach ( $property as $key => $value ) {

                $propertyName = $value->URLSlug;
                if( !empty( $value->getPropertyImages ) ) {
                    $OriginalOtherImg = $value->getPropertyImages;    
                } else {
                    $OriginalOtherImg = [];
                }

                $FeaturedImage = $value->FeaturedImage;
                $BannerImage   = $value->BannerImage;
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgent(Request $request) {
        $databaseConnection = new DatabaseConnection();
        $config = $databaseConnection->getConfiguration();
        $users = User::query();
        $users->leftJoin('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $users->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', '=', 'Users.Id');
        $users->leftJoin('Configurations', 'Configurations.ConfigurationId', '=', 'Users.ConfigurationId');
        
        $confId = $config->ConfigurationId;
        
        $users->where('UserContactMapping.UserTypeId', '!=', 4);
        $users->where('UserContactMapping.Status', 1);
        $users->where('UserContactMapping.ConfigurationId', $confId);
        $users->where('Users.IsSuperAuthorizedAccount', '!=', 1);
        $users->whereNotIn('Users.Id', [4114, 5235, 3953,3704, 4956, 4957]);

        $users->with('getLicense');

        $groupName = "(SELECT TeamSubCategory.Name FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId ) AS GroupName";

        $groupId = "(SELECT TeamSubCategory.Id FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId) AS GroupId";

        $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email", "Users.Title", "Users.ProfileImage", "Users.Username","Users.LinkedIn",
            'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.Country', 'UserAddressDetails.WorkPhone', 'UserAddressDetails.MobilePhone','Configurations.SiteUrl','Configurations.SiteName','Configurations.AgentPageUrl', DB::raw($groupName), DB::raw($groupId)]);

        if(isset($request->order)) {
             $users->orderByRaw("
                CASE 
                    WHEN 
                            Users.Title='Co-Head of U.S. Capital Markets' OR Users.Title LIKE '%Co-Head of U.S. Capital Markets%'
                        THEN 1 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='CEO' OR Users.Title LIKE '%CEO%'
                        THEN 2 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='President' OR Users.Title LIKE 'President%'
                        THEN 3 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Co-President' OR Users.Title LIKE 'Co-President%'
                        THEN 4 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Vice Chairman' OR Users.Title LIKE '%Executive Vice Chairman%'
                        THEN 5 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Vice Chairman' OR Users.Title LIKE '%Vice Chairman%'
                        THEN 6 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Managing Director' OR Users.Title LIKE '%Executive Managing Director%'
                        THEN 7 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Senior Managing Director' OR Users.Title LIKE '%Senior Managing Director%'
                        THEN 8 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Managing Director' OR Users.Title LIKE '%Managing Director%'
                        THEN 9 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Director' OR Users.Title LIKE 'Director%'
                        THEN 10 END DESC, 
                    CASE 
                        WHEN 
                            (Users.Title != 'Associate Director') AND (Users.Title='Associate' OR Users.Title LIKE 'Associate%')
                        THEN 11 END DESC, 
                    CASE 
                        WHEN
							(Users.Title != 'Financial Analyst') AND (Users.Title='Senior Analyst' OR Users.Title LIKE 'Senior Analyst%')
                        THEN 12 END DESC, 
                    CASE 
                        WHEN 
							(Users.Title != 'Financial Analyst') AND (Users.Title='Analyst' OR Users.Title LIKE 'Analyst%')
                        THEN 13 END DESC, 
                    CASE 
                        WHEN Users.LastName is not null THEN Users.LastName END ASC
            ");
            //$users->orderByRaw('DisplayOrder ASC');
        } else {
            /**
             * Order change for specific newmarkAZRetail site
             * for email changes Date: 14-06-2022
             * 
             */
            if ($request->get('ordernewmarkaz')) {
                $users->orderByRaw("CASE WHEN Users.DisplayOrder is null then 1 else 0 end, Users.DisplayOrder");
            } else {
               $users->orderByRaw("
                    CASE 
                        WHEN 
                                Users.Title='Co-Head of U.S. Capital Markets' OR Users.Title LIKE '%Co-Head of U.S. Capital Markets%'
                            THEN 1 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='CEO' OR Users.Title LIKE '%CEO%'
                            THEN 2 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='President' OR Users.Title LIKE 'President%'
                            THEN 3 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='Co-President' OR Users.Title LIKE 'Co-President%'
                            THEN 4 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='Executive Vice Chairman' OR Users.Title LIKE '%Executive Vice Chairman%'
                            THEN 5 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='Vice Chairman' OR Users.Title LIKE '%Vice Chairman%'
                            THEN 6 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='Executive Managing Director' OR Users.Title LIKE '%Executive Managing Director%'
                            THEN 7 END DESC, 
                        CASE 
                            WHEN 
                                Users.Title='Senior Managing Director' OR Users.Title LIKE '%Senior Managing Director%'
                            THEN 8 END DESC, 
                        CASE 
                            WHEN
                                Users.Title='Managing Director' OR Users.Title LIKE '%Managing Director%'
                            THEN 9 END DESC, 
                        CASE 
                            WHEN
                                Users.Title='Director' OR Users.Title LIKE 'Director%'
                            THEN 10 END DESC, 
                        CASE 
                            WHEN 
                                (Users.Title != 'Associate Director') AND (Users.Title='Associate' OR Users.Title LIKE 'Associate%')
                            THEN 11 END DESC, 
                        CASE 
							WHEN
								(Users.Title != 'Financial Analyst') AND (Users.Title='Senior Analyst' OR Users.Title LIKE 'Senior Analyst%')
							THEN 12 END DESC, 
						CASE 
							WHEN 
								(Users.Title != 'Financial Analyst') AND (Users.Title='Analyst' OR Users.Title LIKE 'Analyst%')
							THEN 13 END DESC,
                        CASE 
                            WHEN Users.LastName is not null THEN Users.LastName END ASC
                ");
            }
            
        }
        
        $users = $users->get();
        $agentData = [];
        $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
        $agentPath = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        if( !empty ( $users ) ) {

            foreach ( $users as $key1 => $value1 ) {
                if( $value1->ProfileImage != '' && $value1->ProfileImage != null ) {
                    $value1->ProfileImage = $defaultPathAzure.'/'.$agentPath.'/'.$value1->ProfileImage;
                } else {
                    $value1->ProfileImage = 'https://datumdoc.azureedge.net/datumfilecontainer/placeholders/agent-placeholder.png';
                }
                $agentData[] = $value1;
            }

        }

        $teamSubCategory = TeamSubCategory::query();
        $teamSubCategory->where('ConfigurationId', $config->ConfigurationId);
        $teamSubCategory->where('IsDeleted', '!=', 1);
        $teamSubCategory->select(["Name", "Id"]);
        $teamSubCategory = $teamSubCategory->get();
        if( !empty( $teamSubCategory ) && $teamSubCategory != null ) {
          foreach ( $teamSubCategory as $value ) {
            if( $value->Id != "" ) {
              $users = User::query();
              $users->where('Users.UserTypeId', '!=', 4);
              $users->where('Users.Status', 1);
              $users->where('Users.ConfigurationId', $config->ConfigurationId);
              $users->where('Users.IsSuperAuthorizedAccount', '!=', 1);
              $users->where('Users.TeamSubCategoryId', '=', $value->Id);
              $users->select(['Users.Id']);
              $users = $users->get();
              if( !empty($users) ) {
                $value->size = sizeof($users);
              } else {
                $value->size = 0;
              }
            }
          }
        }
        return response()->json(
        [
            'status' => 'success',
            'message' => [],
            'errors' => [],
            'data' => array('group' =>$teamSubCategory, 'agent'=>$agentData)
        ], 200);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentChildSite ( Request $request ) {

        $databaseConnection = new DatabaseConnection();
        
        $config = $databaseConnection->getConfiguration();

        $users = User::query();
        $users->leftJoin('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
        $users->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', '=', 'Users.Id');
        $users->leftJoin('Configurations', 'Configurations.ConfigurationId', '=', 'Users.ConfigurationId');
        
        $confId = $config->ConfigurationId;
        $users->where('UserContactMapping.Status', 1);
        if(isset($request->UserTypeId) && $request->UserType != "") {
            $users->whereIN('UserContactMapping.UserTypeId', array('1'));
        }else{
            $users->whereNotIN('UserContactMapping.UserTypeId', array('4','1'));
        }
        $users->where('UserContactMapping.ConfigurationId', $config->ConfigurationId);
        $users->where('Users.IsSuperAuthorizedAccount', '!=', 1);

        $users->with('getLicense');

        $groupName = "(SELECT TeamSubCategory.Name FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId ) AS GroupName";

        $groupId = "(SELECT TeamSubCategory.Id FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId) AS GroupId";

        $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email", "Users.Title", "Users.ProfileImage", "Users.Username", 'Users.Bio', 'Users.LinkedIn','UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.Country', 'UserAddressDetails.WorkPhone', 'UserAddressDetails.MobilePhone', 'UserAddressDetails.MobilePhone','Configurations.SiteUrl','Configurations.SiteName','Configurations.AgentPageUrl', DB::raw($groupName), DB::raw($groupId)]);

        if( isset( $request->riki_super_admin ) && $request->riki_super_admin == 'yes') {
            $users->orderByRaw("
                CASE 
                    WHEN 
                            Users.Title='Co-Head of U.S. Capital Markets' OR Users.Title LIKE '%Co-Head of U.S. Capital Markets%'
                        THEN 1 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='CEO' OR Users.Title LIKE '%CEO%'
                        THEN 2 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='President' OR Users.Title LIKE 'President%'
                        THEN 3 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Co-President' OR Users.Title LIKE 'Co-President%'
                        THEN 4 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Vice Chairman' OR Users.Title LIKE '%Executive Vice Chairman%'
                        THEN 5 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Vice Chairman' OR Users.Title LIKE '%Vice Chairman%'
                        THEN 6 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Managing Director' OR Users.Title LIKE '%Executive Managing Director%'
                        THEN 7 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Senior Managing Director' OR Users.Title LIKE '%Senior Managing Director%'
                        THEN 8 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Managing Director' OR Users.Title LIKE '%Managing Director%'
                        THEN 9 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Director' OR Users.Title LIKE 'Director%'
                        THEN 10 END DESC, 
                    CASE 
                        WHEN 
                            (Users.Title != 'Associate Director') AND (Users.Title='Associate' OR Users.Title LIKE 'Associate%')
                        THEN 11 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Senior Analyst' OR Users.Title LIKE '%Senior Analyst%'
                        THEN 12 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Analyst' OR Users.Title LIKE '%Analyst%'
                        THEN 13 END DESC, 
                    CASE 
                        WHEN Users.LastName is not null THEN Users.LastName END ASC
            ");
            //$users->orderBy('Users.DisplayOrder', 'ASC');
        } else {
            $users->orderByRaw("
                CASE 
                    WHEN 
                            Users.Title='Co-Head of U.S. Capital Markets' OR Users.Title LIKE '%Co-Head of U.S. Capital Markets%'
                        THEN 1 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='CEO' OR Users.Title LIKE '%CEO%'
                        THEN 2 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='President' OR Users.Title LIKE 'President%'
                        THEN 3 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Co-President' OR Users.Title LIKE 'Co-President%'
                        THEN 4 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Vice Chairman' OR Users.Title LIKE '%Executive Vice Chairman%'
                        THEN 5 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Vice Chairman' OR Users.Title LIKE '%Vice Chairman%'
                        THEN 6 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Executive Managing Director' OR Users.Title LIKE '%Executive Managing Director%'
                        THEN 7 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Senior Managing Director' OR Users.Title LIKE '%Senior Managing Director%'
                        THEN 8 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Managing Director' OR Users.Title LIKE '%Managing Director%'
                        THEN 9 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Director' OR Users.Title LIKE 'Director%'
                        THEN 10 END DESC, 
                    CASE 
                        WHEN 
                            (Users.Title != 'Associate Director') AND (Users.Title='Associate' OR Users.Title LIKE 'Associate%')
                        THEN 11 END DESC, 
                    CASE 
                        WHEN
                            Users.Title='Senior Analyst' OR Users.Title LIKE '%Senior Analyst%'
                        THEN 12 END DESC, 
                    CASE 
                        WHEN 
                            Users.Title='Analyst' OR Users.Title LIKE '%Analyst%'
                        THEN 13 END DESC, 
                    CASE 
                        WHEN Users.LastName is not null THEN Users.LastName END ASC
            ");
            //$users->orderBy('Users.Id', 'ASC');
        }
        
        if(isset($request->limit) && $request->limit != "") {
            $users = $users->paginate((int)$request->limit);
        } else {
            $users = $users->get();
        }
        
        $agentData = [];
        $defaultPathAzure = env('AZURE_STORAGE_CDN_URL');
        $agentPath = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');
        if( !empty ( $users ) ) {

            foreach ( $users as $key1 => $value1 ) {
                if( $value1->ProfileImage != '' && $value1->ProfileImage != null ) {
                    $value1->ProfileImage = $defaultPathAzure.'/'.$agentPath.'/'.$value1->ProfileImage;
                } else {
                    $value1->ProfileImage = 'https://datumdoc.azureedge.net/datumfilecontainer/placeholders/agent-placeholder.png';
                }
                $agentData[] = $value1;
            }

        }

        /*
         * Only super admin get in Riki
        */
        $superAdmin = [];
        if( isset( $request->riki_super_admin ) && $request->riki_super_admin == 'yes') {

            $users = User::query();
            
            $users->leftJoin('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id');
            $users->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', '=', 'Users.Id');
            $users->leftJoin('Configurations', 'Configurations.ConfigurationId', '=', 'Users.ConfigurationId');
            $users->where('UserContactMapping.UserTypeId', '=', 1);
            $users->where('UserContactMapping.Status', 1);
            $users->where('UserContactMapping.ConfigurationId', $config->ConfigurationId);
            $users->where('Users.IsSuperAuthorizedAccount', '!=', 1);
            $users->with('getLicense');
            
            $groupName = "(SELECT TeamSubCategory.Name FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId ) AS GroupName";

            $groupId = "(SELECT TeamSubCategory.Id FROM TeamSubCategory WHERE TeamSubCategory.IsDeleted != 1 AND TeamSubCategory.Id = Users.TeamSubCategoryId) AS GroupId";

            $users->select(['Users.Id', "Users.FirstName", "Users.LastName", "Users.Email", "Users.Title", "Users.ProfileImage", "Users.Username", 'Users.Bio',
                'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.Country', 'UserAddressDetails.WorkPhone', 'UserAddressDetails.MobilePhone', 'UserAddressDetails.MobilePhone','Configurations.SiteUrl','Configurations.SiteName','Configurations.AgentPageUrl', DB::raw($groupName), DB::raw($groupId)]);

            $users->orderByRaw('LastName ASC');
            $users = $users->first();

            $defaultPathAzure = env('AZURE_STORAGE_URL');
            $agentPath        = env('AZURE_STORAGE_USER_PROFILE_PICTURE_LG_CONTAINER');

            if( !empty ( $users ) ) {
                if( isset($users->ProfileImage) && $users->ProfileImage != '' && $users->ProfileImage != null ) {
                    $users->ProfileImage = $defaultPathAzure.'/'.$agentPath.'/'.$users->ProfileImage;
                } else {
                    $users->ProfileImage = 'https://datumdoc.azureedge.net/datumfilecontainer/placeholders/agent-placeholder.png';
                }
                $superAdmin = $users;
            }

        }

        return response()->json(
        [
            'status' => 'success',
            'message' => [],
            'errors' => [],
            'data' => $agentData,
            'super_admin' =>$superAdmin
        ], 200);
    }
}