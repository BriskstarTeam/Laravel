<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentVault extends Model
{
    public $timestamps = false;
    protected $table = 'DocumentVault';
    //protected $fillable = ["property_id", "download_datetime", "user_id", "documentID", "document_type", "file_type", "file_path", "directory_file_id", "ConfigurationId"];

    protected $fillable = ["PropertyId", "DownloadDateTime", "UserId", "DocumentId", "DocumentType", "FileType", "FilePath", "DirectoryFileId", "LastModifiedAt", "ConfigurationId"];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
}
