<?php
include "../connectDb.php";

if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set( 'precision', 17 );
    ini_set( 'serialize_precision', -1 );
}

$uploadapprove = 0;
$ipaddr = $_SERVER['REMOTE_ADDR'];

// Get header Authorization
function getAuthorizationHeader(){
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI 
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]); //authorize here
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    //echo "gotem ";
    return $headers;
}
// get access token from header

function getBearerToken() {
    $headers = getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
function generateBearerToken(){
	$newtoken = bin2hex(random_bytes(64));
	return $newtoken;
}

getAuthorizationHeader();
$receivedToken = getBearerToken();

$sql = "SELECT * FROM TokenDb WHERE token = '$receivedToken'";
  $result = $conn->query($sql);
  
  if ($result->num_rows > 0) {
  // output data of each row
    while($row = $result->fetch_assoc()) { 
    	$expiretimestamp = strtotime($row["expired_time"]);
        $useridfromtokendb = $row["user_id"];
    	$now = time();
    	if ($now < $expiretimestamp + 28800){ //HKT
    	  //echo "token authorized.";
    	  $uploadapprove = 1;
    	}
    	else{
    	  $uploadapprove = 0;
    	}
    	break;
    	//}
    }
  }
  else{
$uploadapprove = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $uploadapprove) {

$data = json_decode(file_get_contents('php://input'), true);
$subj = $data["subject"];

$sys = NULL; $dia = NULL; $pulse = NULL; $body_temp = NULL; $spo2 = NULL;

$sys = (double)$data["hdt"]["sys"];
$dia = (double)$data["hdt"]["dia"];
$pulse = (double)$data["hdt"]["pulse"];
$body_temp = (double)$data["hdt"]["body_temp"];

if ($data["hdt"]["spo2"] != NULL)
$spo2 = (double)$data["hdt"]["spo2"];
//echo gettype($sys);

if ($data["user_id"] != $useridfromtokendb){
$uploadapprove = 0;
}

if (str_contains($data["user_id"], 'HHST')){
include "../HHS.php";
exit();
}
else if (str_contains($data["user_id"], 'IDSP')){
include "../HHS2.php";
exit();
}

if ($subj === "Health Measurement Record" && $uploadapprove){
    //echo "upload ready. ";
    	$conn->autocommit(false);               
    
    	$query = "INSERT INTO pc_health_data (`android_id`, `measurement_source`, `user_id`,
    	`measurement_date`, `record_key`, `systolic_blood_pressure`, `diastolic_blood_pressure`, `pulse`, 
    	`body_temperature`, `spo2`, `created_by`)
    	VALUES ('$data[android_id]', '$data[source]', '$data[user_id]', '$data[timestamp]',
    	'$data[record_id]', '$sys', '$dia', '$pulse', 
    	'$body_temp', '$spo2', '$ipaddr')";

     
    	
    	
    	if ($conn->query($query) === TRUE) {
    		//echo "New record created successfully."."<br/>";
    		$conn->commit();
    	}
 
    	else {
                //echo "Connection failed: " . $conn->connect_error;
                $status = 101;
    	        $thistoken = "Cannot insert/update";
    	        $somejson = ['status' => $status, 'errorMessage' => $thistoken];
    	        header('Content-type: application/json');
    	        echo json_encode($somejson);
                $conn->rollBack();
                exit();
    	}

$sql = "SELECT * FROM `user_health_monitoring` WHERE user_id = $data[user_id]";
$result = $conn->query($sql);

if ($result->num_rows > 0) {

   while($row = $result->fetch_assoc()) {

   	$status = 0;
   	$somejson = 
    ['status' => $status,
    'completedDatetime' => $data["timestamp"], 
   	'userId' => $data["user_id"], 
    'systolicBloodPressureUpperLimit' => floatval($row["sys_upper_limit"]), 
   	'systolicBloodPressureLowerLimit' => intval($row["sys_lower_limit"]), 
   	'diastolicBloodPressureUpperLimit' => intval($row["dia_upper_limit"]), 
   	'diastolicBloodPressureLowerLimit' => intval($row["dia_lower_limit"]),
   	'pulseUpperLimit' => intval($row["pulse_upper_limit"]),
   	'pulseLowerLimit' => intval($row["pulse_lower_limit"]), 
   	'bodyTemperatureUpperLimit' => (float)number_format($row["temperature_upper_limit"], 2), 
   	'bodyTemperatureLowerLimit' => (float)number_format($row["temperature_lower_limit"], 2),
   	'spo2UpperLimit' => (float)number_format($row["spo2_upper_limit"]/100, 2),
   	'spo2LowerLimit' => (float)number_format($row["spo2_lower_limit"]/100, 2) ];
   	
   	header('Content-type: application/json');
   	echo json_encode($somejson);
   }
}

else if ($result->num_rows == 0){


$sql = "SELECT * FROM `user_health_monitoring` WHERE user_id = 000000"; //default
$result = $conn->query($sql);

   while($row = $result->fetch_assoc()) {

   	$status = 0;
   	$somejson = 
    ['status' => $status,
    'completedDatetime' => $data["timestamp"], 
   	'userId' => $data["user_id"], 
    'systolicBloodPressureUpperLimit' => intval($row["sys_upper_limit"]), 
   	'systolicBloodPressureLowerLimit' => intval($row["sys_lower_limit"]), 
   	'diastolicBloodPressureUpperLimit' => intval($row["dia_upper_limit"]), 
   	'diastolicBloodPressureLowerLimit' => intval($row["dia_lower_limit"]),
   	'pulseUpperLimit' => intval($row["pulse_upper_limit"]),
   	'pulseLowerLimit' => intval($row["pulse_lower_limit"]), 
   	'bodyTemperatureUpperLimit' => (float)number_format($row["temperature_upper_limit"], 2), 
   	'bodyTemperatureLowerLimit' => (float)number_format($row["temperature_lower_limit"], 2),
   	'spo2UpperLimit' => (float)number_format($row["spo2_upper_limit"]/100, 2),
   	'spo2LowerLimit' => (float)number_format($row["spo2_lower_limit"]/100, 2) ];
   	
   	header('Content-type: application/json');
   	echo json_encode($somejson);
   }
}

else{
$status = 401;
$thistoken = 'Invalid authorized credentials';
$somejson = ['status' => $status, 'error message' => $thistoken];
header('Content-type: application/json');
echo json_encode($somejson);
}
}

}

if($uploadapprove == 0){
//echo "here";
$status = 401;
$thistoken = 'Invalid authorized credentials';
$somejson = ['status' => $status, 'error message' => $thistoken];
header('Content-type: application/json');
echo json_encode($somejson);
}
?>