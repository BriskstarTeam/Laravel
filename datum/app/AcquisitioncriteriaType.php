<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AcquisitioncriteriaType
 * @package App
 */
class AcquisitioncriteriaType extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'AcquisitionCriteriaType';

    /**
     * @var array
     */
    protected $fillable = ['Name', 'Status', 'ModuleId', 'CreatedBy', 'CreatedDate', 'UpdatedBy', 'UpdatedDate'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getAcquisitionCriteriaSubType() {
        return $this->hasMany(AcquisitioncriteriaSubType::class, 'AcquisitionTypeId')
            ->where('Status', '=', 1)
            ->select('AcquisitionCriteriaSubType.Id', 'AcquisitionCriteriaSubType.Name', 'AcquisitionCriteriaSubType.AcquisitionTypeId');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getPreferredModules() {
        return $this->belongsTo(PreferredModules::class, 'moduleId')->where('preferredmodules.Name', '=', 'PropetyType');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getUserAcquisitioncriteriaSubType () {
        return $this->hasMany(AcquisitioncriteriaSubType::class, 'AcquisitionTypeId');
    }

    /**
     * @param $PropetyType
     * @param string $user
     */
    public function addPropetyType($PropetyType,$user = ''){
        
        if(isset($PropetyType->type) && !empty( $PropetyType ) && $PropetyType != null ) {
            foreach ( $PropetyType->type as $key => $value ) {
                if( isset( $value->sub_type ) && !empty( $value->sub_type ) ) {
                    foreach ( $value->sub_type as $key1 => $value1 ) {
                        $PropetyType1 = array(
                            'UserId' => $user->Id,
                            'AcquisitionCriteriaTypeId' => (int)$value->main,
                            'AcquisitionCriteriaSubTypeId' => (int)$value1,
                            'Status' => 1,
                            'CreatedBy' => $user->Id,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                            'UpdatedBy' => $user->Id
                        );
                        AcquisitioncriteriaContactRelation::insert($PropetyType1);
                    }
                } else {
                    $PropetyType1 = array(
                        'UserId' => $user->Id,
                        'AcquisitionCriteriaTypeId' => (int)$value->main,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                        'UpdatedBy' => $user->Id
                    );
                    AcquisitioncriteriaContactRelation::insert($PropetyType1);
                }
            }
        }
    }

    /**
     * @param $data
     * @param string $user
     */
    public function addAllCriteriaType($data ,$user = ''){
        if( !empty( $data ) ) {
            $data = json_decode($data);
            $data1 = [];
            foreach ( $data as $key => $value ) {
                $data1[] = array(
                    'UserId' => $user->Id,
                    'AcquisitionCriteriaTypeId' => (int)$value,
                    'AcquisitionCriteriaSubTypeId' => null,
                    'Status' => 1,
                    'CreatedBy' => $user->Id,
                    'CreatedDate' => date('Y-m-d H:i:s'),
                    'UpdatedBy' => $user->Id
                );
            }
            $acquisitioncriteriaContactRelation = AcquisitioncriteriaContactRelation::insert($data1);
        }
    }

}
