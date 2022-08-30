<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Leadbase extends Model
{
    public $timestamps = false;
    protected $table = 'LeadBase';
    protected $fillable = ["UserId", "Base", "CreatedBy", "CreatedDate", "LastModifiedAt"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ID';
}
