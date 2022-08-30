<?php
namespace App\Document;

include_once base_path('lovepdf/vendor/autoload.php');
include_once base_path('MPDF_Lib/mpdf.php');

use App\Companies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Database\DatabaseConnection;
use App\Property\Property;
use App\User;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use App\Mail\Email;
use Illuminate\Support\Facades\Storage;
use App\Directory;
use App\DirectoryFile;
use URL;
use Ilovepdf\Ilovepdf;
use mPDF;

/**
 * Class Document
 * @package App\Document
 */
class Document {

    /**
     * @param string $propertyId
     * @param string $userId
     * @param string $userIP
     * @param string $documentType
     */
    public $propertySlug = "";
    public $documentType = "";
    public $propertyId = "";

    /**
     * @param string $propertyId
     * @param string $userId
     * @param string $userIP
     * @param string $documentType
     * @return string
     */
    public function propertyDocumentPreview ( $propertyId = '', $userId = '', $userIP = '', $documentType = '') {

        $databaseConnection = new DatabaseConnection();

        $user = User::where('Users.Id', $userId)->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->first();
        
        $property = Property::query();
        if(is_numeric($propertyId)) {
            $property->where('Property.Id', $propertyId);
        } else {
            $property->where('Property.URLSlug', $propertyId);
        }
        $property->select(['Property.Id', 'Property.URLSlug', 'Property.Name']);
        $property = $property->first();

        $documentPreviewHtml = '';
        if ( !empty( $property ) && $property != null ) {
            $propertyDocBlobName = env('DOCUMENT_VAULT_NAME', '');
            $blobUrl = env('DOCUMENT_VAULT_URL', '').'/';
            $directory = $propertyDocBlobName.'/'.$property->URLSlug.'/'.$documentType.'/';
            $docSlug = $propertyDocBlobName.'/'.$property->URLSlug.'/';
            
            
            $this->propertySlug = $property->URLSlug;
            $this->documentType = $documentType;
            $this->propertyId = $property->Id;
            $directories = Storage::disk('azure')->allFiles($directory);
            if(!empty($directories)) {
                foreach ($directories as $key => $value ) {
                    $directories[$key] = str_replace($docSlug, '', $value);
                }
                $documentVaultTree = self::createTreeArray($directories);
                $documentPreviewHtml = self::createHTML($documentVaultTree);
            } else {
                $documentPreviewHtml = self::docuementNotFoundHtml($documentType);
            }
        } else {
            $documentPreviewHtml = self::docuementNotFoundHtml($documentType);
        }
        return $documentPreviewHtml;
    }

    /**
     * @param array $string
     * @return array
     */
    public function createTreeArray($string = array()) {
        $result = array();
        foreach($string as $item){
            $itemparts = explode("/", $item);

            $last = &$result;

            for($i=0; $i < count($itemparts); $i++){
                $part = $itemparts[$i];
                if($i+1 < count($itemparts))
                    $last = &$last[$part];
                else 
                    $last[$item] = array();
            }
        }
        return $result;
    }

    /**
     * @param array $data
     * @return string
     */
    public function createHTML( $data = array() ) {
        $html = '<div class="document_heading_section">';
            $html .= '<div class="title_name">Name</div>';
            $html .= '<div class="title_added_date">Date Added</div>';
        $html .= '</div>';
        $html .= '<ul class="oepl_Folder_FileLists master_ul_document_vault">';

            $html .= '<li>';
                foreach ($data as $key => $value) {
                    $html .= '<label class="kt-checkbox check_all_main_lbl">';
                    $html .= '<input class="oepl_checkbox oepl_main_dir" id="checked_all" type="checkbox" name="checkall" value="Check All" data-val=""/>';
                    $html .= '<span class="k-checkbox-label checkbox-span"></span>';
                    $html .= '</label>';
                    $html .= '<span class="oepl_main_folder_check check_all_main_span">';
                    $html .= '<label>Check All</label>';
                    $html .= '</span>';

                    $html .= '<ul class="oepl_Folder_FileLists oepl_sub_folder main_folder">';
                    $html .= self::recursiveLoop($value ,$html);
                    $html .= '</ul>';
                }
            $html .= '</li>';
        $html .= '</ul>';

        return $html;
        
    }

