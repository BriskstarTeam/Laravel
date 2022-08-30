<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserAddressDetails extends Model
{
    public $timestamps = false;

    protected $table = 'UserAddressDetails';

    protected $fillable = [
        "Id",
        "UserId",
        "Street",
        "Suite",
        "City",
        "State",
        "ZipCode",
        "Address",
        "Country",
        "WorkPhone",
        "MobilePhone",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param $data
     * @param string $user_id
     * @return UserAddressDetails
     */
    public function addUpdateAddress($data,$user_id = ''){

        $userAddressDetails = UserAddressDetails::where('UserId', $user_id)->first();
        $address = [
            "UserId"        => $user_id,
            "Street"        => isset( $data->street ) ? ucwords($data->street) : '',
            "Suite"         => isset( $data->suite ) ? self::removeSuiteInSuiteFields($data->suite) : '' ,
            "City"          => isset($data->city) ? ucwords($data->city) : '' ,
            "State"         => isset($data->state) ? $data->state : '' ,
            "ZipCode"       => isset( $data->zipcode ) ? $data->zipcode : '' ,
            "Country"       => isset( $data->country ) ? $data->country : '' ,
            "WorkPhone"     => isset( $data->cell_phone ) ? $data->cell_phone : '' ,
            "MobilePhone"   => isset( $data->mobile_phone ) ? $data->mobile_phone : '' ,
        ];
        if(empty($userAddressDetails)){
            $userAddresses = new UserAddressDetails($address);
            $userAddresses->save();
        }else{
            $userAddresses = UserAddressDetails::where('UserId',$user_id )->update($address);
        }
        return $userAddresses;
    }

    /**
     * @param string $suite
     * @return mixed|string|string[]|null
     */
    public function removeSuiteInSuiteFields($suite = "") {
        $suiteString = "";
        if( $suite != "" ) {
            $suiteString  = str_ireplace('SUITE', "", $suite);
        }
        return $suiteString;
    }
}
