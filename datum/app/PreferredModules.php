<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PreferredModules extends Model
{
    public $timestamps = false;
    protected $table = 'PreferredModules';
    protected $fillable = ["Name", "Status", "CreatedBy", "CreatedDate", "UpdatedBy", "UpdatedDate"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getAcquisitionCriteriaType() {
        return $this->hasMany(AcquisitioncriteriaType::class, 'ModuleId')
            ->where('Status', '=', 1)
            ->with('getAcquisitionCriteriaSubType')
            ->select('AcquisitionCriteriaType.Id', 'AcquisitionCriteriaType.Name', 'AcquisitionCriteriaType.ModuleId');
    }

}

