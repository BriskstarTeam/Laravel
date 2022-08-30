<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AcquisitioncriteriaContactRelation
 * @package App
 */
class AcquisitioncriteriaContactRelation extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'AcquisitionCriteriaUserRelation';

    /**
     * @var array
     */
    protected $fillable = [
        "UserId",
        "AcquisitionCriteriaTypeId",
        "AcquisitionCriteriaSubTypeId",
        "Status",
        "CreatedBy",
        "UpdatedBy",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getUserAcquisitioncriteriaType() {
        return $this->hasMany(AcquisitioncriteriaType::class, 'Id', 'AcquisitionCriteriaTypeId')
            ->with('getUserAcquisitioncriteriaSubType');
    }

    /**
     * @param string $PropetyType
     * @param string $user
     */
    public function addPropetyType($PropetyType = '',$user = ''){

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
     * @param string $PropetyType
     * @param string $user
     */
    public function updatePropetyType($PropetyType = '',$user = ''){
        if( !empty( $PropetyType ) && $PropetyType != null && isset($PropetyType->type) && $PropetyType->type ) {

            foreach ( $PropetyType->type as $key => $value ) {
                if( isset( $value->sub_type ) && !empty( $value->sub_type ) ) {
                    foreach ( $value->sub_type as $key1 => $value1 ) {
                        $acquSubTypeId = AcquisitioncriteriaContactRelation::where('AcquisitionCriteriaTypeId', $key)->where('AcquisitionCriteriaSubTypeId', $value1)->where('UserId', $user->Id)
                        ->first();
                        if( empty($acquSubTypeId) && $acquSubTypeId == null ) {
                            $PropetyType1 = array(
                                'UserId' => $user->Id,
                                'AcquisitionCriteriaTypeId' => (int)$key,
                                'AcquisitionCriteriaSubTypeId' => (int)$value1,
                                'Status' => 1,
                                'CreatedBy' => $user->Id,
                                'CreatedDate' => date('Y-m-d H:i:s'),
                                'UpdatedBy' => $user->Id
                            );
                            AcquisitioncriteriaContactRelation::insert($PropetyType1);
                        } else {
                            $acquSubTypeId->Status = 1;
                            $acquSubTypeId->save();
                        }
                    }
                } else {
                    $acquMainTypeId = AcquisitioncriteriaContactRelation::where('AcquisitionCriteriaTypeId', $key)->WhereNull('AcquisitionCriteriaSubTypeId')->where('UserId', $user->Id)
                        ->first();

                    if( empty( $acquMainTypeId ) && $acquMainTypeId == null ) {
                        $PropetyType1 = array(
                                'UserId' => $user->Id,
                                'AcquisitionCriteriaTypeId' => (int)$key,
                                'AcquisitionCriteriaSubTypeId' => null,
                                'Status' => 1,
                                'CreatedBy' => $user->Id,
                                'CreatedDate' => date('Y-m-d H:i:s'),
                                'UpdatedBy' => $user->Id
                            );
                            AcquisitioncriteriaContactRelation::insert($PropetyType1);
                    } else {
                        $acquMainTypeId->Status = 1;
                        $acquMainTypeId->save();
                    }
                }
            }
        }
    }

    /**
     * @param string $data
     * @param string $user
     */
    public function addAllCriteriaType($data = '',$user = ''){
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

    /**
     * @param string $data
     * @param string $user
     */
    public function addAllUpdateCriteriaType($data = '',$user = ''){

        $data = json_decode($data, true);
        if( !empty( $data ) && $data != null ) {
            foreach ( $data as $key => $value ) {
                $query = AcquisitioncriteriaContactRelation::query();
                $query->WhereNull('AcquisitionCriteriaSubTypeId');
                $query->where('AcquisitionCriteriaTypeId', $value);
                $query->where('UserId', $user->Id);
                $pfMarket = $query->get();

                if($pfMarket->isEmpty()) {
                    AcquisitioncriteriaContactRelation::insert(array(
                        'UserId' => $user->Id,
                        'AcquisitionCriteriaTypeId' => (int)$value,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                        'UpdatedBy' => $user->Id
                    ));
                } else {
                    $acDt = AcquisitioncriteriaContactRelation::where('AcquisitionCriteriaTypeId', $value)->where('UserId', $user->Id)->first();
                    $acDt->Status = 1;
                    $acDt->save();
                }
            }
        }
    }
}
