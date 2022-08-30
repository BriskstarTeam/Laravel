<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AcquisitioncriteriaPropertyRelation
 * @package App\Property
 */
class AcquisitioncriteriaPropertyRelation extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'AcquisitionCriteriaPropertyRelation';

    /**
     * @var array
     */
    //protected $fillable = ["PropertyId", "AcquisitionCriteriaTypeId", "AcquisitionCriteriaSubTypeId", "Status", "created_by", "create_date", "updated_by", "updated_date"];

    protected $fillable = ["PropertyId", "AcquisitionCriteriaTypeId", "AcquisitionCriteriaSubTypeId", "Status", "CreatedBy", "CreatedDate", "UpdatedBy", "UpdatedDate", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

}
