<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightTags extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightTags';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","TagName","IsSelected","IsDeleted","CreatedBy","CreatedDate","DeletedBy","DeletedDate","LastModifiedAt"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}