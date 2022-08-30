<?php
namespace App\Agreement;
//include_once base_path('lovepdf/vendor/autoload.php');
include_once base_path('MPDF_Lib/mpdf.php');

use App\Companies;
use App\User;
use App\WpOsdUserPropertiesRelationship;
use App\Property\NdaTracker;
Use App\OeplPropertyTracker;
use App\Directory;
use App\DirectoryFile;
use App\Property\DocumentVaultOMAccessHistory;
use App\Property\DocumentVaultomAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Property\Property;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use App\Mail\Email;
use Illuminate\Support\Facades\Storage;
use URL;
//use Ilovepdf\Ilovepdf;
use mPDF;
use App\Database\DatabaseConnection;
use Illuminate\Http\File;
use App\Property\SQLDocumentVaultOMAccessHistory;
use App\Property\SQLDocumentVaultomAccess;
use App\Documentvaultomddhistory;
use App\PropetyConfigurationMapping;
use App\ExistingContactConfigurationMapping;
const UNDERCONTRACT = 4;

/**
 * Class Agreement
 * @package App\Agreement
 */
class Agreement {
    use Common;

    /**
     * @param string $propertySlug
     * @param string $userId
     * @return string
     */
    public function createFolderDirecotory( $propertySlug = "", $userId = "" ) {
        $main_path = public_path('confidential_agrement');

        $siteUrl = $this->removeHttp(getallheaders()['site_url']);

        if (!file_exists($main_path)) {
             mkdir($main_path, 0777, true);
        }

        if (!file_exists($main_path.'/'.$siteUrl)) {
             mkdir($main_path.'/'.$siteUrl, 0777, true);
        }

        if (!file_exists($main_path.'/'.$siteUrl.'/'.$propertySlug)) {
             mkdir($main_path.'/'.$siteUrl.'/'.$propertySlug, 0777, true);
        }

        if (!file_exists($main_path.'/'.$siteUrl.'/'.$propertySlug.'/'.$userId)) {
             mkdir($main_path.'/'.$siteUrl.'/'.$propertySlug.'/'.$userId, 0777, true);
        }

        $directory = $main_path.'/'.$siteUrl.'/'.$propertySlug.'/'.$userId;

        return $directory;
    }

    /**
     * @param string $propertySlug
     * @param string $userId
     * @return string
     */
    public function getCADirectoryPath ( $propertySlug = "", $userId = "" ) {
        $siteUrl = $this->removeHttp(getallheaders()['site_url']);
        return 'confidential_agrement/'.$siteUrl.'/'.$propertySlug.'/'.$userId;
    }

