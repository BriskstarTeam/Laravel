<?php

namespace App\Insights;

use Illuminate\Database\Eloquent\Model;

class InsightImages extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $table = 'InsightImages';

    /**
     * @var array
     */
    protected $fillable = ["InsightId","ImageId","UserId","IsProcess","IsDelete","CreatedDate","UniqueId","Filename","IsSaveFTP","ImageMode","Thumbnail","ImageOrder","IsUploaded","LastModifiedAt"];


    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'Id';
}