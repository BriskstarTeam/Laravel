<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;
use App\FavoriteProperty;
use App\WpOsdUserPropertiesRelationship;
use Illuminate\Support\Facades\DB;

class Property extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'Property';

    /**
     * @var array
     */
    protected $fillable = ["CreatedDate", "ModifiedDate", "Title", "MetaDescription", "PropertyContent", "CDDescription", "FeaturedImage", "BannerImage", "URLSlug", "CreatedBy", "Name", "UpdatedBy", "PropertyStatusId", "StatusDate", "Occupancy", "APN", "EntityStatusId", "FeaturedHome", "PrivateList", "DisplayOrder", "PrivateURL", "MainVideo", "SaveStatusId", "FeaturedImageAltText", "ReportDate", "ConfigurationId", "IsCollab", "IsMigrated","HashId","EncumbranceTypeId","HotelAssetTypeId","HotelClassificationTypeId","LaborTypeId","OwnershipInterestTypeId"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return mixed
     */
    public function getPropertyImages() {
        return $this->hasMany(PropertyImages::class, 'PropertyID')
            ->where('PropertyImages.IsDelete', 0)
            ->select(['PropertyImages.ImageOrder','PropertyImages.PropertyID', 'PropertyImages.ImageURL', 'PropertyImages.Filename'])
            ->orderBy('PropertyImages.ImageOrder', 'ASC');
    }

    /**
     * @return mixed
     */
    public function getPropertyAddress() {
        return $this->hasOne(PropertyAddress::class, 'PropertyID');
    }

    /**
     * @return mixed
     */
    public function getPropertyAgents() {
        return $this->hasMany(PropertyAgents::class, 'PropertyID')
            ->where('propertyagents.Delete', 0)
            ->select('propertyagents.ID', 'propertyagents.PropertyID', 'propertyagents.AgentID');
    }

    /**
     * @return mixed
     */
    public function getFinancialDetails() {
        return $this->hasOne(PropertyFinancialDetails::class, 'PropertyID');
            //->select('propertyfinancialdetails.PropertyID', 'propertyfinancialdetails.PricePSF', 'propertyfinancialdetails.AskingPrice', 'propertyfinancialdetails.T12CapRate', 'propertyfinancialdetails.InvestmentPeriod');
    }

    /**
     * @return mixed
     */
    public function getPropertyType() {
        return $this->hasMany(AcquisitioncriteriaPropertyRelation::class, 'PropertyId');
    }

    /**
     * @return mixed
     */
    public function getPropertyHighlights() {
        return $this->hasMany(PropertyHighlights::class, 'PropertyId')
            ->where('propertyhighlights.EntityStatusId', 1);
    }

    /**
     * @return mixed
     */
     public function getPropertyDaysOnMarket($propertyId) {
       $cards = DB::select("SELECT dbo.fn_GetPropertyDaysOnMarket(".$propertyId.") AS day_on_market");
       $daysOnMarket = 0;
       if(!empty($cards) && isset($cards[0]->day_on_market)) {
           $daysOnMarket = $cards[0]->day_on_market;
       }
       return $daysOnMarket;
     }
    /**
     * @return mixed
     */
    public function getPropertyLeaseType($propertyId) {
      $LeaseType = DB::select("SELECT
              STRING_AGG(AcquisitionCriteriaType.Name, ', ') AS LeaseType
          FROM AcquisitionCriteriaPropertyRelation
              INNER JOIN AcquisitionCriteriaType ON AcquisitionCriteriaType.Id = AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaTypeId
              INNER JOIN preferredmodules ON preferredmodules.id = AcquisitionCriteriaType.ModuleId
          WHERE
              AcquisitionCriteriaPropertyRelation.PropertyId = ".$propertyId."
              AND PreferredModules.Id = 6
              AND AcquisitionCriteriaPropertyRelation.Status = 1");
      $LeaseTypeVal = "";
      if(!empty($LeaseType) && $LeaseType[0]->LeaseType != null ) {
          $LeaseTypeVal = $LeaseType[0]->LeaseType;
      }
      return $LeaseTypeVal;
    }

    /**
    * @return mixed
    */
    public function getAcquisitioncriteriaPropertyRelation($propertyId) {
      $acquisitioncriteriaPropertyRelation = AcquisitioncriteriaPropertyRelation::query();
      $acquisitioncriteriaPropertyRelation->where('PropertyId', $propertyId);
      $acquisitioncriteriaPropertyRelation->where('Status', 1);
      $acquisitioncriteriaPropertyRelation = $acquisitioncriteriaPropertyRelation->get();

      $acquisitionCriteria = [];
      if( !empty( $acquisitioncriteriaPropertyRelation ) ) {
          foreach ( $acquisitioncriteriaPropertyRelation as $key => $value ) {
              $acquisitionType = AcquisitionType::where('Id',$value->AcquisitionCriteriaTypeId)
                  ->select('Name')
                  ->first();
              if ( $value->AcquisitionCriteriaSubTypeId != 0 && $value->AcquisitionCriteriaSubTypeId != null ) {
                  $acquisitionSubType = AcquisitionSubType::where('Id', $value->AcquisitionCriteriaSubTypeId)
                      ->select('Name')->get();
                  $acquisitionType->acquisitionSubType = $acquisitionSubType;
              }
              $acquisitionCriteria[] = $acquisitionType;
          }
      }
      return $acquisitionCriteria;
    }

    /**
    * @return mixed
    */
    public function getFavoriteProperty($propertyId, $currentUserId) {
      $favoriteProperty = FavoriteProperty::where('PropertyId', $propertyId)->where('AdminId', $currentUserId)->first();
      $data = "";
      if(!empty($favoriteProperty)) {
          $data = $favoriteProperty->Favorite;
      } else {
          $data = "";
      }
      return $data;
    }

    /**
    * @return mixed
    */
    public function getEncumbranceTypeData($Id = ''){
        return EncumbranceType::where('Id', $Id)->first();
    }

    /**
    * @return mixed
    */
    public function getLaborTypeData($Id = ''){
        return LaborType::where('Id', $Id)->first();
    }

    /**
    * @return mixed
    */
    public function getHotelClassificationTypeData($Id = ''){
        return HotelClassificationType::where('Id', $Id)->first();
    }

    /**
    * @return mixed
    */
    public function getHotelAssetTypeData($Id = ''){
        return HotelAssetType::where('Id', $Id)->first();
    }

    /**
    * @return mixed
    */
    public function getOwnershipInterestTypeData($Id = ''){
        return OwnershipInterestType::where('Id', $Id)->first();
    }

    /**
    * @return mixed
    */
    public function getPropertyRelation($propertyId, $currentUserId) {
      $Lead = WpOsdUserPropertiesRelationship::where('UserId', $currentUserId)->where('PropertyId', $propertyId)->first();
      return $Lead;
    }

    public function getSubPropertyData($propertyId = ''){
        $sql = "select STRING_AGG(AcquisitionCriteriaSubType.Name, ', ') as Name from AcquisitionCriteriaPropertyRelation 
                  LEFT JOIN AcquisitionCriteriaSubType on AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId = AcquisitionCriteriaSubType.Id 
                  WHERE AcquisitionCriteriaPropertyRelation.PropertyId = ".$propertyId." AND AcquisitionCriteriaPropertyRelation.Status = 1 AND
                  AcquisitionCriteriaPropertyRelation.AcquisitionCriteriaSubTypeId IS NOT NULL";

        $sub = DB::select($sql);
        return $sub[0];
    }
}
