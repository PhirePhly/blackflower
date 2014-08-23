<?php

define('FPDF_FONTPATH','font/');
require('fpdf.php');
require_once('db-open.php');
include('local-dls.php');
include('functions.php');
require_once('session.inc');


$subsys="reports";

if (!CheckAuthByLevel('reports', $_SESSION["access_level"])) {
  header_html('Dispatch :: Access Restricted');
  include('include-title.php');
  print "Access level too low to access Reports page.";
  exit;
}


$where_clause = '';
$filter_description = '';
$typefilter = '';
$toc_page = 1;
$daterange = '';
$call_num = MysqlClean($_GET, "call_number", 40);

if ($call_num != '') {
  $toc_page = 0;
  $where_clause = "WHERE call_number = '$call_num' "; 
  // TODO: this will break if not using dateformat/baseindex call numbers, right?
}

else {
  $itype = MysqlClean($_GET, "selected-type", 40);
  if ($_GET['selected-type'] == 'TRAINING') {
    $_GET['hidetraining'] = 0;
  }
  $startdate = MysqlClean($_GET,"startdate",20);
  $enddate = MysqlClean($_GET,"enddate",20);
  $daterange = $startdate;
  $selected_date='';
  if ($startdate != $enddate) {
    $daterange .= " - $enddate";
  }
  else {
    $selected_date = $startdate;
  }
  
  if ($itype != '') { 
    $where_clause = " WHERE call_type = '$itype' AND ";
    $filter_description = "Showing only calls of type '$itype'.";
    $typefilter = " - $itype -";
  }
  elseif (isset($_GET['hidetraining'])) {
    $where_clause = " WHERE call_type != 'TRAINING' AND ";
    $filter_description = 'Calls of type "TRAINING" are hidden.';
  }
  else {
    $where_clause = " WHERE ";
    $typefilter = ' - Unfiltered -';
    // Filter noop, use empty strings
  }

  $where_clause .= " ts_opened >= '$startdate' AND ts_opened <= '$enddate 23:59:59' ";
}



if(isset($_GET["confirmed"])) {
  $opencount = 0;
} else {
  $openquery = "SELECT call_number, call_type, call_details, TIME(ts_opened) as open_time FROM incidents $where_clause  AND incident_status = 'Open' AND disposition != 'Duplicate' ORDER BY incident_id DESC";
  // TODO 1.10.x: Isn't "disposition != Duplicate" redundant here?
  $openresult = mysql_query($openquery) or die("In query: $openquery<br>\nError: ".mysql_error());
  $opencount = mysql_num_rows($openresult);
}

