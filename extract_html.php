<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . './simple_html_dom.php';

function getZestimate($address) {
    $address = str_replace(' ','+',$address);
    $url = "https://www.zillow.com/webservice/GetSearchResults.htm?zws-id=X1-ZWz18nuirxd4i3_2avji&address=". $address ."&citystatezip=Texas%2C+USA&rentzestimate=true";

    $data = simplexml_load_file($url);
    $datas = new \stdClass();

    $error_code = array('501','502','503','504','505','506','507','508');
    $code = (string) $data->message->code;
    if (!in_array($code,$error_code))
    {
        $homedetails_url = $data->response->results->result->links->homedetails;
        $zestimate = $data->response->results->result->zestimate;

        $datas->currency = (string) $zestimate->amount->attributes()->currency[0];
        $datas->z_amount = (string) $zestimate->amount[0];
        $datas->z_valueChange = (string) $zestimate->valueChange[0];
        $datas->z_valuationRangeLow = (string) $zestimate->valuationRange->low[0];
        $datas->z_valuationRangeHigh = (string) $zestimate->valuationRange->high[0];
        $datas->z_percentile = (string) $zestimate->percentile[0];
        $datas->homedetails_url = $homedetails_url;
    }
    
    return $datas;
}

function homeDetails($HOME_DETAIL_URL) {
    $home_data_trim = array('Type: ', 'Year built: ', 'Heating: ','Cooling: ','Parking: ','HOA: ', 'Lot: ', 'Price/sqft: ');
    $home_data_arr = array();
    
    if(!empty($HOME_DETAIL_URL))
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $HOME_DETAIL_URL);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Keep-Alive: 115',  'Connection: keep-alive',  'Content-type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1');
        $response = curl_exec($ch);
    
        $html = new simple_html_dom();
        $html->load($response);
    
        $trim = 0;
    
        foreach($html->find('li[class^=ds-home-fact-list-item]') as $spans) {
            $value = str_replace($home_data_trim[$trim],'',$spans->plaintext);
            array_push($home_data_arr, $value);
            $trim++;
        }
    }
    return $home_data_arr;
}

function getClient()
{
    $client = new \Google_Client();
    $client->setApplicationName('Google Sheets API PHP Quickstart');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $client->setAuthConfig(__DIR__ . './credentials.json');
    return $client;
}

$client = getClient();
$service = new Google_Service_Sheets($client);

$data = [];

$currentRow = 2;

$spreadsheetId = '1ayadKO6whys2nsIeA7SRJzeZzXO_cl5NiWDG7F3xDdI';
$range = 'Class Data!A2:I';
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

if (empty($values)) {
    print "No data found.\n";
} else {
    foreach ($values as $row) {
        // Print columns A and E, which correspond to indices 0 and 4.
        // printf("%s \n", $row[0]);
        // $data[] = [
        //     'col-a' => $row[0]
        // ];

        $z_data = getZestimate($row[0]);
        
        $z_data_bool = empty((array)$z_data) ? true : false;

        $home_details = !$z_data_bool ? homeDetails($z_data->homedetails_url) : []; 

        $data = $z_data_bool ? array() : array($home_details[1],$home_details[6],$home_details[7],$z_data->z_amount,$z_data->z_valueChange,$z_data->z_valuationRangeLow,$z_data->z_valuationRangeHigh,$z_data->z_percentile);

        $updateRange = 'B'.$currentRow.':I';
        $updateBody = new \Google_Service_Sheets_ValueRange([
            'range' => $updateRange,
            'values' => [
                $data
            ],
        ]);
    
        $service->spreadsheets_values->update(
            $spreadsheetId,
            $updateRange,
            $updateBody,
            ['valueInputOption' => 'RAW']
        );
    
        $currentRow++;
    }
}
?>