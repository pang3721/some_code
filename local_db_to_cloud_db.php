<?php
$servername = "localhost";
$username = "username";
$password = "pw";
$dbname = "data";

$servername2 = "172.00.000.0"; 
$username2 = "user";
$password2 = "pw2";
$dbname2 = "thisdb";

$conn = new mysqli($servername, $username, $password, $dbname); //ori_db
$conn2 = new mysqli($servername2, $username2, $password2, $dbname2); //local_db

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
if ($conn2->connect_error) {
  die("Connection failed: " . $conn2->connect_error);
}
$conn2->set_charset('utf8mb4');

$sql = "SELECT * from table1 ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$last_upload_id = intval($row['last_upload_id']); 
echo "upload begins with: ".$last_upload_id."<br/>";

//$target_id = intval($last_upload_id) + 40;
//if ($target_id >= 9999)
//exit();
//for testing

$record = array(
	"vaild_record_no" => array(),
	"phms_id" => array());
	
$sql2 = "SELECT `Barcode`, `id` from IOT_data WHERE `id` > $last_upload_id";
$result2 = $conn->query($sql2);
if ($result2->num_rows > 0) {
  	while($row2 = $result2->fetch_assoc()) {
  		//echo var_dump($row2);
  		$barcode = $row2["Barcode"];
  		$rid = $row2["id"];
  		$sql3 = "SELECT `member_no`, `id` from member WHERE member_no = $barcode";
  		$result3 = $conn2->query($sql3);
  		if($result3->num_rows != 0) {
  			$row3 = $result3->fetch_assoc();
  			//echo "barcode: ".$barcode." exist. record_id: ";
  			array_push($record["vaild_record_no"], $rid);
  			array_push($record["phms_id"], $row3["id"]);
  			echo end($record["vaild_record_no"])." phms_id:";
  			echo end($record["phms_id"])."<br/>";
  		}
  	}
}

$i = 0;
$breaker = 0;
$activity_participant_ref = array();

