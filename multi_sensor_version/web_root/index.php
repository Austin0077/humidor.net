<?php  
    include("functions.php");
    include("pChart/pData.class");  
     include("pChart/pChart.class");  
	 
    $limit         = (int)$_GET['limit'];
	$offset        = (int)$_GET['offset'];
	$clientid      = (int)$_GET['id'];
	$force         = (int)$_GET['force'];
		
	$lastID        = 0;
	$cache_enabled = 1;
	
	if($force==1) $cache_enabled=0;
	
    if($limit < 1 || $limit > 13000) $limit = $default_limit;
    if($offset < 1) $offset = 0;
    
    ConnectDB();
    
    $userr = mysql_query("select * from humiusers where autoid=$clientid");
    if(mysql_num_rows($userr)==0){ //list available users..
	    $r = mysql_query("select * from humiusers");
	    if(mysql_num_rows($r)==0) die("Log into <a href=admin.php>control panel</a> and create users..");    
	    $report = "<b><br><br>Please select a user: <br><ul>\n";
	    while($rr = mysql_fetch_assoc($r)){
		     $report .= "<li><a href=index.php?id=".$rr['autoid'].">".$rr['username']."</a>\n";
	    }
	    die($report);
    }
    
    $user = mysql_fetch_assoc($userr);
    $last_report  = "./reports/lastReport_$clientid.html";
    $image_path   = "./reports/graph_$clientid_";
    $humi_img     = "./images/".$user['img'];
    $GRAPH_TITLE  = $user['username']." Humidor";
    $scnt         = (int)$user['scnt'];
	
	 
    /* limit graph re-generation to only when new data available (unless special req)*/
    $isStdReport = ($limit == $default_limit && $offset==0) ? 1 : 0;
    if($isStdReport == 0) $image_path = "./reports/historical_$clientid.png";
    
    if($isStdReport && $cache_enabled){ 
	    $r = mysql_query("select autoid from humidor where clientid=$clientid order by autoid desc limit 1");
	    $rr = mysql_fetch_assoc($r);
	    $lastID = $rr['autoid'];
	    $lastGraphID = (int)$user['lastid'];
	    if($lastID == $lastGraphID && file_exists($image_path) && file_exists($last_report)){
		 	   die( '<html><head><title>Cached results</title></head>'.file_get_contents($last_report) );
	    }
	    mysql_query("update humiusers set lastid=$lastID where clientid=$clientid");
	}
    
    //get last watered time..
    $r = mysql_query("SELECT UNIX_TIMESTAMP(tstamp) as int_tstamp from humidor where watered > 0 and clientid=$clientid order by autoid desc limit 1");
    $rr = mysql_fetch_assoc($r);
    $lastWatered = date("g:i a - D m.d.y",$rr['int_tstamp']);
    
    $r = mysql_query("SELECT UNIX_TIMESTAMP(tstamp) as int_tstamp from humidor where watered > 0 and clientid=$clientid order by autoid desc limit 10");
    $cnt = mysql_num_rows($r);
    if($cnt > 0){
	    $waterTable = "<b>Last $cnt Waterings:</b><ul>\r\n";
	    while($rr = mysql_fetch_assoc($r)){
		    $waterTable.="<li>".date("g:i a - D m.d.y",$rr['int_tstamp'])."\r\n";
	    }
	    $waterTable.="</ul>\r\n";
	}
	
    $r = mysql_query("SELECT UNIX_TIMESTAMP(tstamp) as int_tstamp from humidor where smoked > 0 and clientid=$clientid order by autoid desc limit 20");
    $cnt = mysql_num_rows($r);
    if($cnt > 0){
	    $smokedTable = "<b>Last $cnt Smokes:</b><ul>\r\n";
	    while($rr = mysql_fetch_assoc($r)){
		    $smokedTable.="<li>".date("g:i a - D m.d.y",$rr['int_tstamp'])."\r\n";
	    }
	    $smokedTable.="</ul>\r\n";
	}
	    
    $r = mysql_query("SELECT UNIX_TIMESTAMP(tstamp) as int_tstamp from humidor where powerevt > 0 and clientid=$clientid order by autoid desc limit 1");
    $rr = mysql_fetch_assoc($r);
    $lastPowerEvent = date("g:i a - D m.d.y",$rr['int_tstamp']);
    
    $r = mysql_query("SELECT UNIX_TIMESTAMP(tstamp) as int_tstamp from humidor where clientid=$clientid order by autoid desc limit 1");
    $rr = mysql_fetch_assoc($r);
    $lastUpdate = date("g:i a - D m.d.y",$rr['int_tstamp']);
    
	$report="
	<html>  
	    	<head>
	    	<title>Humidor Temperature/Humidity Log</title>
		    <style type='text/css'>
			    .bb td, .bb th {
			     border-bottom: 1px solid black !important;
			    }
			    TABLE{ font-family: verdana; font-size:10px;}
			</style>
			<script>
				function doChange(limit){
					location = 'index.php?limit='+limit+'&offset=$offset&id=$clientid'
				}
			</script>
			</head>
	    	<center>
	    	<table border=1 bordercolor='#003300' cellspacing=0 cellpadding=6>
	    		<tr>
	    			<td rowspan=2 valign=top>
	    				<img src='$humi_img'>
	    				<div style='position:relative;top:10px;right:-20px'>
	    				Interval: &nbsp; &nbsp;
	    				<select name=timeSpan id=timeSpan onchange='doChange(this.value)'>
		    				<option value=$one_day>Day</option>
		    				<option ".($limit==($one_day * 3) ? "SELECTED" : "")." value=".($one_day * 3).">3 day</option>
		    				<option ".($limit==($one_day * 7) ? "SELECTED" : "")." value=".($one_day * 7).">Week</option>
		    				<option ".($limit==($one_day * 30) ? "SELECTED" : "")." value=".($one_day * 30).">Month</option>
		    				<!--option ".($limit==($one_day * 90) ? "SELECTED" : "")." value=".($one_day * 90).">3 Month</option-->
		    				<!--option ".($limit==($one_day * 3) ? "SELECTED" : "")." value=".($one_day * 180).">6 Month</option-->
	    				</select>
	    				</div>
	    				<br>
	    			</td>
	    			<td><!--img src='$image_path'--></td>
	    		</tr>
	    		<tr>
	    			<td align=left>
		";
		
		//echo "<h1>$scnt</h1>";
		for($i=0;$i<$scnt;$i++){
			$report.=generate_report($i)."<br><br>";
		}
		
		$report.="
				<hr>
				<table>
					<tr>
    					<td>Last Watered: </td>
    					<td>$lastWatered</td>
    				 
    					<td width=60 &nbsp; </td>
    					
    					<td width=120 align=left>Records: </td>
    					<td>$record_cnt</td>
    				</tr>
    				
    				<tr>
    					<td>Last Update: </td>
    					<td>$lastUpdate</td>
    				 
    					<td width=60 &nbsp; </td>
    					
    					<td>Last Reboot: </td>
    					<td>$lastPowerEvent</td>
    				</tr>
				</table>
				</td>
		    	</tr>
		    	<tr>
		    		<td colspan=2>
		    			<table><tr>
		    				<td width=185> &nbsp; </td>
		    				<td width=320>$waterTable</td>
		    				<td>$smokedTable</td>
		    			</tr></table>
		    		</td>
		    	</tr>
		    </table>
		    </center>
		    <br><br>
	    ";

	echo $report;
	
	function generate_report($sid){
		global $image_path,$clientid,$limit,$offset,$GRAPH_TITLE, $record_cnt;
	
		//now we build the temp/humidity value arrays from the database
	    $r = mysql_query("select * from humidor where clientid=$clientid and sid=$sid order by autoid desc limit $limit offset $offset");
		
	    $i=0;
	    $lowest  = 200;
	    $highest = 0;
	    $avgTemp = 0;
	    $avgHumi = 0;
	    $records = 0;
	    
	    $highTemp= 0;
	    $highHumi= 0;
	    $lowTemp = 200;
	    $lowHumi = 200;
	    
		while($rr = mysql_fetch_assoc($r)){
			
			$h[$i] = $rr['humidity'];
			$t[$i] = $rr['temp'];
			
			$avgTemp += $t[$i];
			$avgHumi += $h[$i];
			
			if($h[$i] < $lowHumi) $lowHumi = $h[$i];
			if($t[$i] < $lowTemp) $lowTemp = $t[$i];
			if($h[$i] > $highHumi ) $highHumi = $h[$i];
			if($t[$i] > $highTemp)  $highTemp = $t[$i];
			
			if($h[$i] < $lowest) $lowest = $h[$i];
			if($t[$i] < $lowest) $lowest = $t[$i];
			if($h[$i] > $highest) $highest = $h[$i];
			if($t[$i] > $highest) $highest = $t[$i];
			
			
			$i++;
		}
		
		$record_cnt = $i;
		$avgTemp = round($avgTemp / $record_cnt,1);
		$avgHumi = round($avgHumi / $record_cnt,1);
		
		//reverse because we ordered desc in sql..
		$t = array_reverse($t); 
	    $h = array_reverse($h);
		$myimg=$image_path.$sid.".png";
	    generateGraph($myimg,$t, $h, $lowest, $highest, $GRAPH_TITLE, $record_cnt);  	 
		
	    $one_day = 3 * 24;
	    
	    $report = " 
	    	
		    			<table>
							<tr><td colspan=5><img src='$myimg'></td></tr>
		    				<tr class=bb>
		    					<td width=120 align=left><font style='font-size:20px;color:blue'>Humidity:</font></td>
		    					<td><font style='font-size:20px;color:blue'> &nbsp; $h[0]</font></td>
		    				
		    					<td width=60 &nbsp; </td>
		    					
		    					<td><font style='font-size:20px;color:blue'>Temperature:</font></td>
		    					<td><font style='font-size:20px;color:blue'> &nbsp; $t[0]</font></td>
		    				</tr>
		    				
		    				<tr>
		    					<td>Hum low/high</td>
		    					<td>$lowHumi &nbsp; / &nbsp; $highHumi</td>
		    				
		    					<td width=60 &nbsp; </td>
		    					
		    					<td>Temp low/high</td>
		    					<td>$lowTemp &nbsp; / &nbsp; $highTemp</td>
		    				</tr>
		    				
		    				<tr>
		    					<td>Avg Humidity: </td>
		    					<td>$avgHumi</td>
		    				
		    					<td width=60 &nbsp; </td>
		    					
		    					<td>Avg Temperature: </td>
		    					<td>$avgTemp</td>
		    				</tr>
		    				<!--tr><td></td><td></td></tr-->
		    			</table>";
	    
	    return $report;
    }
	
	
    if($isStdReport) file_put_contents($last_report, $report);
    
    if( $user['alertsent']==1 ){ //we dont want the alert to get cached..
	    echo "
	    	<script>
	    		function clear_alert(){
		    		var key = prompt('Enter the apikey to clear the alert:');
		    		if(key.length==0) return;
		    		window.open('logData.php?clear_alert=1&clientid=$clientid&apikey='+key, '', 'width=200, height=100');
	    		}
	    	</script>
	    	<center><font color=red size=+4><u><a onmouseover='this.style.cursor=\"pointer\"' onclick='clear_alert()'>Clear Alert</a></u></font></center>	    
	    ";
    }
     
 
     
