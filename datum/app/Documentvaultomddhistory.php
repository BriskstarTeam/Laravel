<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Documentvaultomddhistory extends Model
{
    public $timestamps = false;

    protected $table = 'DocumentVaultOMDDHistory';

    protected $fillable = [
        "UserId",
        "NDASignedDateTime",
        "DueDiligenceRequestStatus",
        "DueDiligenceRequestDateTime",
        "DueDiligenceApprovedDateTime",
        "DocumentRole",
        "NDASigned",
        "DDApproved",
        "DueDiligenceRejectDateTime",
        "CreatedBy",
        "CreatedDate",
        "ConfigurationId",
        "HostedConfigurationId"
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