if($opencount > 0) {

  require_once('functions.php');

  header_html('Confirm Incidents Report');
  
  $mode = $_GET["mode"];
  $always_pagebreak = $_GET["always-pagebreak"];

  ?>
  
  <body vlink="blue" link="blue" alink="cyan">
  <?php
    include('include-title.php');
  ?>
  
  <div class="h1"><u>Confirm Incidents Report</u></div>

  <div style="margin:1em">
  
    <div>  
    Warning, <?php print "$opencount" ?> incident(s) created on <?php $enddate ?> are still 
    open, and the details written into the report for these incident(s) will be incomplete:
    </div>
    
    <table cellpadding="1" cellspacing="1" bgcolor="#aaaaaa" style="border: solid 1px #aaaaaa; margin:1em">
      <tr>
        <td class="th">Call No.</td>
        <td class="th">Incident Details</td>
        <td class="th">Call Type</td>
        <td class="th">Open Time</td>
      </tr>
    <?php
      while ($open = mysql_fetch_object($openresult)) {
        ?>
        <tr>
          <td class="message-iself"><?php print "$open->call_number" ?></td>
          <td class="message-iself"><?php print str_replace(" ", "&nbsp;",  substr($open->call_details,0,40)) ?></td>
          <td class="message-iself"><?php print "$open->call_type" ?></td>
          <td class="message-iself"><?php print "$open->open_time" ?></td>
        </tr>
        <?php
      }
    ?>
    </table>

    <div>
    Do you still want to write this report?
    </div>
    
    <div style="margin:1em">
    
      <form name="myform" method="GET" action="reports-incidents.php" style="display:inline">
        <input type="submit" name="confirmed" value="Yes, Get PDF Incidents Report" />
        <input type="hidden" name="startdate" value="<?php print $startdate ?>" />
        <input type="hidden" name="enddate" value="<?php print $enddate ?>" />
        <input type="hidden" name="mode" value="<?php print $mode ?>" />
        <input type="hidden" name="always-pagebreak" value="<?php print $always_pagebreak ?>" />
      </form>
      
      <form name="myform" method="GET" action="reports.php" style="display:inline">
        <input type="submit" name="confirmed" value="No, Return to Reports" />
      </form>
    
    </div>
    
  <div>
  
  </body>
  
  </html>
  
  <?php

} else {

  header('Content-type: application/pdf');

  class PDF extends FPDF
  {
    var $widths;
    var $aligns;
    var $showcriteria;

    function SetReportsCriteria($date, $call_number) {
      if ($date != '')
        $this->showcriteria = "For date(s): " . $date;
      elseif ($call_number != '')
        $this->showcriteria = "For incident #: " . $call_number;
      else
        $this->showcriteria = "For all incidents in database. ";
    }

    function Header()
    {
        global $HEADER_LOGO;
        $this->Image("$HEADER_LOGO",175,8,20);
        $this->SetFillColor(230);

        // top row
        $this->SetY(12);
        $this->SetFont('Arial','B',14);
        $this->Cell(160,5,'Black Rock City ESD - Incidents Report',0,0);

        $this->SetFont('Arial','',12);
        // bottom row
        $this->SetY(19);
        $this->Cell(80,5,'Report written at: '.NOW,0,0,'L');
        $this->Cell(80,5,$this->showcriteria,0,0,'L');
        $this->Ln(8);
    }

    //Page footer
    function Footer()
    {
        //Position at 1.5 cm from bottom
        $this->SetY(-15);
        //Arial italic 8
        $this->SetFont('Arial','',8);
        //Page number
        $this->Cell(0,10,'Page '.$this->PageNo().' of {nb}',0,0,'C');

    }


    function SetWidths($w)
    {
        //Set the array of column widths
        $this->widths=$w;
    }

    function SetAligns($a)
    {
        //Set the array of column alignments
        $this->aligns=$a;
    }

    function Row($data, $margin = 0)
    {
        //Calculate the height of the row
        $nb=0;
        for($i=0;$i<count($data);$i++)
            $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        $h=5*$nb;
        //Issue a page break first if needed
        $this->CheckPageBreak($h);
        if($margin != 0) {
          $this->Cell($margin,5);
        }
        //Draw the cells of the row
        for($i=0;$i<count($data);$i++)
        {
            $w=$this->widths[$i];
            $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            //Save the current position
            $x=$this->GetX();
            $y=$this->GetY();
            //Draw the border
            $this->Rect($x,$y,$w,$h);
            //Print the text
            $this->MultiCell($w,5,$data[$i],0,$a);
            //Put the position to the right of the cell
            $this->SetXY($x+$w,$y);
        }
        //Go to the next line
        $this->Ln($h);
    }

    function CheckPageBreak($h)
    {
        //If the height h would cause an overflow, add a new page immediately
        if($this->GetY()+$h>$this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w,$txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
            $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb)
        {
            $c=$s[$i];
            if($c=="\n")
            {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
            $l+=$cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i=$sep+1;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }

    function DLSColumnHeader() {
      $this->SetY(30);
      $this->SetFont('Arial','B',10);
      $this->Cell(19,5,'Call No.', 1,0);
      $this->Cell(40,5,'Call Type',1,0);
      $this->Cell(90,5,'Details',1,0);
      $this->Cell(21,5,'Opened',1,0);
      $this->Cell(21,5,'Closed',1,0);
      $this->Ln(7);
    }

    function StatsColumnHeader() {
      $this->SetY(38);
      $this->SetFont('Arial','B',10);
      $this->Cell(50,5,'Call Type',1,0);
      $this->Cell(25,5,'No. Incidents', 1,0);
      $this->Ln(7);
    }
  }

  // End subclass definition
  // Begin main program

  #if (isset($_GET["mode"]) && $_GET["mode"] == "report-by-date") {
    
    $pdf=new PDF();
    $pdf->SetReportsCriteria($daterange, $call_num);
    $pdf->SetFont('Arial','',10);
    $pdf->Open();
    $pdf->AliasNbPages();

    $pdf->AddPage('');
    $pdf->SetWidths(array(50,25));
    $pdf->SetDrawColor(64);
    //
    if ($filter_description != '') {
      $pdf->Cell(80,5,"Filter applied: $filter_description",0,0,'L');
      $pdf->Ln(8);
    }

    syslog(LOG_INFO, $_SESSION['username'] . " generated incidents report");
    if ($DEBUG) syslog(LOG_INFO, " -- beginning report generation.");


    if ($toc_page) {
    $query = "SELECT * FROM incident_types ";
    $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

    $pdf->StatsColumnHeader();
    $totalincidents = 0;
    while ($line = mysql_fetch_object($result)) {
      $query = "SELECT COUNT(*) AS subtotal FROM incidents $where_clause  AND incident_status != 'New' AND call_type='".$line->call_type."'";
      $subresult = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
      $subline = mysql_fetch_object($subresult);
      $totalincidents = $totalincidents + $subline->subtotal;
      if ((isset($_GET['selected-type']) && $_GET['selected-type'] != '' && $_GET['selected-type'] != $line->call_type) || 
          ($line->call_type == 'TRAINING' && isset($_GET['hidetraining']))) {
        $pdf->Row(array($line->call_type, ' (filtered)'));
      }
      else {
        $pdf->Row(array($line->call_type, $subline->subtotal));
      }
      mysql_free_result($subresult);
    }
    mysql_free_result($result);
    $pdf->Ln(7);
    $pdf->Row(array('TOTAL', $totalincidents));


    $pdf->AddPage('');
    $pdf->SetWidths(array(19,40,90,21,21));
    
    // preload times -- for all incidents since $where_clause is highly variable
    
    $dispatch_times = array();
    $arrival_times = array();
    $times = MysqlQuery("SELECT incident_id, MIN(iu.dispatch_time) as dispatch_time, MIN(iu.arrival_time) as arrival_time FROM incident_units iu GROUP BY iu.incident_id");
    while ($incident_time = mysql_fetch_array($times, MYSQL_ASSOC)) {
      $dispatch_times[$incident_time["incident_id"]] = $incident_time["dispatch_time"];
      $arrival_times[$incident_time["incident_id"]] = $incident_time["arrival_time"];
    }

    $query = "SELECT * FROM incidents $where_clause  AND incident_status != 'New' AND disposition != 'Duplicate'";

    $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
    if ($DEBUG) syslog(LOG_INFO, " -- selected appropriate incidents.");

    while ($line = mysql_fetch_object($result)) {
      if ($pdf->GetY() < 31) 
        $pdf->DLSColumnHeader();
      //if ($line->ts_opened == "0000-00-00 00:00:00") {
        //$line->ts_opened = "";
      //} else {
        //if($selected_date == substr($line->ts_opened, 0, 10)) {
          //$line->ts_opened = substr($line->ts_opened, 11);
        //} else {
          //$line->ts_opened = substr($line->ts_opened, 11) . ' ' . substr($line->ts_opened, 0, 10);
        //}
      //}
      //if ($line->ts_complete == "0000-00-00 00:00:00") {
        //$line->ts_complete = "";
      //} else {
        //if($selected_date == substr($line->ts_complete, 0, 10)) {
          //$line->ts_complete = substr($line->ts_complete, 11);
        //} else {
          //$line->ts_complete = substr($line->ts_complete, 11) . ' ' . substr($line->ts_complete, 0, 10);
        //} 
      //}
      if ($line->call_number != '') {
        $pdf->Row(array($line->call_number, $line->call_type, $line->call_details, dls_mdhmtime($line->ts_opened), dls_mdhmtime($line->ts_complete)));
      } else {
        $pdf->Row(array("incident #" . $line->incident_id, $line->call_type, $line->call_details, dls_mdhmtime($line->ts_opened), dls_mdhmtime($line->ts_complete)));
      }
    }
    mysql_free_result($result);
}
    if ($pdf->GetY() > 31)
      $pdf->AddPage('');

    $pdf->SetFillColor(230);
    $pdf->SetDrawColor(64);
    
    // TODO: don't repeat this query needlessly
    $query = "SELECT * FROM incidents $where_clause  AND incident_status != 'New' AND disposition != 'Duplicate'";
    $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
    $numrows = mysql_num_rows($result);
    $thisrow=0;
    while ($line = mysql_fetch_object($result)) {
      $thisrow++;
    
      //if ($line->ts_opened == "0000-00-00 00:00:00") {
        //$line->ts_opened = "";
      //} else {
        //if($selected_date == substr($line->ts_opened, 0, 10)) {
          //$line->ts_opened = substr($line->ts_opened, 11);
        //} else {
          //$line->ts_opened = substr($line->ts_opened, 11) . ' ' . substr($line->ts_opened, 0, 10);
        //} 
      //}
        //
      //if ($line->ts_dispatch == "0000-00-00 00:00:00") {
        //$line->ts_dispatch = "";
      //} else {
        //if($selected_date == substr($line->ts_dispatch, 0, 10)) {
          //$line->ts_dispatch = substr($line->ts_dispatch, 11);
        //} else {
          //$line->ts_dispatch = substr($line->ts_dispatch, 11) . ' ' . substr($line->ts_dispatch, 0, 10);
        //} 
      //}
      //
      //if ($line->ts_arrival == "0000-00-00 00:00:00") {
        //$line->ts_arrival = "";
      //} else {
        //if($selected_date == substr($line->ts_arrival, 0, 10)) {
          //$line->ts_arrival = substr($line->ts_arrival, 11);
        //} else {
          //$line->ts_arrival = substr($line->ts_arrival, 11) . ' ' . substr($line->ts_arrival, 0, 10);
        //} 
      //}  
      //
      //if ($line->ts_complete == "0000-00-00 00:00:00") {
        //$line->ts_complete = "";
      //} else {
        //if($selected_date == substr($line->ts_complete, 0, 10)) {
          //$line->ts_complete = substr($line->ts_complete, 11);
        //} else {
          //$line->ts_complete = substr($line->ts_complete, 11) . ' ' . substr($line->ts_complete, 0, 10);
        //} 
      //}  
    
      $pdf->Ln(2);
      $pdf->SetFont('Arial','B',14);
      if ($line->call_number != '') {
        $pdf->Cell(190, 8, 'Call Number: '. $line->call_number, 1, 1, 'L', 1);
      } else {
        $pdf->Cell(190, 8, 'Call (Error: Call Number Not Available - Incident ID# '. $line->incident_id . ')', 1, 1, 'L', 1);
      }
      $pdf->Ln(2);
    
      $thisrow_top = $pdf->GetY();
      $pdf->SetFont('Arial','',10);
      $pdf->Cell(30, 5, "Call Type:", 0, 0, "R");  $pdf->Cell(3); $pdf->Cell(45, 5, $line->call_type, 1, 1);
      $pdf->Cell(30, 5, "Details:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->call_details, 1, 1);
      $pdf->Cell(30, 5, "Location:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->location, 1, 1);
      $pdf->Ln(2);
      $pdf->Cell(30, 5, "Reporting Party:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->reporting_pty, 1, 1);
      $pdf->Cell(30, 5, "Contact At:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->contact_at, 1, 1);
      $thisrow_endhdr = $pdf->GetY();
    
      $pdf->Ln(2);
      $pdf->SetXY(126, $thisrow_top);
      $pdf->Cell(30, 5, "Opened:", 0, 2, "R");
      $pdf->Cell(30, 5, "Dispatched:", 0, 2, "R");
      $pdf->Cell(30, 5, "Unit Arrived:", 0, 2, "R");
      $pdf->Cell(30, 5, "Completed:", 0, 2, "R");
      $pdf->SetXY($pdf->GetX(), $pdf->GetY()+2);
      $pdf->Cell(30, 5, "Disposition:", 0, 2, "R");  
      if ($pdf->GetY() > $thisrow_endhdr) $thisrow_endhdr = $pdf->GetY();
    
      $pdf->SetXY(160, $thisrow_top);
      $pdf->Cell(38, 5, dls_mdhmtime($line->ts_opened), 1,2);
      $pdf->Cell(38, 5, dls_mdhmtime(array_key_exists($line->incident_id, $dispatch_times) ? $dispatch_times[$line->incident_id] : ''), 1,2);
      $pdf->Cell(38, 5, dls_mdhmtime(array_key_exists($line->incident_id, $arrival_times) ? $arrival_times[$line->incident_id] : ''),1,2);
      $pdf->Cell(38, 5, dls_mdhmtime($line->ts_complete), 1,2);
      $pdf->SetXY($pdf->GetX(), $pdf->GetY()+2);
      $pdf->SetFont('Arial','B',10);
      $pdf->Cell(38, 5, $line->disposition, 0, 2);
      $pdf->SetFont('Arial','',10);
    
      $pdf->SetY($thisrow_endhdr+5);
    
      $pdf->SetFont('Arial','B',10);
      $pdf->Cell(30, 5, "Units/Times Assigned to Incident", 0, 1);
      $pdf->SetFont('Arial','',10);
    
      $unitquery = "SELECT * FROM incident_units WHERE incident_id=".$line->incident_id." ORDER BY dispatch_time";
      $unitresult = mysql_query($unitquery) or die("In query: $unitquery<br>\nError: ".mysql_error());
    
      if (mysql_num_rows($unitresult)>0) {
        $pdf->Cell(5,5);
        $pdf->Cell(60,5,"Unit Name");
        $pdf->Cell(21,5,"Dispatched");
        $pdf->Cell(21,5,"On Scene");
        $pdf->Cell(24,5,"Transporting");
        $pdf->Cell(24,5,"At Destination");
        $pdf->Cell(21,5,"Cleared");
        $pdf->Ln(5);
        $pdf->SetWidths(array(60, 21, 21, 24, 24, 21));
        while ($unit = mysql_fetch_object($unitresult)) {
        
					//if($selected_date == substr($unit->dispatch_time, 0, 10)) {
						//$unit->dispatch_time = substr($unit->dispatch_time, 11);
					//}  else {
					  //$unit->dispatch_time = dls_hmmdtime($unit->dispatch_time);
					//}
				
					//if($selected_date == substr($unit->arrival_time, 0, 10)) {
						//$unit->arrival_time = substr($unit->arrival_time, 11);
					//}  else {
					  //$unit->arrival_time = dls_hmmdtime($unit->arrival_time);
					//}
				//
					//if($selected_date == substr($unit->transport_time, 0, 10)) {
						//$unit->transport_time = substr($unit->transport_time, 11);
					//}  else {
					  //$unit->transport_time = dls_hmmdtime($unit->transport_time);
					//}
	//
					//if($selected_date == substr($unit->transportdone_time, 0, 10)) {
						//$unit->transportdone_time = substr($unit->transportdone_time, 11);
					//}  else {
					  //$unit->transportdone_time = dls_hmmdtime($unit->transportdone_time);
					//}
					//
					//if($selected_date == substr($unit->cleared_time, 0, 10)) {
						//$unit->cleared_time = substr($unit->cleared_time, 11);
					//}  else {
					  //$unit->cleared_time = dls_hmmdtime($unit->cleared_time);
					//}
        
          $pdf->Row(
            array(
              $unit->unit, 
              dls_mdhmtime($unit->dispatch_time), 
              dls_mdhmtime($unit->arrival_time),
              dls_mdhmtime($unit->transport_time),
              dls_mdhmtime($unit->transportdone_time),
              dls_mdhmtime($unit->cleared_time)
            ),
            5
          );
            
        }
      }
      else {
        $pdf->Cell(5,5);
        $pdf->Cell(50,5, "- No units were assigned to this incident -");
        $pdf->Ln(5);
      }
      mysql_free_result($unitresult);
    
      $pdf->Ln(5);
      $pdf->SetFont('Arial','B',10);
      $pdf->Cell(30, 5, "Notes Logged For Incident", 0, 1);
      $pdf->SetFont('Arial','',10);
    
      $notequery = "SELECT * FROM incident_notes WHERE incident_id=".$line->incident_id." ORDER BY note_id";
      $noteresult = mysql_query($notequery) or die("In query: $notequery<br>\nError: ".mysql_error());
      if (mysql_num_rows($noteresult) > 0) {
        $pdf->Cell(5,5);
        $pdf->Cell(21,5,"Time");
        $pdf->Cell(25,5,"Noted By");
        $pdf->Cell(30,5,"Unit");
        $pdf->Cell(107,5,"Note");
        $pdf->Ln(5);
        $pdf->SetWidths(array(21,25,30,107));
        while ($note = mysql_fetch_object($noteresult)) {
          //if($selected_date == substr($note->ts, 0, 10)) {
            //$note->ts = substr($note->ts, 11);
          //} else {
            //$note->ts = substr($note->ts, 11) . "\n" . substr($note->ts, 0, 10);
          //}
          $pdf->Row(array(dls_mdhmtime($note->ts), $note->creator, $note->unit, $note->message), 5);
        }
      }
      else {
        $pdf->Cell(5,5);
        $pdf->Cell(50,5, "- No notes logged for this incident -");
        $pdf->Ln(5);
      }
      mysql_free_result($noteresult);
          
      $pdf->Ln(5);
    
      if ($thisrow<$numrows && ($pdf->GetY() > 190 || isset($_GET["always-pagebreak"]))) {
        $pdf->AddPage('');
      }
    }
    $pdf->SetDisplayMode('fullpage','single');
    mysql_free_result($result);
    mysql_close($link);
    
    if ($DEBUG) syslog(LOG_INFO, " -- closed database connection.");
    $pdf->Output("\"CAD Incidents Report$typefilter $daterange.pdf\"",'D');
    if ($DEBUG) syslog(LOG_INFO, " -- output the pdf report.");
  #}
  #else 
  #{
          #die( "selected incidents reports not yet available.");
  #}
  
} 

