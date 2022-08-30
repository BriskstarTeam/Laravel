<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DueDiligenceRequestHistory extends Model
{
    public $timestamps = false;

    protected $table = 'DueDiligenceRequestHistory';
    
    protected $fillable = [
        "UserPropertyRelationId",
        "UserId",
        "BeforeDocumentRole",
        "AfterDocumentRole",
        "UserIP",
        "CreatedBy",
        "CreatedDate",
        "ConfigurationId",
        "HostedConfigurationId",
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * @param $UserPropertyRelationId
     * @param $UserId
     * @param $BeforeDocumentRole
     * @param $AfterDocumentRole
     * @param $UserIP
     * @param $ConfigurationId
     * @param $HostedConfigurationId
     */
    public function saveDueDiligenceRequestHistory($UserPropertyRelationId, $UserId, $BeforeDocumentRole, $AfterDocumentRole, $UserIP, $ConfigurationId, $HostedConfigurationId) {
        $dueDiligenceRequestHistory = new DueDiligenceRequestHistory();
        $dueDiligenceRequestHistory->UserPropertyRelationId = $UserPropertyRelationId;
        $dueDiligenceRequestHistory->UserId                 = $UserId;
        $dueDiligenceRequestHistory->BeforeDocumentRole     = $BeforeDocumentRole;
        $dueDiligenceRequestHistory->AfterDocumentRole      = $AfterDocumentRole;
        $dueDiligenceRequestHistory->UserIP                 = $UserIP;
        $dueDiligenceRequestHistory->CreatedBy              = $UserId;
        $dueDiligenceRequestHistory->CreatedDate            = date('Y-m-d H:i:s');
        $dueDiligenceRequestHistory->ConfigurationId        = $ConfigurationId;
        $dueDiligenceRequestHistory->HostedConfigurationId  = $HostedConfigurationId;
        $dueDiligenceRequestHistory->save();
    }

}