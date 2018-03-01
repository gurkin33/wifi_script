<?php
/*
wifi script version 1.2.0
*/

include 'config.php';

$debug=0;
if(isset($_GET['debug'])) $debug=1;

//echo $debug;

$newLine="</br> \n";
echo "Hello!" . $newLine;
if ($debug) echo "Here I'll debug my script!" . $newLine;

echo "Current time: " . time() . ' or ' . date('d-M-y') . $newLine;

$conn_mysql = new mysqli(
	$mysql_access['host'], 
	$mysql_access['username'], 
	$mysql_access['password'], 
	$mysql_access['db_name']
);
if ($conn_mysql->connect_error) die($conn_mysql->connect_error);
$conn_mysql->set_charset("utf8");
echo "Here";
$unexpired=array();
$sql_take_all = "SELECT * FROM dehotel_wifi WHERE expired=0";

$result_take_all = $conn_mysql->query($sql_take_all);
	$rows_take_all = $result_take_all->num_rows;
	for ($a = 0 ; $a < $rows_take_all; ++$a){
		$result_take_all->data_seek($a);
		$row_take_all = $result_take_all->fetch_array(MYSQLI_ASSOC);
		if ( ($row_take_all['date_departure'] + 57600) < time() ) {
			if ($debug) echo 'Order time expired'. $newLine;
			$sql_update_expired = "UPDATE dehotel_wifi SET expired=1  WHERE order_num='".$row_take_all['order_num']."'";
			if ($conn_mysql->query($sql_update_expired) === true) if ($debug) echo "Updated successfully. Expired" . $newLine;
		} else {
			$unexpired[$row_take_all['order_num']]=$row_take_all;
		}
	}
//echo '<pre>';
//print_r($unexpired);
//echo '</pre>';

$conn = oci_connect(
	$oracle_access['one'],
	$oracle_access['two'],
	$oracle_access['three']
);
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}

$stid = oci_parse($conn, 'select yres_id, yres_exparrtime, yres_expdeptime from yres where yres_exparrtime <= (select wgbs_datevalue from wgbs) and yres_actdeptime is null and yres_resstatus <> 3');
oci_execute($stid);

//echo "<table border='1'>\n";
//echo "<tr><td>yres_id</td><td>yres_exparrtime</td><td>yres_expdeptime</td></tr>\n";
$counter = 0;
$row_counter=1;
$fidelio_order=array();
$value=''; $fidelio_order['arrTime']=$fidelio_order['departTime']=$fidelio_order['expired']=$fidelio_order['order_id']=$fidelio_order['already']=0;
while ($row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS)) {
	if ($debug) print_r($row);
    //echo "<tr>\n";
	//echo "    <td>" . $row_counter . "    </td> \n";
    foreach ($row as $key => $item) {
		$value = $item;
		if ($key == 'YRES_ID') {
			$fidelio_order['order_id']=$value; 
			if(isset($unexpired[$value])) {
				$fidelio_order['already']=1; 
				if ($debug) echo "Already in table" . $newLine;
			}
			$query_already=" select * from dehotel_wifi where order_num = '".$item."'";
			$result_take_twins = $conn_mysql->query($query_already);
			$rows_take_twins = $result_take_twins->num_rows;
			if ($debug) echo $rows_take_twins .' - number of twins.'. $newLine;
			if ($rows_take_twins != 0) { if ($debug) echo 'Sorry man, but you have a twin.'.$newLine; $fidelio_order['already']=1;} 
		}
		if ($key == 'YRES_EXPARRTIME') {$value = strtotime($item); $fidelio_order['arrTime']=$value;}
		if ($key == 'YRES_EXPDEPTIME') {$value = strtotime($item); $fidelio_order['departTime']=$value; $fidelio_order['expired']=$value - $fidelio_order['arrTime'];}
       // echo "    <td>" . ($value !== null ? htmlentities($value, ENT_QUOTES) : "&nbsp;") . "</td>\n";
    }
	
	if (!$fidelio_order['already']) {
	$response = file_get_contents('http://api.wifisystem.ru/api.json?key='.$wifi_api_key.'&voucher_type=1&amount='. ((round( $fidelio_order['expired']/60/60 + 16 )) * 3) .'&speed=3&expire='.round( (round( $fidelio_order['expired']/60/60 + 16 )) / 24 ).'&data='.$fidelio_order['order_id']);
	$response = json_decode($response);
	//echo '<pre>';
	//print_r($response);
	//echo '</pre>';
	
	$stid_up = oci_parse($conn, "update yrcf set yrcf_wifilogin = '". $response->username ."', yrcf_wifipass = '". $response->password ."' where yrcf_yres_id = ".$fidelio_order['order_id']);
	oci_execute($stid_up);
	
	$sql_add = 	"INSERT INTO dehotel_wifi (ID, order_num, date_arrival, date_departure, login, password, expired, created)".
				" VALUES (NULL, '".$fidelio_order['order_id']."', '".$fidelio_order['arrTime']."', '".$fidelio_order['departTime']."', '". $response->username ."','". $response->password ."','0', CURRENT_TIMESTAMP)";
	if ($conn_mysql->query($sql_add) === true) echo "Add to DB. Ok!" . $newLine;
	}
	$row_counter++;
   // echo "</tr>\n";
	$fidelio_order['arrTime']=$fidelio_order['departTime']=$fidelio_order['expired']=$fidelio_order['order_id']=$fidelio_order['already']=0;
	$counter++;
	//if ($counter > 19) break;
}
//echo "</table>\n";
