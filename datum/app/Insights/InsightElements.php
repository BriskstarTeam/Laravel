<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightElements extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightElements';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","InsightElementTypeId","ElementOrder","Content", "LastModifiedAt"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}
