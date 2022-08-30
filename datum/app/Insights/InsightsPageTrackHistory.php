<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightsPageTrackHistory extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightsPageTrackHistory';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","UserId","Token","ConfigurationId","HostedConfigurationId","UserIP","Status","CreatedBy","CreatedDate"];
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}