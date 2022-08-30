<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Acquisitioncriteria1031Relation extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'AcquisitionCriteria1031Relation';

    /**
     * @var array
     */
    protected $fillable = ['TrackerId', 'AcquisitionCriteriaTypeId','AcquisitionCriteriaSubTypeId', 'Status', 'CreatedBy', 'CreatedDate', 'UpdatedBy', 'UpdatedDate', 'LastModifiedAt'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    public function addUpdate1031($data = [],$user =[]){
        if(isset( $data->firstName )){
            $FirstName = str_replace("\'","''",ucwords($data->firstName));
            

        }
        if(isset( $data->lastName )){
            $LastName = str_replace("\'","''",ucwords(self::lastNameFields($data->lastName)));
        }

        $name = $FirstName .' '.$LastName;

       
        $insert1031ExchangeStatus = DB::select("exec Update1031ExchangeStatus {$user->Id},'{$name} ',{$data->exchange_status}, {$user->Id} ") ;

        if($insert1031ExchangeStatus[0]->Result > 0 && $insert1031ExchangeStatus[0]->TrackerId) {    
            $trackerId = $insert1031ExchangeStatus[0]->Result;
            $PropetyType = json_decode($data->PropetyType);
            $PeferredMarketType = $data->PeferredMarketType;
            $InvestmentStraragy = $data->InvestmentStraragy;
            $ReturnMetrics = $data->ReturnMetrics;
            $PrefferedDealSize = $data->PrefferedDealSize;

            if(isset($PropetyType->type) && !empty( $PropetyType ) && $PropetyType != null ) {
                foreach ( $PropetyType->type as $key => $value ) {
                    if( isset( $value->sub_type ) && !empty( $value->sub_type ) ) {
                        foreach ( $value->sub_type as $key1 => $value1 ) {
                            $PropetyType1 = array(
                                'TrackerId' => $trackerId,
                                'AcquisitionCriteriaTypeId' => (int)$value->main,
                                'AcquisitionCriteriaSubTypeId' => (int)$value1,
                                'Status' => 1,
                                'CreatedBy' => $user->Id,
                                'CreatedDate' => date('Y-m-d H:i:s'),
                            );
                            $acquisitioncriteria1031Relation = Acquisitioncriteria1031Relation::insert($PropetyType1);
                        }
                    } else {
                        $PropetyType1 = array(
                            'TrackerId' => $trackerId,
                            'AcquisitionCriteriaTypeId' => (int)$value->main,
                            'AcquisitionCriteriaSubTypeId' => null,
                            'Status' => 1,
                            'CreatedBy' => $user->Id,
                            'CreatedDate' => date('Y-m-d H:i:s'),
                        );
                        $acquisitioncriteria1031Relation = Acquisitioncriteria1031Relation::insert($PropetyType1);
                    }
                }
            }
            
            if( !empty( $PeferredMarketType ) ) {
                $PeferredMarketType = json_decode($PeferredMarketType);
                $peferredMarketType1 = [];
                foreach ( $PeferredMarketType as $key => $value ) {
                    $peferredMarketType1[] = array(
                        'TrackerId' => $trackerId,
                        'AcquisitionCriteriaTypeId' => (int)$value,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                    );
                }
                $acquisitioncriteria1031Relation = Acquisitioncriteria1031Relation::insert($peferredMarketType1);
            }
            if( !empty( $InvestmentStraragy ) ) {
                $InvestmentStraragy = json_decode($InvestmentStraragy);
                $investmentStraragy1 = [];
                foreach ( $InvestmentStraragy as $key => $value ) {
                    $investmentStraragy1[] = array(
                        'TrackerId' => $trackerId,
                        'AcquisitionCriteriaTypeId' => (int)$value,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                    );
                }
                $acquisitioncriteria1031Relation = Acquisitioncriteria1031Relation::insert($investmentStraragy1);
            }

            if( !empty( $ReturnMetrics ) ) {
                $returnMetrics1 = [];
                $ReturnMetrics = json_decode($ReturnMetrics);
                foreach ( $ReturnMetrics as $key => $value ) {
                    $returnMetrics1[] = array(
                        'TrackerId' => $trackerId,
                        'AcquisitionCriteriaTypeId' => (int)$value,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                    );
                }
                $acquisitioncriteria1031Relation = Acquisitioncriteria1031Relation::insert($returnMetrics1);
            }

            if( !empty( $PrefferedDealSize ) ) {
                $prefferedDealSize1 = [];
                $PrefferedDealSize = json_decode($PrefferedDealSize);
                foreach ( $PrefferedDealSize as $key => $value ) {
                    $prefferedDealSize1[] = array(
                        'TrackerId' => $trackerId,
                        'AcquisitionCriteriaTypeId' => (int)$value,
                        'AcquisitionCriteriaSubTypeId' => null,
                        'Status' => 1,
                        'CreatedBy' => $user->Id,
                        'CreatedDate' => date('Y-m-d H:i:s'),
                    );
                }
                $acquisitioncriteriaContactRelation = Acquisitioncriteria1031Relation::insert($prefferedDealSize1);
            }
        }
        return true;
    }

    /**
     * @param string $lastName
     * @return string
     */
    public function lastNameFields( $lastName = "" ) {
        $lName = "";
        if ( $lastName != "" ) {
            $op = substr($lastName, 0, 2);
            if ($op == "mc" || $op == "Mc" || $op == "MC") 
            {
                $lastName1 = ucwords("Mc".strtoupper(substr($lastName, 2, 1)).strtolower(substr($lastName, 3)));
                $lName = $lastName1;
            } else {

                $lastName1 = ucwords(strtolower($lastName));
                $lName = $lastName1;
            } 
        }
        $lName = implode("'", array_map('ucfirst', explode("'", $lName)));
        return $lName;
    }
}