    /**
     * @param $datas
     * @param string $html
     * @param string $mainFolder
     * @return string
     */
    public function recursiveLoop($datas, $html = '', $mainFolder = 'yes'){
        if(!empty($datas)){
            $html = '';
            foreach ($datas as $k => $v) {
                if(!empty($v)){
                    $folderCLass = ( $mainFolder == 'yes' ) ? 'folder_li' : 'folder_sub_main';
                    $html .= '<li class="'.$folderCLass.'">';
                        $html .= '<label class="kt-checkbox">';
                        $html .= '<input class="oepl_checkbox oepl_main_dir" id="oepl_select_all" type="checkbox" data-directoryfileid="" name="foldername['.$k.'][]" value="'.$k.'" data-val=""/>';
                        $html .= '<span class="k-checkbox-label checkbox-span"></span>';
                        $html .= '</label>';

                        $html .= '<span class="oepl_main_folder oepl_down_arrow">';
                            $html .= '<label>'.$k.'</label>';
                        $html .= '</span>';

                        $html .= '<span class="published_date_document_vault folder_last_modify_date">';
                        $html .= "";
                        $html .= '</span>';

                        $html .= '<ul class="oepl_Folder_FileLists oepl_sub_folder oepl_folder_closed">';
                            $html .= self::recursiveLoop($v ,$html, 'no');
                        $html .= '</ul>';

                    $html .= '</li>';
                }else{
                    $icon = "";
                    $datumDirectoryFile = self::getFileId($k, $this->propertySlug);
                    if( !empty($datumDirectoryFile)) {
                        //$mimeType = self::getMIMEType($k);
                        $mimeType = $datumDirectoryFile['mimetype'];
                        $ModifiedDate = $datumDirectoryFile['ModifiedDate'];
                        $FileSize = $datumDirectoryFile['FileSize'];

                        if ($mimeType != "" && $mimeType != null ) {
                            $icon = self::imageIcon($mimeType);
                        } else {
                            $icon = self::imageIcon('other');
                        }
                        $img = '<img src="'.$icon.'" width="20"/>';
                        $html .= '<li>';
                        $checkbox_html = '<label class="kt-checkbox">';
                        
                        $checkbox_html .= '<input class="oepl_checkbox selectedfilename" type="checkbox" name="filename['.$datumDirectoryFile['fileid'].'][]" data-directoryfileid="" value="'.$k.'" data-val="" data-filesize="'.$FileSize.'"/><span class="k-checkbox-label checkbox-span">';
                        $checkbox_html .= '</span></label>';
                        $html .= $checkbox_html;
                        $html .= '<span class="">';
                        $fileName = substr($k, strrpos($k, '/') + 1);
                        $html .= '<label class="datum_document_filename"><span style="vertical-align: middle;padding: 5px;">'.$img.'</span>'.$fileName.'</label>';
                        $html .= '</span>';
                        $html .= '<span class="published_date_document_vault file_last_modify_date" data-date="'.$ModifiedDate.'">';
                            $html .= $ModifiedDate;
                        $html .= '</span>';
                        $html .= '</li>';
                    }
                }
            }
        }
        return $html;
    }

    /**
     * @param string $fileUrl
     * @param $propertySlug
     * @return array
     */
    public function getFileId($fileUrl = "", $propertySlug) {
        if($fileUrl != "" ) {
            $dt = explode('/', $fileUrl);
            $lastDirName = $dt[sizeof($dt)-2];
            $fileName = $dt[sizeof($dt)-1];
            $fileQuery = DirectoryFile::query();
            $fileQuery->join('DatumDirectory', 'DatumDirectory.Id', '=', 'DatumDirectoryFile.DirectoryId');
            $fileQuery->join('Property', 'Property.Id', '=', 'DatumDirectory.PropertyId');
            $fileQuery->where('DatumDirectoryFile.FileName', $fileName);
            $fileQuery->where('Property.URLSlug', $propertySlug);
            $fileQuery->where('DatumDirectory.DirectoryName', $lastDirName);
            $fileQuery->where('DatumDirectoryFile.IsDeleted', 0);
            $fileQuery->where('DatumDirectoryFile.FileType', $this->documentType);
            $fileQuery->select("DatumDirectoryFile.*");
            $directoryFiles = $fileQuery->first();
            
            /*$sql = "SELECT DDF.*
                FROM DatumDirectoryFile DDF 
                INNER JOIN DatumDirectory DD ON DDF.DirectoryId = DD.Id
                INNER JOIN Property P ON DD.PropertyId = P.Id
                WHERE DDF.FileName = '{$fileName}' 
                AND P.URLSlug = '{$propertySlug}'
                AND DD.DirectoryName = '$lastDirName'
                AND DDF.IsDeleted = 0 AND DDF.FileType='{$this->documentType}'";
            $directoryFiles = DB::select($sql);*/
            
            if( !empty($directoryFiles) && sizeof($directoryFiles) == 1 ) {
                $publishe_date = date('F d, Y');
                if($directoryFiles->ModifiedDate != null && $directoryFiles->ModifiedDate != "") {
                    $publishe_date = date('F d, Y', strtotime($directoryFiles->ModifiedDate));
                } else {
                    $publishe_date = date('F d, Y', strtotime($directoryFiles->CreatedDate));
                }

                if( isset($directoryFiles) && !empty($directoryFiles)) {
                    return array(
                        'fileid' => $directoryFiles->Id,
                        'mimetype' => $directoryFiles->MimeType,
                        'ModifiedDate' => $publishe_date,
                        'FileSize' => $directoryFiles->FileSize
                    );
                } else {
                    return [];
                }
            } else {
                return [];
            }
        }
    }

