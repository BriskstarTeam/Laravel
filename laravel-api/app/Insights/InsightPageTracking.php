<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightPageTracking extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightPageTracking';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","UserId","UserIP","DateEntered","BrowseFromMobile","LastModifiedAt","ConfigurationId","HostedConfigurationId"];
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}