<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class NdaTracker extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false;
    protected $table = 'NDATracker';
    protected $fillable = ['UserId', 'PropertyId', 'IPAddress', 'CreatedDateTime', 'EmailTo', 'PDFFile', 'NDASigned', 'DocId', 'ConfigurationId', 'HostedConfigurationId'];
    protected $primaryKey = 'Id';
}
