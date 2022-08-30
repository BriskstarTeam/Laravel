<?php
namespace App\Database;

use Illuminate\Support\Facades\DB;
use App\Congigurations;
use App\Property\Property;
use App\Insights\WPPosts;
use Illuminate\Support\Facades\Config;
use App\User;
use App\DashboardParentAdmin;
use App\UserAddressDetails;
use App\Companies;

/**
 * Class DatabaseConnection
 * @package App\Database
 */
class DatabaseConnection
{
    /**
     * @var |null
     */
    public $currentConnection = null;

    /**
     * DatabaseConnection constructor.
     */
    public function __construct() {
        if(isset( getallheaders()['activation_key'] ) && getallheaders()['activation_key'] != "" ) {
            $connectionName = DB::connection()->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $this->currentConnection = $connectionName;
        }
    }

    /**
     * @param $propertyId
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getPropertyConfiguration($propertyId) {
        $property = Property::query();
        $property->where('Property.Id', $propertyId);
        $property->join('Configurations', 'Configurations.ConfigurationId', 'Property.ConfigurationId');
        $property->select(['Property.Id', 'Configurations.ConfigurationId', 'Configurations.SiteName', 'Configurations.SiteUrl', 'Configurations.HostName', 'Configurations.DbUserName', 'Configurations.DbPassword', 'Configurations.DatabaseName']);
        $property = $property->first();
        return $property;
    }
    /**
     * @param $propertyId
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getInsightConfiguration($HashId) {
        $insight = WPPosts::query();
        $insight->where('WPPosts.HashId', $HashId);
        $insight->join('Configurations', 'Configurations.ConfigurationId', 'WPPosts.ConfigurationId');
        $insight->select(['WPPosts.Id','WPPosts.UrlSlug','WPPosts.HashId', 'Configurations.ConfigurationId', 'Configurations.SiteName', 'Configurations.SiteUrl', 'Configurations.HostName', 'Configurations.DbUserName', 'Configurations.DbPassword', 'Configurations.DatabaseName']);
        $insight = $insight->first();
        return $insight;
    } 

    /**
     * @return mixed
     */
    public function getConnectionName() {
       if(isset( getallheaders()['activation_key'] ) && getallheaders()['activation_key'] != "" ) {
           return $this->currentConnection;
       }
    }

    /**
     * @return $congigurations
     * @return false
     */
    public function getConfiguration() {
        if(isset( getallheaders()['activation_key'] ) && getallheaders()['activation_key'] != "" ) {
            $congigurations = Congigurations::where('ActivationKey', getallheaders()['activation_key'])->first();
            return $congigurations;
        }
    }
}
