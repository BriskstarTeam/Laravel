<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class WpOsdUserPropertiesRelationship
 * @package App
 */
class WpOsdUserPropertiesRelationship extends Model
{
    public $timestamps = false;
    protected $table = 'WPOsdUserPropertiesRelations';

    protected $fillable = [
        "UserId",
        "PropertyId",
        "NDASignedDateTime",
        "NDASentEmail",
        "DuediligenceRequestStatus",
        "DuediligenceRequestDateTime",
        "DuediligenceApprovedDateTime",
        "DuediligenceApprovedBy",
        "DuediligenceApprovedName",
        "DocumentRole",
        "NDASigned",
        "DDApproved",
        "CreatedFrom",
        "NDAIP",
        "NDAPDF",
        "DuediligenceRejectDateTime",
        "DuediligenceRejectBy",
        "DocId",
        "LastModifiedAt",
        "ConfigurationId",
        "HostedConfigurationId"
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function property()
    {
        return $this->belongsTo(Properties::class, 'Id');
    }
}