function generateGraph($output_file,$t, $h, $lowest, $highest, $GRAPH_TITLE, $record_cnt){

	
	
	$glow = $lowest-2;
	$ghi =  $highest+4;
	while( ($ghi-$glow) % 5 != 0) $ghi--; //this keeps y scale as whole numbers try small side first..
	
	if($ghi==$highest){ //we dont want highest to be at top of graph..
		$ghi = $highest+1;
		while( ($ghi-$glow) % 5 != 0) $ghi++;
	}
	 
	//should we filter the arrays since they may contain thousands of points all the same?
	//the charts are kind of looking like flat lines here..maybe charting is dumb :)
	
	//now we generate the graph 
     
      
     // Dataset definition   
     $DataSet = new pData;  
     //$DataSet->AddPoint(array(1,4,3,4,3,3,2,1,0,7,4,3,2,3,3,5,1,0),"Temperature");  
     //$DataSet->AddPoint(array(1,4,2,6,2,3,0,1,5,1,2,4,5,2,1,0,6,4),"Humidity");  
     $DataSet->AddPoint($h,"Humidity"); 
     $DataSet->AddPoint($t,"Temperature");
     $DataSet->AddAllSeries();  
     $DataSet->SetAbsciseLabelSerie();  
     $DataSet->SetSerieName("Humidity","Humidity"); 
     $DataSet->SetSerieName("Temperature","Temperature");  
      
     // Initialise the graph  
     $Test = new pChart(700,230);  
     $Test->setFixedScale($glow, $ghi);
     $Test->setFontProperties("tahoma.ttf",8);  
     $Test->setGraphArea(50,30,585,200);  
     $Test->drawFilledRoundedRectangle(7,7,693,223,5,240,240,240);  
     $Test->drawRoundedRectangle(5,5,695,225,5,230,230,230);  
     $Test->drawGraphArea(255,255,255,TRUE);  
     $Test->drawScale($DataSet->GetData(),$DataSet->GetDataDescription(),SCALE_NORMAL,150,150,150,TRUE,0,2);     
     $Test->drawGrid(4,TRUE,230,230,230,50);  
      
     // Draw the 0 line  
     $Test->setFontProperties("tahoma.ttf",6);  
     $Test->drawTreshold(0,143,55,72,TRUE,TRUE);  //drawTreshold($Value,$R,$G,$B,$ShowLabel=FALSE,$ShowOnRight=FALSE,$TickWidth=4,$FreeText=NULL)
      
     // Draw the cubic curve graph  
     $Test->drawCubicCurve($DataSet->GetData(),$DataSet->GetDataDescription());  
      
     // Finish the graph  
     $Test->setFontProperties("tahoma.ttf",8);  
     $Test->drawLegend(600,30,$DataSet->GetDataDescription(),255,255,255);  
     $Test->setFontProperties("tahoma.ttf",10);  
     $Test->drawTitle(50,22,$GRAPH_TITLE . date(' - m.d.y') ,50,50,50,585);  
     
     $Test->Render($output_file);
	
}        
    
 
?>