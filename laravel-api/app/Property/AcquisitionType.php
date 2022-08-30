<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class AcquisitionType extends Model
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
    //protected $fillable = ["Name", "Status", "moduleId", "created_by", "create_date"];

    protected $fillable = ["Name", "Status", "ModuleId", "CreatedBy", "CreatedDate", "LastModifiedAt", "UpdatedBy", "UpdatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

}
