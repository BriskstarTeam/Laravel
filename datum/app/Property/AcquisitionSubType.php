<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class AcquisitionSubType extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'AcquisitionCriteriaSubType';

    /**
     * @var array
     */
    //protected $fillable = ["Name", "Status", "AcquisitionTypeId", "created_by", "create_date"];

    protected $fillable = ["Name", "Status", "AcquisitionTypeId", "CreatedBy", "CreatedDate", "LastModifiedAt", "UpdatedBy", "UpdatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

}
