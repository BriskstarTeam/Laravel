<?php

namespace App\Http\Controllers;
use App\PreferredModules;
use App\Companies;
use App\Countries;
use App\States;
use App\ExchangeStatus;
use App\IndustryRole;
use App\BrokerType;
use App\InvestorType;
use Illuminate\Http\Request;
use App\Database\DatabaseConnection;
use Illuminate\Support\Facades\DB;
use App\Property\Property;
use App\Property\ListingStatus;
use App\Property\BuildingClass;
use App\Mail\Email;


class CommonController {

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index( Request $request ) {
        $query = PreferredModules::query();
        $query->where('Status', '=', 1);
        $query->with('getAcquisitionCriteriaType');
        $query->select('PreferredModules.Id', 'PreferredModules.Name');
        $acquisitionCriteriaType = $query->get();

        $query1 = PreferredModules::query();
        $query1->where('Status', '=', 1);
        $query1->where('Name', '=', 'PrefferedPropertyType');
        $query1->with('getAcquisitionCriteriaType');
        $query1->select('PreferredModules.Id', 'PreferredModules.Name');
        $acquisitionCriteriaType1 = $query1->get();

        $dataContact = [];
        if( !$acquisitionCriteriaType1->isEmpty() ) {
            foreach ( $acquisitionCriteriaType1 as $key => $value ) {
                $dataContact[$value->Name] = $value;
            }
        }

        $countries = Countries::query();
        $countries->where('Code', '!=', 'US');
        $countries->select('Code', 'CountryName');
        $countries = $countries->get();

        $states = States::query();
        $states->select('Code', 'StateName');
        $states = $states->get();
        $data = [];

        if( !$acquisitionCriteriaType->isEmpty() ) {
            foreach ( $acquisitionCriteriaType as $key => $value ) {
                $data[$value->Name] = $value;
            }
        }

        $ExchangeStatus = ExchangeStatus::where('Active', 1)->select(['Id', 'ExchangeStatusName AS ExchangeStatus', 'Code', 'Active'])->get();
        $ExchangeStatus1 = [];
        if(!empty($ExchangeStatus) && $ExchangeStatus != null ) {
            foreach($ExchangeStatus as $key => $value ) {
                $ExchangeStatus1[$value->Id] = $value->ExchangeStatus;
            }
        }

        $IndustryRole = IndustryRole::where('Active', 1)->get();
        $IndustryRole1 = [];
        if(!empty($IndustryRole) && $IndustryRole != null ) {
            foreach($IndustryRole as $key => $value ) {
                $IndustryRole1[$value->Id] = $value->Role;
            }
        }

        $BrokerType = BrokerType::where('Active', 1)->where('IsAdminType', 0)->get();
        $BrokerType1 = [];
        if(!empty($BrokerType) && $BrokerType != null ) {
            foreach($BrokerType as $key => $value ) {
                $BrokerType1[$value->Id] = $value->Type;
            }
        }

        $InvestorType = InvestorType::where('Active', 1)->get();
        $InvestorType1 = [];
        if(!empty($InvestorType) && $InvestorType != null ) {
            foreach($InvestorType as $key => $value ) {
                $InvestorType1[$value->Id] = $value->Type;
            }
        }

        $data['ExchangeStatus'] = (object)$ExchangeStatus1;
        $data['IndustryRole']   = (object)$IndustryRole1;
        $data['BrokerType']     = (object)$BrokerType1;
        $data['InvestorType']   = (object)$InvestorType1;

        $databaseConnection = new DatabaseConnection();
        $configuration      = $databaseConnection->getConfiguration();
        $companies = Companies::select('Id', 'CompanyName')->where('IsDelete', '=', 0)->get();
            
        $companiesData = [];
        if( !empty($companies) && $companies != null ) {
            foreach ( $companies as $key => $value ) {
                $companiesData[] = array(
                    'label' => $value->CompanyName,
                    'value' => (string)$value->CompanyName,
                );
            }
        }

        $cities = $this->cities();
        $finalCities = [];
        if(!empty($cities)) {
            foreach ($cities as $key => $value) {
                $finalCities[] = array(
                    'label' => $value,
                    'value' => $value
                );
            }
        }

        $data['company'] = $companiesData;
        $data['Country'] = $countries;
        $data['State'] = $states;
        $data['City'] = $finalCities;

        $query = Property::query();
        $query->join('PropertyAddress', 'PropertyAddress.PropertyId', '=', 'Property.Id');
        $sql1 = "DISTINCT PropertyAddress.ZipCode as label, PropertyAddress.City as city";
        $query->select(DB::raw($sql1));
        $locationState = [];
        if(!empty($states) && $states != null ) {
            foreach ( $states as $key => $value ) {
                $locationState[] = $value->StateName;
            }
        }

        $propertyLocation = $query->get();
        $locationData = [];
        if(!empty($propertyLocation) && $propertyLocation != null ) {
            foreach($propertyLocation as $key => $value ) {
                if($value->label != null) {
                    $locationData[] = $value->label;
                }
            }
        }

        $locationCity = [];
        if(!empty($propertyLocation) && $propertyLocation != null ) {
            foreach($propertyLocation as $key => $value ) {
                if($value->city != null) {
                    $locationCity[] = $value->city;
                }
            }
        }

        $locationCity = array_unique($locationCity);
        $propertyLocation = array_merge($locationData, $locationState);
        $propertyLocation = array_merge($locationCity, $propertyLocation);
        
        $query = Property::query();
        $query->join('Savestatus', 'SaveStatus.Id', '=', 'Property.SaveStatusId');
        $query->where('Savestatus.Description', '=', 'Active');
        $query->join('PropertyStatus', 'PropertyStatus.Id', '=', 'Property.PropertyStatusId');
        $query->where('PropertyStatus.Description', '!=', 'Closed');
        $query->select('Property.Name');
        $property = $query->get();

        $maxQuery = Property::query();
        $maxQuery->join('SaveStatus', 'SaveStatus.Id', '=', 'Property.SaveStatusId');
        $maxQuery->where('SaveStatus.Description', '=', 'Active');
        $maxQuery->join('PropertyStatus', 'PropertyStatus.Id', '=', 'Property.PropertyStatusId');
        $maxQuery->where('PropertyStatus.Description', '!=', 'Closed');
        $maxQuery->join('PropertyFinancialDetails', 'PropertyFinancialDetails.PropertyId', '=', 'Property.Id');
        $sql = 'Max(PropertyFinancialDetails.AskingPrice) as max_asking_price, Min(NULLIF(PropertyFinancialDetails.AskingPrice, 0)) as min_asking_price';
        $maxQuery->select(DB::raw($sql));

        $maxProperty = $maxQuery->get();

        if($maxProperty->isNotEmpty()) {
            $maxProperty = $maxProperty;
        } else {
            $maxProperty = [];
        }
        if($property->isNotEmpty()) {
            $property = $property;
        } else {
            $property = [];
        }
        $propertyStatus = ListingStatus::all();
        $propertyBuildingClass = BuildingClass::all();

        $data['property_status'] = $propertyStatus;
        $data['property_tenancy'] = $this->propertyTenancy();
        $data['property_building_class'] = $propertyBuildingClass;
        $data['property_location'] = $propertyLocation;
        $data['property_name'] = $property;
        $data['asking_price_min_max'] = $maxProperty;

        if(!empty($dataContact) && $dataContact != null) {
            $data['ContactPrefferedPropertyType'] = $dataContact;
        } else {
            $data['ContactPrefferedPropertyType'] = [];
        }
        
        return response()
            ->json(
                [
                    'status' => 'success',
                    'message' => [],
                    'errors' => [],
                    'data' => $data
                ], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \SendGrid\Mail\TypeException
     */
    public function insightsSharingEmail(Request $request){
        
        $request->validate([
            'property_id' => 'required',
            'share_email' => 'required',
            'your_email' => 'required|email',
            'your_name' => 'required',
            'subject' => 'required',
            'message' => 'required'
        ]);
        $str = str_replace(PHP_EOL, ',', trim($request->share_email));
        $str = str_replace('/', ',', $str);
        $requestedEmails = explode(",",$str);
        $insights_data =  json_decode($request->insights_data);
        
        $subjectName = $request->subject;
        $propertyUrl = $insights_data->URL;
        $content = "<div>";
        $content .= $request->message;
        $content .= "</div>";

        $email = new Email();
        $message = $email->email_content('', $content,true);

        $bulkMail = [];
        if(!empty($requestedEmails)) {
            $i = 0;
            foreach ($requestedEmails as $key => $value ) {
                if($value != "") {
                    $bulkMail[$i]['email'] = trim($value);
                    $bulkMail[$i]['name'] = "";
                    $i++;
                }
            }
        }

        $fromTo['name'] = $request->your_name;

        $replyTo = $request->your_email;

        $email->sendEmail( $subjectName, $bulkMail, $message, array(), '', $replyTo, $fromTo);

        $to[0]['email'] = $request->your_email;
        $to[0]['name'] = $request->your_name;

        //$email->sendEmail( $subjectName, $to, $message);

        return response()->json(
            [
                'status' => 'success',
                'message' => "Email send successfully.",
                'errors' => [],
                'data' => []
            ], 200);
    }

    /**
     * @return array
     */
    public function propertyTenancy() {
        return array(
            'Vacant' => 'Vacant',
            'Single' => 'Single',
            'Multi' => 'Multiple',
        );
    }

    /**
     * @return array
     */
    public function cities () {
        return ["Abilene", "Akron", "Albuquerque", "Alexandria", "Allen", "Allentown", "Amarillo", "Anaheim", "Anchorage", "Ann Arbor", "Antioch", "Arlington", "Arvada",
            "Athens", "Atlanta", "Augusta", "Aurora", "Austin", "Bakersfield", "Baltimore", "Baton Rouge", "Beaumont", "Bellevue", "Bend", "Berkeley", "Billings", "Birmingham",
            "Boise", "Boston", "Boulder", "Bridgeport", "Broken Arrow", "Brownsville", "Buffalo", "Burbank", "Cambridge", "Cape Coral", "Carlsbad", "Carmel", "Carrollton",
            "Cary", "Cedar Rapids", "Centennial", "Chandler", "Charleston", "Charlotte", "Chattanooga", "Chesapeake", "Chicago", "Chico", "Chula Vista", "Cincinnati",
            "Clarksville", "Clearwater", "Cleveland", "Clinton", "Clovis", "College Station", "Colorado Springs", "Columbia", "Columbus", "Concord", "Coral Springs",
            "Corona", "Corpus Christi", "Costa Mesa", "Dallas", "Daly City", "Davenport", "Davie", "Dayton", "Denton", "Denver", "Des Moines", "Detroit", "Downey", "Durham",
            "Edinburg", "El Cajon", "El Monte", "El Paso", "Elgin", "Elizabeth", "Elk Grove", "Escondido", "Eugene", "Evansville", "Everett", "Fairfield", "Fargo",
            "Fayetteville", "Fontana", "Fort Collins", "Fort Lauderdale", "Fort Wayne", "Fort Worth", "Fremont", "Fresno", "Frisco", "Fullerton", "Gainesville", "Garden Grove",
            "Garland", "Gilbert", "Glendale", "Grand Prairie", "Grand Rapids", "Greeley", "Green Bay", "Greensboro", "Gresham", "Hampton", "Hartford", "Hayward", "Henderson",
            "Hialeah", "High Point", "Hillsboro", "Hollywood", "Honolulu", "Houston", "Huntington Beach", "Huntsville", "Independence", "Indianapolis", "Inglewood", "Irvine",
            "Irving", "Jackson", "Jacksonville", "Jersey City", "Joliet", "Jurupa Valley", "Kansas City", "Kansas City", "Kent", "Killeen", "Knoxville", "Lafayette",
            "Lakeland", "Lakewood", "Lancaster", "Lansing", "Laredo", "Las Cruces", "Las Vegas", "League City", "Lewisville", "Lexington", "Lincoln", "Little Rock",
            "Long Beach", "Los Angeles", "Louisville", "Lowell", "Lubbock", "Macon", "Madison", "Manchester", "McAllen", "McKinney", "Memphis", "Meridian", "Mesa",
            "Mesquite", "Miami", "Miami Gardens", "Midland", "Milwaukee", "Minneapolis", "Miramar", "Mobile", "Modesto", "Montgomery", "Moreno Valley", "Murfreesboro",
            "Murrieta", "Naperville", "Nashville", "New Haven", "New Orleans", "New York", "Newark", "Newport News", "Newport Beach", "Norfolk", "Norman", "North Charleston",
            "North Las Vegas", "Norwalk", "Oakland", "Oceanside", "Odessa", "Oklahoma City", "Olathe", "Omaha", "Ontario", "Orange", "Orlando", "Overland Park", "Oxnard",
            "Palm Bay", "Palmdale", "Pasadena", "Paterson", "Pearland", "Pembroke Pines", "Peoria", "Philadelphia", "Phoenix", "Pittsburgh", "Plano", "Pomona", "Pompano Beach",
            "Port St. Lucie", "Portland", "Providence", "Provo", "Pueblo", "Raleigh", "Rancho Cucamonga", "Reno", "Renton", "Rialto", "Richardson", "Richmond", "Riverside",
            "Rochester", "Rockford", "Roseville", "Round Rock", "Sacramento", "Saint Paul", "Salem", "Salinas", "Salt Lake City", "San Angelo", "San Antonio", "San Bernardino",
            "San Diego", "San Francisco", "San Jose", "San Mateo", "Sandy Springs", "Santa Ana", "Santa Clara", "Santa Clarita", "Santa Maria", "Santa Rosa", "Savannah",
            "Scottsdale", "Seattle", "Shreveport", "Simi Valley", "Sioux Falls", "South Bend", "Sparks", "Spokane", "Spokane Valley", "Springfield", "St. Louis",
            "St. Petersburg", "Stamford", "Sterling Heights", "Stockton", "Sugar Land", "Sunnyvale", "Surprise", "Syracuse", "Tacoma", "Tallahassee", "Tampa", "Temecula",
            "Tempe", "Thornton", "Thousand Oaks", "Toledo", "Topeka", "Torrance", "Tucson", "Tulsa", "Tuscaloosa", "Tyler", "Vacaville", "Vallejo", "Vancouver", "Ventura",
            "Victorville", "Virginia Beach", "Visalia", "Vista", "Waco", "Warren", "Washington", "Waterbury", "West Covina", "West Jordan", "West Palm Beach",
            "West Valley City", "Westminster", "Wichita", "Wichita Falls", "Wilmington", "Winston–Salem", "Woodbridge", "Worcester", "Yonkers"];
    }
}