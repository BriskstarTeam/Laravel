<?php

namespace App\Http\Controllers;
include_once base_path('MPDF_Lib/mpdf.php');
use App\Congigurations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Database\DatabaseConnection;
use App\Property\Property;
use App\User;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use App\Traits\EncryptionDecryption;
use Illuminate\Support\Facades\Storage;
use URL;
use mPDF;
use App\Agreement\Agreement;
use App\Property\NdaTracker;
use App\PropetyConfigurationMapping;

class BlobStorageController extends Controller {
    use Common;

    public function index( Request $request ){
    	$NdaTracker = NdaTracker::whereNotNull('DocId')->orderBy('Id', 'DESC')->paginate(100);
    	
    	$caSignURL = env('CA_SIGN_DOCUMENT_URL', '');
    	$blobNotFoundedCA = [];
    	if ( !empty($NdaTracker)) {
    		foreach ($NdaTracker as $key => $value ) {
    			$contents = Storage::disk('azure')->exists('PropertyCASignedDocuments/'.$value->DocId);

    			if (!$contents) {
    				$blobNotFoundedCA[] = array(
    					'PropertyId' => $value->PropertyId,
    					'DocId' => $value->DocId,
    					'CreatedDateTime' => $value->CreatedDateTime,
    					'UserId' => $value->UserId,
    				);
    			}
    		}
    	}
    	dd($blobNotFoundedCA);
    }

    public function createAgrement( Request $request ){
        $guids = self::guIDS();
        $caSignURL = env('CA_SIGN_DOCUMENT_URL', '');
        $blobNotFoundedCA = [];
        if ( !empty($guids)) {
            foreach ($guids as $key => $value ) {
                $fileURL = self::createBlobStorageCA($value);
                if ($fileURL != "") {
                    $blobNotFoundedCA[] = $fileURL;
                }
            }
        }
        dd($blobNotFoundedCA);
    }

    public function createBlobStorageCA ($docId) {
    	$NdaTracker = NdaTracker::where('DocId', '=', $docId)->first();
    	if (!empty($NdaTracker)) {
    		$userId = $NdaTracker->UserId;
    		$user = User::where('Users.Id', $userId)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId','UserContactMapping.IndustryRoleId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();
    		$propertyId = $NdaTracker->PropertyId;
    		$property = Property::query();
	        $property->where('Property.Id', $propertyId);
	        $property->where('Property.Id', $propertyId);
	        $property->leftJoin('PropertyCAContent', 'PropertyCAContent.PropertyId', '=', 'Property.Id');
	        $property->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
	        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');

	        $property->select(['Property.Id', 'Property.URLSlug', 'Property.Name', 'Property.PropertyStatusId',"PropertyCAContent.CAPdfDocument", 'PropertyAddress.Address1', 'PropertyAddress.Address2', 'PropertyAddress.City', 'PropertyAddress.State', 'PropertyAddress.Country', 'PropertyAddress.ZipCode']);

	        $property = $property->first();

	        $userIp = $NdaTracker->IPAddress;
	        if(!empty($property)){
	        	if ( $property->CAPdfDocument != "" && $property->CAPdfDocument != null) {
	        		$capath = env('CONFIDENTIAL_AGREMENT_URL', '');
	        		$main_path = public_path('temp_ca');

	        		if (!file_exists($main_path.'/'.$userId)) {
			             mkdir($main_path.'/'.$userId, 0777, true);
			        }
                    
			        $finalUrl = $main_path.'/'.$userId;
			        file_put_contents( $finalUrl.'/'.$property->CAPdfDocument,file_get_contents($capath.'/'.$property->URLSlug.'/'.$property->CAPdfDocument));

			        $HeaderHTML = '';
	                $printable = '';
	                $footerHTML = '';
	                $head = '';

	                $HeaderHTML = self::getHeaderHtml();
	                $printable = self::createDynamicPageHtml($property, $user, $userIp, $NdaTracker);

	                ## stylesheet
	                $stylesheet = self::getCss();
	                
	                #============== START Footer HTML
	                $footerHTML .= '<hr style="height: 1px; color: #000; margin: 0px; padding: 0px;" />';
	                $footerHTML .= '<div style="text-align:center;">{PAGENO}</div>';
	                #============== END Footer HTML
	                
	                $margin_left = 10;
	                $margin_right = 10;
	                $margin_top = 20;
	                $margin_bottom = 20;
	                $margin_header = 5;
	                $margin_footer = 10;

	                $pdf = new mPDF('en', 'A4', '', 'proximanovaalt', $margin_left, $margin_right, $margin_top, $margin_bottom, $margin_header, $margin_footer);

	                $pdf->fontdata['proximanovaalt'] = array('R' => "ProximaNovaAlt.ttf", 'B' => "ProximaNovaAlt-Bold.ttf", );

	                $pdf->SetImportUse();
	                $pagecount = $pdf->SetSourceFile($finalUrl.'/'.$property->CAPdfDocument);
	                
	                for ($i = 1; $i <= $pagecount; $i++) {
	                    $tplId = $pdf->ImportPage($i);
	                    $pdf->UseTemplate($tplId);
	                    if ($i != $pagecount) {
	                        $pdf->WriteHTML('<pagebreak />');
	                    }
	                }
	                ## new page add
	                $pdf->SetHTMLHeader($HeaderHTML);
	                $pdf->AddPage();
	                $pdf->SetHTMLFooter($footerHTML);

	                $pdf->WriteHTML($stylesheet, 1);
	                $pdf->WriteHTML($printable);
	                $pdf->SetProtection(array('copy','print'), '');

	                $guidDirectory = $finalUrl.'/'.$docId;
	                $caSignLocalPath = $finalUrl.'/';

	                $pdf->Output($guidDirectory, 'F');
	                if (!file_exists($finalUrl.'/'.$userId)) {
			             mkdir($finalUrl.'/'.$userId.'/'.$docId, 0777, true);
			        }
	                $pdfURL = URL('public/temp_ca/'.$userId.'/'.$docId);
	                return $pdfURL;
	        	} else {
	        		return "";
	        	}
	        } else {
	        	return "";
	        }
    	}
    }