foreach ($record["vaild_record_no"] as $r){
	
	$infor = "";
	$pid = $record["phms_id"][$i];
	$refid = intval($r);
	$sql4 = "SELECT * from IOT_data WHERE `id` = $refid";
	$result4 = $conn->query($sql4);
	$row4 = $result4->fetch_assoc();
	$IOT_data_id = $row4["id"];
	echo "IOT_data id: ".$IOT_data_id;
	echo " phms_id: ".$pid." ";
	$i++;
	$somestring = "體檢日期: ";
	$somestring .= $row4["MeasureTime"];
	$now = date("Y-m-d H:i:s", time());
	
	if($row4["UnitNo"] == 'CC01'||$row4["UnitNo"] == 'SC1_01'){
		$breaker = 1;
    	$somestring .= " \nheight: $row4[Height] \nweight: $row4[Weight] \nBMI: $row4[BMI] 
        Blood HighPressure: $row4[Highpressure] \nBlood LowPressure: $row4[LowPressure]";

    	
    	$ctime = $row4["MeasureTime"];
    	if($row4["UnitNo"] == 'CC01')
    	$content = "01";
    	else if($row4["UnitNo"] == 'SC1_01')
    	$content = "SC1";
    	//echo $content;
	
	    try{
		$conn2->autocommit(false);
    	
		$sql4_1 = "INSERT INTO non_activity_record (`non_activity_id`, `member_id`, 
		`IOTdata_id`, `record_at`, `content`, `details`, `credit`, `created_by`)
        VALUES (15, '$pid', '$IOT_data_id', '$ctime', '$content', '$somestring',
        1.00, 'admin')";
        
        $sql4_2 = "INSERT INTO non_activity_participant (`non_activity_id`, `type`, 
        `member_id`, `status`, `created_at`, `created_by`)
        VALUES (15, 10, '$pid', 11, '$now', 'admin')";
        
        if ($conn2->query($sql4_1) === TRUE) {
        	echo "New record added to activity_record. ";
        	$conn2->commit();
        	
        	$sql4_3 = "SELECT `id` from activity_record ORDER BY id DESC LIMIT 1";
            $result4_3 = $conn2->query($sql4_3);
            $row4_3 = $result4_3->fetch_assoc();
            $activity_record_id = $row4_3["id"];
            //echo " rerid = ".$refid."<br/>";
            $sql5 = "UPDATE IOT_data set `is_uploaded`= 1, `upload_ref_no` = '$activity_record_id'
            WHERE id='$refid'";
            if ($conn->query($sql5) === TRUE) 
            $conn->commit();
            else
            echo"cannot update iotdata for reference.".$conn->error;
        } 
        else
        	echo "01 cannot upload to 008 activity_record. ";
        	
        echo "<br/>";
	    }
	    
	    catch(Exception $e){
	    	echo "Error: ". " " . $conn2->error;
	    	$conn2->rollBack();
	    }
	} //cc01
   
   else if($row4["UnitNo"] == 'CC02' || $row4["UnitNo"] == 'CC03'|| $row4["UnitNo"] == 'SC5_01'){
		$breaker = 1;
		$FatRate = (float)number_format($row4["FatRate"], 2);
		$Minerals = (float)number_format($row4["Minerals"], 2);
		$Muscle = (float)number_format($row4["Muscle"], 2);
		$Protein = (float)number_format($row4["Protein"], 2);
		$Water = (float)number_format($row4["Water"], 2);
		//$BasalMetabolismReference = (float)number_format($row4["BasalMetabolismReference"], 8);
		//$BasicMetabolism = (float)number_format($row4["BasicMetabolism"], 7);
		$WalkingTime = (float)number_format($row4["WalkingTime"], 2);
		
		$somestring .= " \nheight: $row4[Height] \nweight: $row4[Weight] \nBMI: $row4[BMI] 
        Blood HighPressure: $row4[Highpressure] \nBlood LowPressure: $row4[LowPressure]
        Fatrate: $FatRate \nMinerals: $Minerals kg\nMuscle: $Muscle kg
        Protein: $Protein kg\nWater: $Water kg";

    	
    	$ctime = $row4["MeasureTime"];
    	if($row4["UnitNo"] == 'CC02')
    	$content = "CC02";
    	else if($row4["UnitNo"] == 'CC03')
    	$content = "CC03";
    	else if($row4["UnitNo"] == 'SC5')
    	$content = "SC5";
	
	    try{
		$conn2->autocommit(false);
    	
		$sql4_1 = "INSERT INTO non_activity_record (`non_activity_id`, `member_id`, 
		`IOTdata_id`, `record_at`, `content`, `details`, `credit`, `created_by`)
        VALUES (15, '$pid', '$IOT_data_id', '$ctime', '$content', '$somestring',
        1.00, 'admin')";
        
        $sql4_2 = "INSERT INTO activity_participant (`non_activity_id`, `type`, 
        `member_id`, `status`, `invoice_no`, `created_at`, `created_by`)
        VALUES (15, 10, '$pid', 11, '$row4[RecordNo]', '$now', 
        'admin')";
        
        if ($conn2->query($sql4_1) === TRUE) {
        	echo "New record added to 008 activity_record. ";
        	$conn2->commit();
        	
        	$sql4_3 = "SELECT `id` from activity_record ORDER BY id DESC LIMIT 1";
            $result4_3 = $conn2->query($sql4_3);
            $row4_3 = $result4_3->fetch_assoc();
            $activity_record_id = $row4_3["id"];
            //echo " rerid = ".$refid."<br/>";
            $sql5 = "UPDATE IOT_data set `is_uploaded`= 1, `upload_ref_no` = '$activity_record_id'
            WHERE id='$refid'";
            if ($conn->query($sql5) === TRUE) 
            $conn->commit();
            else
            echo"cannot update iotdata for reference.".$conn->error;
            
        } 
        else{
        	echo "02/03 cannot upload to 008 activity_record.||";
        	echo "pid:".$pid." iotid: ".$IOT_data_id." ctime: ".$ctime.
        	" content: ".$content." string:".$somestring;
        }
        echo "<br/>";
	    }
	    
	    catch(Exception $e){
	    	echo "Error: ". " " . $conn2->error;
	    	$conn2->rollBack();
	    }
	} //cc02,03
	
	else if($row4["UnitNo"] == 'inbody270'){
		$somestring .= "";
	} //inbody270
   
   if ($r == end($record["vaild_record_no"])){
    $sql6 = "INSERT INTO table1 (`last_upload_id`, `created_at`)
         VALUES ('$r', '$now')";
    if ($conn->query($sql6) === TRUE) 
    $conn->commit();
   }

}
?>