    /**
     * @param string $userId
     * @param string $propertyId
     * @param string $userIp
     * @return array
     */
    public function confidencialAgreementPreview( $userId = '', $propertyId = '', $userIp = ''){
        error_reporting(0);
        $userIp = $userIp;
        $databaseConnection = new DatabaseConnection();
        
        $user = User::where('Users.Id', $userId)->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->first();
        $property = Property::query();
        if(is_numeric($propertyId)) {
            $property->where('Property.Id', $propertyId);
        } else {
            $property->where('Property.URLSlug', $propertyId);
        }
        $property->leftJoin('PropertyCAContent', 'PropertyCAContent.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');

        $property->select(['Property.Id', 'Property.URLSlug', 'Property.Name',"PropertyCAContent.CAPdfDocument", 'PropertyAddress.Address1', 'PropertyAddress.Address2', 'PropertyAddress.City', 'PropertyAddress.State', 'PropertyAddress.Country', 'PropertyAddress.ZipCode']);

        $property = $property->first();
        $finalUrl = self::createFolderDirecotory($property->URLSlug, $userId);

        if(!empty($property) && $property != null){

            if ( $property->CAPdfDocument != "" ) {
                $capath = env('CONFIDENTIAL_AGREMENT_URL', '');
                $dt = get_headers(self::fileUrl($capath.'/'.$property->URLSlug.'/'.$property->CAPdfDocument), true);
                if (strpos($dt[0], '404') !== false) {
                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'ca_notfound' => true,
                        'URL' => URL::to('/ca-404'),
                    ];
                }
                file_put_contents( $finalUrl.'/'.$property->CAPdfDocument,file_get_contents($capath.'/'.$property->URLSlug.'/'.$property->CAPdfDocument));

                $HeaderHTML = '';

                $printable = '';
                $footerHTML = '';
                $head = '';

                $HeaderHTML = self::getHeaderHtml();
                
                $printable = self::createDynamicPageHtml($property, $user, $userIp);

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
                if(!file_exists( $finalUrl.'/'.$property->CAPdfDocument ) ) {
                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'ca_notfound' => true,
                        'URL' => URL::to('/ca-404'),
                        'type' => '404',
                    ];
                }
                $pagecount = $pdf->SetSourceFile($finalUrl.'/'.$property->CAPdfDocument);
                if(!$pagecount) {

                    ini_set('display_errors', 1);
                    $pdf1 = $property->CAPdfDocument;
                    $quality = 90;
                    $res='300x300';
                    $exportPath = base_path().'/Ilovepdf_files/'.$property->CAPdfDocument;

                    set_time_limit(900);
                    $repairePDFPath = $finalUrl.'/'.$property->CAPdfDocument;
                    //exec("'gs' '-dNOPAUSE' '-sDEVICE=pdfwrite' '-o$exportPath' '-r$res' '$pdf1'",$output);
                    exec("mutool clean {$repairePDFPath} {$exportPath}");
                    if(file_exists( $exportPath )) {
                        chmod($finalUrl.'/'.$property->CAPdfDocument, 0777);

                        unlink($finalUrl.'/'.$property->CAPdfDocument);
                        chmod($exportPath, 0777);

                        $caFLNM = env('CONFIDENTIAL_AGREMENT_C_NAME', '').'/'.$property->URLSlug;

                        $contents = Storage::disk('azure')->putFileAs($caFLNM, $exportPath, $property->CAPdfDocument);
                        
                        copy ($exportPath, $finalUrl.'/'.$property->CAPdfDocument);

                        $pagecount = $pdf->SetSourceFile($exportPath);
                        if(!$pagecount){
                            return [
                                'status' => 'success',
                                'message' => [],
                                'errors' => [],
                                'data' => [],
                                'ca_notfound' => true,
                                'URL' => URL::to('/ca-404'),
                                'type' => '404',
                            ];
                        }
                    }
                }

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
                //$pdf->showImageErrors = true;
                $fileName = 'Executed CA_'. $property->Name.'.pdf';
                $fileName = str_replace(' ', '_', $fileName);
                $fileSaveDirectory = $finalUrl.'/'.$fileName;

                $content = $pdf->Output($fileSaveDirectory, 'F');
                
                $flUrl = self::getCADirectoryPath($property->URLSlug, $userId);
                
                $pdfURL = URL('public/'.$flUrl.'/'.$fileName);
                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'URL' => $pdfURL,
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'ca_notfound' => true,
                    'URL' => URL::to('/ca-404'),
                    'type' => '404',
                ];
            }
        } else {
            return [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => [],
                'ca_notfound' => true,
                'URL' => URL::to('/ca-404'),
                'type' => '404',
            ];
        }
    }

    /**
     * @param string $userId
     * @param string $propertyId
     * @param string $userIp
     * @param $copyEmail
     * @return array
     * @throws \Exception
     */
    public function confidencialAgreementDownload( $userId = '', $propertyId = '', $userIp = '', $copyEmail ){
        $user = User::where('Users.Id', $userId)->join('UserContactMapping', 'UserContactMapping.UserId', '=', 'Users.Id')->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId','UserContactMapping.IndustryRoleId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->orderBy('UserContactMapping.UserTypeId', 'DESC')->first();

        $property = Property::query();
        if(is_numeric($propertyId)) {
            $property->where('Property.Id', $propertyId);
        } else {
            $property->where('Property.URLSlug', $propertyId);
        }
        $property->leftJoin('PropertyCAContent', 'PropertyCAContent.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');

        $property->select(['Property.Id', 'Property.URLSlug', 'Property.Name', 'Property.PropertyStatusId',"PropertyCAContent.CAPdfDocument", 'PropertyAddress.Address1', 'PropertyAddress.Address2', 'PropertyAddress.City', 'PropertyAddress.State', 'PropertyAddress.Country', 'PropertyAddress.ZipCode']);

        $property = $property->first();
        
        if(!empty($property)){
            if ( $property->CAPdfDocument != "" ) {
                $capath = env('CONFIDENTIAL_AGREMENT_URL', '');                
                $finalUrl = self::createFolderDirecotory($property->URLSlug, $userId);

                $dt = get_headers(self::fileUrl($capath.'/'.$property->URLSlug.'/'.$property->CAPdfDocument), true);
                if (strpos($dt[0], '404') !== false) {
                    return [
                        'status' => 'success',
                        'message' => [],
                        'errors' => [],
                        'data' => [],
                        'ca_notfound' => true,
                        'URL' => URL::to('/ca-404'),
                    ];
                }

                file_put_contents( $finalUrl.'/'.$property->CAPdfDocument,file_get_contents($capath.'/'.$property->URLSlug.'/'.$property->CAPdfDocument));
                $HeaderHTML = '';

                $printable = '';
                $footerHTML = '';
                $head = '';

                $HeaderHTML = self::getHeaderHtml();
                $printable = self::createDynamicPageHtml($property, $user, $userIp);

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

                if(!$pagecount) {

                    ini_set('display_errors', 1);
                    $pdf1 = $property->CAPdfDocument;
                    $quality = 90;
                    $res='300x300';
                    $exportPath = base_path().'/Ilovepdf_files/'.$property->CAPdfDocument;

                    set_time_limit(900);
                    $repairePDFPath = $finalUrl.'/'.$property->CAPdfDocument;
                    //exec("'gs' '-dNOPAUSE' '-sDEVICE=pdfwrite' '-o$exportPath' '-r$res' '$pdf1'",$output);
                    exec("mutool clean {$repairePDFPath} {$exportPath}");
                    if(file_exists( $exportPath )) {
                        chmod($finalUrl.'/'.$property->CAPdfDocument, 0777);

                        unlink($finalUrl.'/'.$property->CAPdfDocument);
                        chmod($exportPath, 0777);

                        $caFLNM = env('CONFIDENTIAL_AGREMENT_C_NAME', '').'/'.$property->URLSlug;

                        $contents = Storage::disk('azure')->putFileAs($caFLNM, $exportPath, $property->CAPdfDocument);
                        
                        copy ($exportPath, $finalUrl.'/'.$property->CAPdfDocument);

                        $pagecount = $pdf->SetSourceFile($exportPath);

                        if(!$pagecount){
                            return [
                                'status' => 'success',
                                'message' => [],
                                'errors' => [],
                                'data' => [],
                                'ca_notfound' => true,
                                'URL' => URL::to('/ca-404'),
                                'type' => '404',
                            ];
                        }
                    }
                }

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

                $fileName = 'Executed_CA_'.$property->Name.'.pdf';
                $fileNameForDB = $fileName;
                $fileName = str_replace(' ', '_', $fileName);
                $fileSaveDirectory = $finalUrl.'/'.$fileName;

                $data1 = $data1 ?? random_bytes(16);
                assert(strlen($data1) == 16);
                $data1[6] = chr(ord($data1[6]) & 0x0f | 0x40);
                $data1[8] = chr(ord($data1[8]) & 0x3f | 0x80);
                $guid = vsprintf('%s%s-%s-%s-%s-%s%s', str_split(bin2hex($data1), 4));

                $guidDirectory = $finalUrl.'/'.$guid;
                $caSignLocalPath = $finalUrl.'/';

                $pdf->Output($guidDirectory, 'F');
                $pdf->Output($fileSaveDirectory, 'F');

                $flUrl = self::getCADirectoryPath($property->URLSlug, $userId);

                $pdfURL = URL('public/'.$flUrl.'/'.$fileName);

                if( $copyEmail =='yes') {

                    $subject = $property->Name." Confidentiality Agreement";
                    $content = "";
                    $content .= "<p>Thank you for executing the confidentiality agreement for ".$property->Name.". Please find the attached executed document.</p>";
                    $content .= '<p>Thank you,<br></p>';
                    $attachment = array(
                        'path' => $guidDirectory,
                        'filename' => $fileName,
                    );
                    $username = ucwords(strtolower($user->FirstName.' '.$user->LastName));
                    $to = array(
                        array(
                            'email' => $user->Email,
                            'name' => ''
                        ),
                    );
                    $email = new Email();
                    $message = $email->email_content($username, $content, true);
                    $email->sendEmail( $subject, $to, $message, array(), $attachment);
                    $copyEmail = "Yes";
                } else {
                    $copyEmail = 'No';
                }
                self::uploadCAOnBlobStorage($caSignLocalPath, $guid, $property);
                self::confidentialAgreementSign($property, $user, $userIp, $guid, $fileNameForDB, $copyEmail);
                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'URL' => $pdfURL,
                    'FileName' => $fileName
                ];
            } else {
                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'ca_notfound' => true,
                    'URL' => URL::to('/ca-404'),
                    'type' => '404',
                    'FileName' => ''
                ];
            }
        } else {
            return [
                'status' => 'success',
                'message' => [],
                'errors' => [],
                'data' => [],
                'ca_notfound' => true,
                'URL' => URL::to('/ca-404'),
                'FileName' => '',
                'type' => '404',
            ];
        }
    }

    /**
     * @param array $propertyData
     * @param array $userData
     * @param string $userIP
     * @return string
     */
    public function createDynamicPageHtml( $propertyData = array(), $userData = array(), $userIP = "") {

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

        //$current_date = date('m/d/Y');
        $current_date = getallheaders()['current_date'];
        //$current_time = date('h:i A').' PST';
        $current_time = getallheaders()['current_time'];

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

    /**
     * @param array $property
     * @param array $user
     * @param string $userIp
     * @param string $guid
     * @param $fileName
     * @param $copyEmail
     */
    public function confidentialAgreementSign ( $property = array(), $user = array(), $userIp = '', $guid = '', $fileName, $copyEmail) {
        
        $databaseConnection = new DatabaseConnection();
        $configurationData  = $databaseConnection->getConfiguration();
        $hostedConfigData   = $databaseConnection->getPropertyConfiguration($property->Id);
        
        $ndaTracker = new NdaTracker();
        $ndaTracker->UserId = $user->Id;
        $ndaTracker->PropertyId = $property->Id;
        $ndaTracker->IPAddress = $userIp;
        $ndaTracker->CreatedDateTime = date('Y-m-d H:i:s');
        $ndaTracker->EmailTo = $user->Email;
        $ndaTracker->PDFFile = $fileName;
        $ndaTracker->NDASigned = 1;
        $ndaTracker->DocId = $guid;
        $ndaTracker->ConfigurationId = $configurationData->ConfigurationId;
        $ndaTracker->HostedConfigurationId = $hostedConfigData->ConfigurationId;
        $ndaTracker->save();

        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('PropertyId', $property->Id)->where('UserId', $user->Id)->first();

        if(!empty($propertyUserRelationship) && $propertyUserRelationship != null ) {
            $documentAccessRole = 'Public';

            if($property->PropertyStatusId != UNDERCONTRACT) {
                $directoryomaccess = self::checkUserOmAccessForProperty($property, $user);
                if($directoryomaccess) {
                    $documentAccessRole = 'om';
                }
            }

            if($propertyUserRelationship->DuediligenceRequestStatus == 3) {
                $documentAccessRole = 'Due Diligence';
            }

            $propertyUserRelationship->DocumentRole = $documentAccessRole;
            if( $copyEmail =='Yes') {
                $propertyUserRelationship->NDASentEmail = $user->Email;
            } else {
                $propertyUserRelationship->NDASentEmail = "";
            }

            $propertyUserRelationship->NDASignedDateTime = date('Y-m-d H:i:s');
            $propertyUserRelationship->NDASigned = 1;
            $propertyUserRelationship->NDAPDF = $fileName;
            $propertyUserRelationship->NDAIP = $userIp;
            $propertyUserRelationship->UserId = $user->Id;
            $propertyUserRelationship->DocId = $guid;
            $propertyUserRelationship->ConfigurationId = $configurationData->ConfigurationId;
            $propertyUserRelationship->HostedConfigurationId = $hostedConfigData->ConfigurationId;
            $propertyUserRelationship->save();

            if( $configurationData->ConfigurationId != $hostedConfigData->ConfigurationId ) {
                
                self::savePropertyConfigurationMapping($property->Id, $user->Id, $configurationData->ConfigurationId, $hostedConfigData->ConfigurationId, $documentAccessRole);

                self::SaveExistingContactConfigurationMapping($user->Id, $hostedConfigData->ConfigurationId);
            }
        } else {
            $documentAccessRole = 'Public';
            $wpOsdUserPropertiesRelationship = new WpOsdUserPropertiesRelationship();
            $wpOsdUserPropertiesRelationship->UserId = $user->Id;
            $wpOsdUserPropertiesRelationship->PropertyId = $property->Id;
            $wpOsdUserPropertiesRelationship->NDASignedDateTime = date('Y-m-d H:i:s');
            
            if( $copyEmail == 'Yes') {
                $wpOsdUserPropertiesRelationship->NDASentEmail = $user->Email;
            } else {
                $wpOsdUserPropertiesRelationship->NDASentEmail = "";
            }

            if($property->PropertyStatusId != UNDERCONTRACT) {
                $directoryomaccess = self::checkUserOmAccessForProperty($property, $user);
                if($directoryomaccess) {
                    $documentAccessRole = 'om';
                }
            }
            $wpOsdUserPropertiesRelationship->DocumentRole = $documentAccessRole;
            $wpOsdUserPropertiesRelationship->NDASigned = 1;
            $wpOsdUserPropertiesRelationship->NDAIP = $userIp;
            $wpOsdUserPropertiesRelationship->NDAPDF = $fileName;
            $wpOsdUserPropertiesRelationship->DocId = $guid;
            $wpOsdUserPropertiesRelationship->ConfigurationId = $configurationData->ConfigurationId;
            $wpOsdUserPropertiesRelationship->DuediligenceRequestStatus = 1;
            $wpOsdUserPropertiesRelationship->DuediligenceRequestDateTime = date('Y-m-d H:i:s');
            $wpOsdUserPropertiesRelationship->DDApproved = 0;
            $wpOsdUserPropertiesRelationship->HostedConfigurationId = $hostedConfigData->ConfigurationId;
            $wpOsdUserPropertiesRelationship->save();

            if( $configurationData->ConfigurationId != $hostedConfigData->ConfigurationId ) {
                self::savePropertyConfigurationMapping($property->Id, $user->Id, $configurationData->ConfigurationId, $hostedConfigData->ConfigurationId, $documentAccessRole);

                self::SaveExistingContactConfigurationMapping($user->Id, $hostedConfigData->ConfigurationId);
            }
        }
    }

    /**
     * @param array $property
     * @param array $user
     * @return bool
     */
    public function checkUserOmAccessForProperty ($property = array(), $user = array()) {
        $directory = Directory::query();
        $directory->join('DocumentVaultOMAccess', 'DocumentVaultOMAccess.DatumDirectoryId', '=', 'DatumDirectory.Id');
        $directory->where('DatumDirectory.PropertyId', $property->Id);
        $directory->where('DocumentVaultOMAccess.Access', 3);
        $directory->where('DatumDirectory.DirectoryName', 'Offering Memorandum');
        $directory->where('DatumDirectory.ParentId', 0);
        $directory->where(function ($q) use ($user) {
           $q->where('DocumentVaultOMAccess.UserId', $user->Id);
           if ($user->IndustryRoleId != null ) {
                $q->orWhere('DocumentVaultOMAccess.IndustryRoleId', $user->IndustryRoleId);
           }
           $q->orWhere('DocumentVaultOMAccess.UserEmail', $user->Email); 
        });
        $directoryomaccess = $directory->orderBy('DocumentVaultOMAccess.Id', 'desc')->first();
        $offeringMemorandomAccess = false;
        if (!empty($directoryomaccess) && $directoryomaccess != null) {
            $offeringMemorandomAccess = true;
        } else {
            $offeringMemorandomAccess = false;
        }
        return $offeringMemorandomAccess;
    }

    /**
     * @param string $absolutePath
     * @param string $fileName
     * @param array $property
     * @return mixed
     */
    public function uploadCAOnBlobStorage ( $absolutePath = "", $fileName = "", $property = array()) {
        $caSignFolder = env('CA_SIGN_DOCUMENT_NAME', '');
        $fileUrl = $caSignFolder;
        $contents = Storage::disk('azure')->putFileAs($fileUrl, $absolutePath.'/'.$fileName, $fileName);
        self::createLog($fileName);
        return $contents;
    }

    /**
     * @param string $userId
     * @param string $propertyId
     * @param string $userIP
     * @return array
     */
    public function caDownloadDashboard( $userId = "", $propertyId = "", $userIP = "" ) {
        $databaseConnection = new DatabaseConnection();
        $configurationData = $databaseConnection->getConfiguration();

        $user = User::where('Users.Id', $userId)->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->first();

        $NdaTracker = NdaTracker::where('DocId', $propertyId)->where('UserId', $userId)->first();

        if ( !empty ($NdaTracker) && $NdaTracker != null ) {

            $property = Property::where("Id", $NdaTracker->PropertyId)->first();

            $getPropertyByGuID = self::getPropertyByDashboardGUID($NdaTracker->DocId);

            if(!empty($getPropertyByGuID) && $getPropertyByGuID != null ) {

                $property_name = '';
                $clientname = '';

                $property_name = $getPropertyByGuID->Name;
                $property_Id = $getPropertyByGuID->ID;
                $propertySlug = $getPropertyByGuID->URLSlug;
                $userID = $userId;

                $userEmail = "";
                if(isset($getPropertyByGuID->Email) && $getPropertyByGuID->Email != "") {
                    $userEmail = $getPropertyByGuID->Email;
                }
                $userEmail = $user->Email;
                $clientname = $getPropertyByGuID->contactfirstname.' '.$getPropertyByGuID->contactlastname;

                $fileName = 'Executed CA_'. $property_name.'.pdf';
                $fileName = str_replace(' ', '_', $fileName);

                $postArray = array(
                    'property_id' => $property->Id,
                    'nda_pdf' => $fileName,
                    'doc_id' => $NdaTracker->DocId,
                    'user_id' => $userID,
                    'user_email' => $userEmail,
                    'user_ip' => $userIP
                );
                $caSignURL = env('CA_SIGN_DOCUMENT_URL', '');
                
                $finalUrl = self::createFolderDirecotory($property->URLSlug, $userId);
                $fileSaveDirectory = $finalUrl.'/'.$fileName;

                file_put_contents( $fileSaveDirectory, file_get_contents($caSignURL.'/'.$NdaTracker->DocId));

                $flUrl = self::getCADirectoryPath($property->URLSlug, $userId);
                
                $pdfURL = URL('public/'.$flUrl.'/'.$fileName);

                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'URL' => $pdfURL,
                    'FileName' => $fileName,
                ];

            }
        }
    }

    /**
     * @param string $userId
     * @param string $propertyId
     * @param string $userIP
     * @return array
     */
    public function downloadedConfidencialAgreement ( $userId = "", $propertyId = "", $userIP = "" ) {
        $databaseConnection = new DatabaseConnection();
        $configurationData = $databaseConnection->getConfiguration();

        $user = User::where('Users.Id', $userId)->leftJoin('UserAddressDetails', 'UserAddressDetails.UserId', 'Users.Id')->leftJoin('Companies', 'Companies.Id', 'Users.CompanyId')->select(['Users.Id', 'Users.FirstName', 'Users.LastName', 'Users.Email', 'Users.CompanyId', 'UserAddressDetails.Street', 'UserAddressDetails.Suite', 'UserAddressDetails.City', 'UserAddressDetails.State', 'UserAddressDetails.ZipCode', 'UserAddressDetails.Address', 'UserAddressDetails.WorkPhone', 'Companies.CompanyName'])->first();
        
        $propertyUserRelationship = WpOsdUserPropertiesRelationship::where('DocId', $propertyId)->where('UserId', $userId)->first();

        if ( !empty ($propertyUserRelationship) && $propertyUserRelationship != null ) {

            $property = Property::where("Id", $propertyUserRelationship->PropertyId)->first();
            $getPropertyByGuID = self::getPropertyByGUID($propertyUserRelationship->DocId);
            
            if(!empty($getPropertyByGuID) && $getPropertyByGuID != null ) {

                $property_name = '';
                $clientname = '';

                $property_name = $getPropertyByGuID->Name;
                $property_Id = $getPropertyByGuID->ID;
                $propertySlug = $getPropertyByGuID->URLSlug;
                $userID = $userId;

                $userEmail = "";
                if(isset($getPropertyByGuID->Email) && $getPropertyByGuID->Email != "") {
                    $userEmail = $getPropertyByGuID->Email;
                }
                $userEmail = $user->Email;
                $clientname = $getPropertyByGuID->contactfirstname.' '.$getPropertyByGuID->contactlastname;

                $fileName = 'Executed CA_'. $property_name.'.pdf';
                $fileName = str_replace(' ', '_', $fileName);

                $postArray = array(
                    'property_id' => $property->Id,
                    'nda_pdf' => $fileName,
                    'doc_id' => $propertyUserRelationship->DocId,
                    'user_id' => $userID,
                    'user_email' => $userEmail,
                    'user_ip' => $userIP
                );
                $caSignURL = env('CA_SIGN_DOCUMENT_URL', '');
                
                $finalUrl = self::createFolderDirecotory($property->URLSlug, $userId);
                $fileSaveDirectory = $finalUrl.'/'.$fileName;
                file_put_contents( $fileSaveDirectory, file_get_contents($caSignURL.'/'.$propertyUserRelationship->DocId));

                $flUrl = self::getCADirectoryPath($property->URLSlug, $userId);
                
                $pdfURL = URL('public/'.$flUrl.'/'.$fileName);
                $ndaTracker = new NdaTracker();
                $ndaTracker->UserId = $userID;
                $ndaTracker->PropertyId = $property->Id;
                $ndaTracker->IPAddress = $userIP;
                $ndaTracker->CreatedDateTime = date('Y-m-d H:i:s');
                $ndaTracker->EmailTo = $userEmail;
                $ndaTracker->PDFFile = $fileName;
                $ndaTracker->NDASigned = 1;
                $ndaTracker->DocId = $propertyUserRelationship->DocId;
                $ndaTracker->ConfigurationId = $configurationData->ConfigurationId;
                $ndaTracker->save();
                
                return [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => [],
                    'URL' => $pdfURL,
                    'FileName' => $fileName,
                ];

            }
        }

    }

    /**
     * @param string $docID
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     */
    public function getPropertyByDashboardGUID ( $docID = "" ) {
        $property = NdaTracker::query();
        $property->leftJoin('Property', 'property.Id', '=', 'NDATracker.PropertyId');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'Property.Id');
        $property->leftJoin('Users', 'Users.Id', '=', 'PropertyContactMapping.UserId');
        $property->select(["Property.Name", "Property.URLSlug", "Users.FirstName AS contactfirstname", "Users.LastName AS contactlastname", "Users.Email", "Property.Id", "NDATracker.UserId"]);
        $property->where('NDATracker.DocId', $docID);
        $property = $property->first();
        
        return $property;
    }

    /**
     * @param string $docID
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     */
    public function getPropertyByGUID ( $docID = "" ) {
        $property = WpOsdUserPropertiesRelationship::query();
        $property->leftJoin('Property', 'Property.Id', '=', 'WPOsdUserPropertiesRelations.PropertyId');
        $property->leftJoin('PropertyContactMapping', 'PropertyContactMapping.PropertyId', '=', 'property.Id');
        $property->leftJoin('Users', 'Users.Id', '=', 'PropertyContactMapping.UserId');
        $property->select(["Property.Name", "Property.URLSlug", "Users.FirstName AS contactfirstname", "Users.LastName AS contactlastname", "Users.Email", "Property.Id", "WPOsdUserPropertiesRelations.UserId AS UserId"]);
        $property->where('WPOsdUserPropertiesRelations.docId', $docID);
        $property = $property->first(); 
        
        return $property;
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

    /**
     * @param $propertyId
     * @param $userId
     * @param $configurationId
     * @param $HostedConfigurationId
     * @param string $documentRole
     */
    public function savePropertyConfigurationMapping($propertyId, $userId, $configurationId, $HostedConfigurationId, $documentRole = "") {

        $propetyConfigurationMapping = new PropetyConfigurationMapping();

        $propetyConfigurationMapping->PropertyId             = $propertyId;
        $propetyConfigurationMapping->ConfigurationId        = $configurationId;
        $propetyConfigurationMapping->HostedConfigurationId  = $HostedConfigurationId;
        $propetyConfigurationMapping->UserId                 = $userId;
        $propetyConfigurationMapping->DocumentRole           = $documentRole;
        $propetyConfigurationMapping->CreatedBy              = $userId;
        $propetyConfigurationMapping->CreatedDate            = date('Y-m-d H:i:s');
        $propetyConfigurationMapping->save();

    }

    /**
     * @param $propertyId
     * @param $userId
     * @return bool
     */
    public function removedOMAccess ($propertyId, $userId) {

        $directory = Directory::where("PropertyId", $propertyId)->where("DirectoryName", 'Offering Memorandum')->first();

        if( !empty($directory) && $directory != null ) {
            $documentVaultomAccess = DocumentVaultomAccess::where('UserId', $userId)->where('DatumDirectoryId', $directory->Id)->orderBy('Id', 'desc')->first();
            $documentVaultomAccess->Access = 1;
            $documentVaultomAccess->save();
        }
        return true;
    }

    /**
     * @param $userId
     * @param $hostedConfigurationId
     */
    public function SaveExistingContactConfigurationMapping ($userId, $hostedConfigurationId) {
        $existingContactConfigurationMapping = ExistingContactConfigurationMapping::where('ConfigurationId', $hostedConfigurationId)->where('UserId', $userId)->first();
        if(empty($existingContactConfigurationMapping) && $existingContactConfigurationMapping == null ) {
            $existingContactConfigurationMapping = new ExistingContactConfigurationMapping();
            $existingContactConfigurationMapping->UserId          = $userId;
            $existingContactConfigurationMapping->ConfigurationId = $hostedConfigurationId;
            $existingContactConfigurationMapping->CreatedDate     = date('Y-m-d H:i:s');
            $existingContactConfigurationMapping->CreatedBy       = $userId;
            $existingContactConfigurationMapping->save();
        }
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