    /**
     * @param string $fileUrl
     * @return string
     */
    public function getMIMEType($fileUrl = '') {
        if($fileUrl != "") {
            $propertyDocBlobName = env('DOCUMENT_VAULT_NAME', '');
            $blobUrl = env('DOCUMENT_VAULT_URL', '').'/';
            $directory = self::fileUrl($blobUrl.$propertyDocBlobName.'/'.$this->propertySlug.'/'.$fileUrl);
            $info     = get_headers($directory, 1);
            return isset($info['Content-Type']) ? $info['Content-Type'] : 'other';
        }
    }

    /**
     * @param string $documentType
     * @return string
     */
    public function docuementNotFoundHtml($documentType = "") {
        $html = '';
        $html = '<div class="document_heading_section">';
            $html .= '<div class="title_name">Name</div>';
            $html .= '<div class="title_added_date">Date Added</div>';
        $html .= '</div>';
        $html .= '<ul class="oepl_Folder_FileLists_not_found">';
            $html .= '<li>';
                $html .= '<div class="not_found_message">No documents are currently available.</div>';
            $html .= '</li>';
        $html .= '</ul>';
        return $html;
    }

    /**
     * @param string $mimeType
     * @return string
     */
    public function imageIcon ( $mimeType = "") {
        $url = env('FILE_TYPE_ICO_URL', '');
        $iconeUrl = "";
        switch ($mimeType) {
            case 'directory':
                $iconeUrl = $url.'folder.svg';
                break;
            case 'text/css':
                $iconeUrl = $url.'css.svg';
                break;
            case 'text/plain':
                $iconeUrl = $url.'txt.svg';
                break;
            case 'text/csv':
                $iconeUrl = $url.'csv.svg';
                break;
            case 'text/javascript':
                $iconeUrl = $url.'javascript.svg';
                break;
            case 'image/png':
                $iconeUrl = $url.'png.svg';
                break;
            case 'image/svg+xml':
                $iconeUrl = $url.'svg.svg';
                break;
            case 'image/jpeg':
                $iconeUrl = $url.'jpg.svg';
                break;
            case 'application/pdf':
                $iconeUrl = $url.'pdf.svg';
                break;
            case 'application/zip':
                $iconeUrl = $url.'zip.svg';
                break;
            case 'application/json':
                $iconeUrl = $url.'json-file.svg';
                break;
            case 'video/x-msvideo':
                $iconeUrl = $url.'avi.svg';
                break;
            case 'other':
                $iconeUrl = $url.'file.svg';
                break;
            case 'application/msword':
                $iconeUrl = $url.'doc.svg';
                break;
            case 'application/vnd.ms-excel':
                $iconeUrl = $url.'xls.svg';
                break;
            case 'image/vnd.dwg':
                $iconeUrl = $url.'dwg.svg';
                break;
            case 'audio/mpeg':
                $iconeUrl = $url.'mp3.svg';
                break;
            case 'application/vnd.ms-powerpoint':
                $iconeUrl = $url.'ppt.svg';
                break;
            case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                $iconeUrl = $url.'ppt.svg';
                break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                $iconeUrl = $url.'doc.svg';
                break;
            default:
                $iconeUrl = $url.'file.svg';
        }
        return $iconeUrl;
    }

    /**
     * @param string $url
     * @return string
     */
    public function fileUrl ($url = "") {
        $parts = parse_url($url);
        $path_parts = array_map('rawurldecode', explode('/', $parts['path']));

        return $parts['scheme'] . '://' .$parts['host'] .implode('/', array_map('rawurlencode', $path_parts));
    }

}
