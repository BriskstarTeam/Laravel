<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Directory extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false;

    protected $table = 'DatumDirectory';

    protected $fillable = [
        "PropertyId",
        "CreatedBy",
        "RemovedBy",
        "ModifiedBy",
        "DirectoryGUID",
        "ParentId",
        "DirectoryName",
        "IsDeleted",
        "CreatedDate",
        "ModifiedDate",
        "RemovedDate",
        "BoxId",
        "LastModifiedAt",
    ];

    protected $primaryKey = 'Id';

    /**
     * @return mixed
     */
    public function getFiles() {
        return $this->hasMany(DirectoryFile::class, 'DirectoryId', 'DirectoryId')->where('IsDeleted', 0);
    }

    /**
     * @return mixed
     */
    public function getChildDirectory() {
        return $this->hasMany(Directory::class, 'ParentId', 'DirectoryId')->with('getChildFiles');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getChildFiles() {
        return $this->hasMany(DirectoryFile::class, 'DirectoryId', 'DirectoryId');
    }
}