    public function createDynamicPageHtml( $propertyData = array(), $userData = array(), $userIP = "", $NdaTracker = array()) {
        $propertyName = $propertyData->Name;
        $property_Address = $propertyData->Address1;
        if (!empty($propertyData->Address2) || !is_null($propertyData->Address2)) {
            $property_Address .= ', '.$propertyData->Address2;
        }
        $property_Address .= ', '.$propertyData->City;
        $property_Address .= ', '.$propertyData->State.' '.$propertyData->ZipCode;
        
        $username = ucwords(strtolower($userData->FirstName.' '.$userData->LastName));
        $email = $userData->Email;

        $CompanyName = "";
        if($userData->CompanyId == null || $userData->CompanyId == "") {
            $CompanyName = "Individual";
        } else {
            $CompanyName = $userData->CompanyName;
        }

        $userAddress = $userData->Street.' '.$userData->Suite;
        $userCity = $userData->City;
        $userState = $userData->State;
        $userZipCode = $userData->ZipCode;
        $userWorkPhone = $userData->WorkPhone;

		$ipInfo = file_get_contents('http://ip-api.com/json/' . $userIP);
		$ipInfo = json_decode($ipInfo);

        if(isset($ipInfo->timezone) && $ipInfo->timezone != "") {
            date_default_timezone_set($ipInfo->timezone);
        }
        
        $current_time = date('h:i A', strtotime($NdaTracker->CreatedDateTime));
        $current_date = date('F d, Y', strtotime($NdaTracker->CreatedDateTime));

        $printable = '
        <div>
            <p align="center" style="text-align:center;margin-bottom:30px;font-size:18px;"><strong>ELECTRONIC ACCEPTANCE ADDENDUM</strong></p>
            <table class="OEPL_table" style="width: 100%;">
                <tr>
                    <td colspan="2"><strong>LISTING NAME: </strong>'.ucwords(strtolower($propertyName)).'</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>LISTING ADDRESS: </strong>'.$property_Address.'</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <td><strong>Name: </strong>'.$username.'</td>
                    <td><strong>Company: </strong>'.$CompanyName.'</td>
                </tr>
                <tr>
                    <td><strong>Address: </strong>'.$userAddress.'</td>
                    <td><strong>City: </strong>'.$userCity.'</td>
                </tr>
                <tr>
                    <td><strong>State: </strong>'.$userState.'</td>
                    <td><strong>Zip Code: </strong>'.$userZipCode.'</td>
                </tr>
                <tr>
                    <td><strong>Work Phone: </strong>'.$userWorkPhone.'</td>
                    <td><strong>Email Address: </strong>'.$email.'</td>
                </tr>
                <tr>
                    <td style="vertical-align:middle;padding-bottom: 8px !important;"><strong>Signature: </strong><span style="font-size:28px;font-family:vladimir;">'.$username.'</span></td>
                    <td><strong>IP Address: </strong>'.$userIP.'</td>
                </tr>
                <tr>
                    <td><strong>Date: </strong>'.$current_date.'</td>
                    <td><strong>Time: </strong>'.$current_time.'</td>
                </tr>
            </table>
        </div';
        return $printable;
    }

    /**
     * @return string
     */
    public function getCss () {
        $stylesheet = '
        table{ width: 100%; border: 0.5px solid #ddd; font-size:12px;}
        tr th {background: #eee;text-align:left !important;}
        tr td, tr th { padding: 5px;height: 30px;}
        p {text-indent: 50px;text-align:justify;}
        .OEPL_table {border:none !important;font-size:14px;width:100%;}
        .OEPL_table tr td {padding:0 !important;}';
        return $stylesheet;
    }

    /**
     * @return string
     */
    public function getHeaderHtml () {
        $logo = env('LOGO_BLACK', '');
        $HeaderHTML = '
        <table style="width: 100%; font-family: Arial;border:none;" cellspacing="2" cellpadding="2">
            <tr>
                <td><img src="https://datumdoc.blob.core.windows.net/datumfilecontainer/placeholders/logo_black.jpg" width="120"></td>
            </tr>
        </table>
        <hr style="height: 1px;margin: 0px; padding: 0px;color: #000;" />';
        return $HeaderHTML;
    }

    public function uploadCAOnBlobStorage ( $absolutePath = "", $fileName = "") {
        $caSignFolder = env('CA_SIGN_DOCUMENT_NAME', '');
        $fileUrl = $caSignFolder;
        dd($absolutePath.'/'.$fileName);
        $contents = Storage::disk('azure')->putFileAs($fileUrl, $absolutePath.'/'.$fileName, $fileName);
        dd($contents);
        return $contents;
    }

    public function guIDS () {
    	$docIds = array(
            "6dfc58fd-edd5-4bbb-ae3c-22d5d55f"
		);
		return array_unique($docIds);
    }

    /**
     * @param string $json
     */
    public function createLog ($json = '') {
        $log  = "INFO: ".date("F j, Y, g:i a")." ".$json.' - downloaded '.PHP_EOL;
        $logFolder = public_path('user_agrement_sign');
        if ( !is_dir( $logFolder ) ) {
            mkdir($logFolder, 0777, true) || chmod($logFolder, 0777);
        }
        file_put_contents($logFolder.'/log_'.date("j.n.Y").'.log', $log, FILE_APPEND);
    }
}