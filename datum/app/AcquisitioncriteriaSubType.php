<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AcquisitioncriteriaSubType
 * @package App
 */
class AcquisitioncriteriaSubType extends Model
{
    public $timestamps = false;
    protected $table = 'AcquisitionCriteriaSubType';
    protected $fillable = ['Name', 'Status', 'AcquisitionTypeId', 'CreatedBy', 'CreatedDate', 'UpdatedBy', 'UpdatedDate'];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getAcquisitioncriteriaType() {
        return $this->belongsTo(AcquisitioncriteriaType::class, 'AcquisitionTypeId')
            ->with('getPreferredModules');
    }
}
