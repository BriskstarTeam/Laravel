<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightDocumentTracking extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightDocumentTracking';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","UserId","UserIP","DateEntered","LastModifiedAt"];
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}