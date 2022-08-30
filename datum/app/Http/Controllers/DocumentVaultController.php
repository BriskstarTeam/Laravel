<?php

namespace App\Http\Controllers;

use App\Congigurations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Properties;
use App\DocumentVault;
use App\Directory;
use App\DirectoryFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Database\DatabaseConnection;
use App\Traits\Common;
use App\WpOsdUserPropertiesRelationship;

/**
 * Class DocumentVaultController
 * @package App\Http\Controllers
 */
class DocumentVaultController extends Controller
{
    use Common;
    /**
     * @var array
     */
    public $currentUser = [];

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function documentTracking( Request $request ) {
        $this->currentUser = $request->user();

        $request->validate([
            'property_id' => 'required|numeric',
            'document_type' => 'required',
            'file_path' => 'required',
            'file_type' => 'required'
        ]);

        $databaseConnection = new DatabaseConnection();
        $databaseConnection->switchConnection($databaseConnection->getConnectionName(), 'mysql');

        if($request->file_type == 'single') {
            $filePathArray = json_decode($request->file_path, true);
            $filepath_arr = explode('/', $filePathArray);
            $filename = end($filepath_arr);
            $propertyId = $request->property_id;

            foreach ($filepath_arr as $filepath_single) {

                if($filepath_single == $filename){
                    break;
                }

                if($filepath_single == $propertyId){
                    continue;
                }
                $directory = Directory::where('PropertyId', $propertyId)
                    ->where('DirectoryName', $filepath_single)
                    ->select('DirectoryId')->first();

                $directory_id = 0;
                if( !empty ( $directory ) ) {
                    $directory_id = $directory->DirectoryId;
                }
            }

            if($directory_id != 0){

                $directoryFile = DirectoryFile::query();
                $directoryFile->where('FileName', $filename);
                $directoryFile->where('DirectoryId', $directory_id);
                $directoryFile->where( function($q){
                    $q->where('IsDeleted', '=', 0);
                    $q->orWhereNull('IsDeleted');
                });
                $directoryFile = $directoryFile->first();
                $documentVault = new DocumentVault();
                if(!empty($directoryFile)) {
                    $documentVault->property_id = $propertyId;
                    $documentVault->download_datetime = date('Y-m-d H:i:s');
                    $documentVault->user_id = $this->currentUser->Id;
                    $documentVault->documentID = $directory_id;
                    $documentVault->document_type = $request->document_type;
                    $documentVault->file_type = null;
                    $documentVault->file_path = $filename;
                    $documentVault->directory_file_id = $directoryFile->DirectoryFileId;
                    $documentVault->save();
                }

                return response()->json(
                    [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => $documentVault
                    ], 200);
            }
        } else {
            $filePathArray = json_decode($request->file_path, true);
            $propertyId = $request->property_id;
            if( !empty( $filePathArray ) ) {
                foreach ( $filePathArray as $key => $value ) {
                    $filepath_arr = explode('/', $value);
                    $filename = end($filepath_arr);

                    foreach ($filepath_arr as $filepath_single) {

                        if($filepath_single == $filename){
                            break;
                        }

                        if($filepath_single == $propertyId){
                            continue;
                        }
                        $directory = Directory::where('PropertyId', $propertyId)
                            ->where('DirectoryName', $filepath_single)
                            ->select('DirectoryId')->first();

                        $directory_id = 0;
                        if( !empty ( $directory ) ) {
                            $directory_id = $directory->DirectoryId;
                        }
                    }
                    if($directory_id != 0){
                        $directoryFile = DirectoryFile::query();
                        $directoryFile->where('FileName', $filename);
                        $directoryFile->where('DirectoryId', $directory_id);
                        $directoryFile->where( function($q){
                            $q->where('IsDeleted', '=', 0);
                            $q->orWhereNull('IsDeleted');
                        });
                        $directoryFile = $directoryFile->first();

                        $documentVault = new DocumentVault();
                        if(!empty($directoryFile)) {
                            $documentVault->property_id = $propertyId;
                            $documentVault->download_datetime = date('Y-m-d H:i:s');
                            $documentVault->user_id = $this->currentUser->Id;
                            $documentVault->documentID = $directory_id;
                            $documentVault->document_type = $request->document_type;
                            $documentVault->file_type = null;
                            $documentVault->file_path = $filename;
                            $documentVault->directory_file_id = $directoryFile->DirectoryFileId;
                            $documentVault->save();
                        }
                    }
                }
            }
            return response()->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => []
                ], 200);
        }

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unexecutedDownload( Request $request) {
        $this->currentUser = $request->user();

        $request->validate([
            'property_id' => 'required|numeric',
        ]);
        $user_ip = $request->user_ip;

        $databaseConnection = new DatabaseConnection();
        $databaseConnection->switchConnection($databaseConnection->getConnectionName(), 'mysql');


        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('property_id', $request->property_id)->where('user_id', $this->currentUser->Id)->first();
        
        if(!empty($propertyUserRelationship) && $propertyUserRelationship != null ) {
            $propertyUserRelationship->user_id = $this->currentUser->Id;
            $propertyUserRelationship->property_id = $request->property_id;
            $propertyUserRelationship->docuement_role = "Public";
            $propertyUserRelationship->nda_ip = $user_ip;
            $propertyUserRelationship->save();    
        } else {
            $wpOsdUserPropertiesRelationship = new WpOsdUserPropertiesRelationship();
            $wpOsdUserPropertiesRelationship->user_id = $this->currentUser->Id;
            $wpOsdUserPropertiesRelationship->property_id = $request->property_id;
            $wpOsdUserPropertiesRelationship->docuement_role = "Public";
            $propertyUserRelationship->nda_ip = $user_ip;
            $wpOsdUserPropertiesRelationship->save();    
        }
        

        return response()->json(
            [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => $wpOsdUserPropertiesRelationship
            ], 200);
    }
}
