<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DirectoryFile extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false;

    protected $table = 'DatumDirectoryFile';

    protected $fillable = [
        "DirectoryId",
        "CreatedBy",
        "CreatedByUserId",
        "RemovedBy",
        "RemovedByUserId",
        "DirectoryFileGuid",
        "FileName",
        "MimeType",
        "FileSize",
        "FileType",
        "IsDeleted",
        "CreatedDate",
        "ModifiedDate",
        "ModifiedBy",
        "ModifiedByUserId",
        "RemovedDate",
        "BoxId",
        "LastModifiedAt",
    ];

    protected $primaryKey = 'Id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getParentDirectory() {
        return $this->hasMany(Directory::class, 'DirectoryId', 'DirectoryId')->with('getParentToParent');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getParentToParent() {
        return $this->hasMany(Directory::class, 'ParentId', 'DirectoryId')->where('ParentId', '!=', 0);
    }
}
