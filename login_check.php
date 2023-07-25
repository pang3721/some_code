<?php
include "../connectDb.php";

//echo $response.'<br/>';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // collect value of input field
  $id = $_POST['userId'];
  $pw = $_POST['password'];
  $count = 0;
  
  $pwboolean = 0; $pw1 = 'TMDHC_'; $today .= date("Y-m-d"); $emptycount = 0;
  $pw1 .= $today;
  $pw1 .= '_';
  $pw1 .= $id;
  $hashpw = md5($pw1);
  $pw = $_POST['password'];
  
  if ($pw === $hashpw && $pw !== NULL){
    //pw is correct
    $pwboolean = 1;
  }
  else 
  $pwboolean = 0;
}

$status = 100;
$thistoken = NULL;
$expiredtime = NULL;

if($pwboolean === 1){

  $sql = "SELECT * FROM TokenDb WHERE user_id = '$id'";
  $result = $conn->query($sql);
  $status = 0;
  
  if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
    	
    	$expiredtimestamp = strtotime($row["expired_time"]);
    	$now = time();
    	
    	if($now < $expiredtimestamp){ //token already exists and valid
    	    //echo "vaild token";
    	    $status = 0;
    		$thistoken = $row["token"];
    		$expiredtimestamp = $now + 900 + 28800;
    	}
    	else if ($now > $expiredtimestamp){//token already exists and not valid
    		$status = 0;
    		$thistoken = bin2hex(random_bytes(32));
    		$expiredtimestamp = $now + 900 + 28800;
    	}
    	else{
    		//echo "unexpected situation.";
    		$status = 100;
    		$thistoken = 'xxx';
    		$somejson = ['status' => $status, 'errorMessage' => $thistoken];
    		header('Content-type: application/json');
    		echo json_encode($somejson);
    		break;
    	}
         //update expired time
    	$newexpiredtime = date('Y-m-d H:i:s', $expiredtimestamp);
    	$somejson = ['status' => $status, 'accessToken' => $thistoken, 'expiredTime' => $newexpiredtime];
    	header('Content-type: application/json');
    	echo json_encode($somejson);
    	$sql = "UPDATE TokenDb SET expired_time='$newexpiredtime', token = '$thistoken' WHERE user_id='$id'";
 	    if ($conn->query($sql) === TRUE) {
 		//echo "Record updated successfully";
 	    } 
 	    else {
 		    $status = 101;
    		$thistoken = 'Cannot insert/update';
    		$somejson2 = ['status' => $status, 'errorMessage' => $thistoken];
    		header('Content-type: application/json');
    		echo json_encode($somejson2);
    		exit();
 	    }
    	
    	break;
    }
    //echo "got out ";
  }
  
  else {
    	//user first time log in. assign new token
  	$thistoken = bin2hex(random_bytes(32));
  	$now = time();
  	$expiredtimestamp = $now + 900;
  	$newexpiredtime = date('Y-m-d H:i:s', $expiredtimestamp);
  	
  	$sql = "INSERT INTO TokenDb (`user_id`, `token`, `expired_time`) 
  	VALUES ('$id', '$thistoken', '$newexpiredtime')";
  	
  	if ($conn->query($sql) === TRUE) {
 		$somejson = ['status' => $status, 'accessToken' => $thistoken, 'expiredTime' => $newexpiredtime];
        header('Content-type: application/json');
        echo json_encode($somejson);
  	}
  	else {
        $status = 101;
    	$thistoken = "Cannot insert/update";
    	$somejson = ['status' => $status, 'errorMessage' => $thistoken];
    	header('Content-type: application/json');
    	echo json_encode($somejson);
  	}
  }
  
  $conn->close();
}

else if($pwboolean === 0){
	$status = 401;
	$thistoken = 'Invalid authorized credentials';
	$somejson = ['status' => $status, 'errorMessage' => $thistoken];
        header('Content-type: application/json');
	echo json_encode($somejson);
}

?>