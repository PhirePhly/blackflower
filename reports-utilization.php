<?php

define('FPDF_FONTPATH','font/');
require('fpdf.php');
require_once('db-open.php');
include('local-dls.php');
require_once('session.inc');
include('functions.php');

ini_set('display_errors',1); 
error_reporting(E_ALL);

$subsys="reports";

if (!CheckAuthByLevel('reports', $_SESSION["access_level"])) {
  include('include-title.php');
  header_html('Dispatch :: Access Restricted');
  print "Access level too low to access Reports page.";
  exit;
}

$filter_set_name = MysqlClean($_GET, "filterset", 80);
$incident_types_selector = MysqlClean($_GET, "incidenttypes", 80);
$startdate = MysqlClean($_GET,"startdate",20);
$enddate = MysqlClean($_GET,"enddate",20);
$daterange = $startdate;
//$selected_date='';
if ($startdate != $enddate) {
  $daterange .= " - $enddate";
}
//else {
  //$selected_date = $startdate;
//}

syslog(LOG_DEBUG, "At header of utilization report, date range is [$daterange]");

$call_types = "";

$overall_times = array();
$overall_numcalls = array();

if (array_key_exists('typesselected', $_GET) && sizeof($_GET['typesselected']) > 0) {
  foreach ($_GET['typesselected'] as $type) {
    if ($call_types == '') {
      $call_types = "call_type IN (";
    }
    else {
      $call_types .= ", ";
    }

    if (preg_match('/^[a-zA-Z0-9_\- ]+$/', $type)) {
      $call_types .= "'$type'";
    }
  }

  $call_types .= ")";
  syslog(LOG_DEBUG, "Saw types selected, resulting SQL fragment is:  $call_types");
}
elseif ($incident_types_selector == 'filter') {
  include('include-title.php');
  header_html("Dispatch :: Reports");
  print '<body vlink="blue" link="blue" alink="cyan">';

  print '<form name="myform" method="GET" action="reports-utilization.php">';
  print "\n<input type=\"hidden\" name=\"filterset\" value=\"$filter_set_name\"/>\n";
  print "\n<input type=\"hidden\" name=\"incidenttypes\" value=\"$incident_types_selector\"/>\n";
  print "\n<input type=\"hidden\" name=\"startdate\" value=\"$startdate\"/>\n";
  print "\n<input type=\"hidden\" name=\"enddate\" value=\"$enddate\"/>\n";
  
  print '<h3> Select call types for unit utilization report: </h3>';
  $query = "SELECT call_type FROM incident_types ORDER BY call_type ASC";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  while ($line = mysql_fetch_object($result)) {
    print '<div class=text><input type=checkbox name="typesselected[]" value="' . $line->call_type . '">' . $line->call_type. "</div>\n";
  }
  mysql_free_result($result);

  print "\n<input class=\"btn\" type=\"submit\" name=\"unitutilization_report\" value=\"Get Report\"/><p>\n";
  print "\n<input class=\"btn btnatext\" type=\"button\" onclick=\"window.location='reports.php';\" value=\"Return to Reports menu\"/>\n";
  print '</form></body>';
  exit;
}
else {
  $call_types = "call_type != 'TRAINING'";
}


  //SELECT DATE_FORMAT(iu.dispatch_time, '%c/%e') AS dispatch_date, 
$query_daily_base = "
  SELECT DATE(iu.dispatch_time) AS dispatch_date, 
         TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(cleared_time,dispatch_time)))),'%k:%i') AS utiliz, 
         COUNT(*) AS calls 
    FROM incident_units iu JOIN incidents i ON iu.incident_id=i.incident_id 
   WHERE DATE(iu.dispatch_time)>='$startdate' 
     AND DATE(iu.dispatch_time)<='$enddate' 
     AND $call_types ";
//         AND (unit like '%QRV%') 
//        AND iu.unit='QRV 3'
$query_daily_suffix = "
   GROUP BY dispatch_date
";

  //SELECT DATE_FORMAT(iu.dispatch_time, '%c/%e') AS dispatch_date, 
$query_overall_base = "
  SELECT DATE(iu.dispatch_time) AS dispatch_date, 
         TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(cleared_time,dispatch_time)))),'%k:%i') AS utiliz, 
         COUNT(*) AS calls 
    FROM incident_units iu JOIN incidents i ON iu.incident_id=i.incident_id 
   WHERE DATE(dispatch_time)>='$startdate' 
     AND DATE(dispatch_time)<='$enddate' 
     AND $call_types 
     ";
//    and (unit like '%QRV%');

  header('Content-type: application/pdf');

  class PDF extends FPDF
  {
    var $widths;
    var $aligns;
    var $showcriteria;

    function Header()
    {
        global $HEADER_LOGO;
        $this->Image("$HEADER_LOGO",175,8,20);
        $this->SetFillColor(230);

        // top row
        $this->SetY(12);
        $this->SetFont('Arial','B',14);
        $this->Cell(160,5,'Black Rock City ESD - Unit Utilization Report',0,0);

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

    function AutoCell($fontsize, $w, $h, $txt, $border = 0, $align = 'J', $fill = false, $debug = 0)
    {
      $this->SetFontSize($fontsize);
      $predicted_w = round($this->GetStringWidth($txt));
      $fs=$fontsize;
      if (($predicted_w) >= $w) {  // margin - apparently there's a fencepost
        $fs = (int)(($fontsize * ($w/$predicted_w)))-1;
        $this->SetFontSize($fs);
      }
      if ($debug == 1) {
       $txt = "$w:$predicted_w:$fs:$txt";
      }
      $this->Cell($w,$h,$txt,$border,0,$align,$fill);
      $this->SetFontSize($fontsize);
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

  }

  // End subclass definition
  // Begin main program


    syslog(LOG_INFO, $_SESSION['username'] . " generated units utilization report");
    syslog(LOG_DEBUG, " -- beginning report generation.");


    $filter_regexps = array();
    $all_dates = array();

    if ($filter_set_name == "") {
      $filter_regexps["All Units"] = ".*";
    }
    else {
      $filter_query = "SELECT * FROM unit_filter_sets WHERE filter_set_name='$filter_set_name'";
      $result = mysql_query($filter_query) or die("In query: $filter_query<br>\nError: ".mysql_error());
      while ($line = mysql_fetch_object($result)) {
        $filter_regexps[$line->row_description] = $line->row_regexp;
      }
      mysql_free_result($result);
    }
    // TODO: error check empty filter set if passed parameter was bad
    $filter_regexp = join('|', array_values($filter_regexps));
    syslog(LOG_DEBUG, " -- unit filter regexp is [$filter_regexp]");
    

    $units = array();
    $units_query = "SELECT DISTINCT unit FROM incident_units WHERE unit REGEXP '$filter_regexp' ORDER BY unit";
    $result = mysql_query($units_query);
    if (!$result) {
      syslog(LOG_WARNING, " dying: In query: $units_query --- Error: ".mysql_error());
      die("In query: $units_query<br>\nError: ".mysql_error());
    }
    while ($unit_row = mysql_fetch_object($result)) {
      array_push($units, $unit_row->unit);
    }
    mysql_free_result($result);

    foreach ($units as $unit) {
      $query = $query_daily_base . " AND unit = '$unit' " . $query_daily_suffix;
      $result = mysql_query($query);
      if (!$result) {
        syslog(LOG_WARNING, " dying: In query: $query --- Error: ".mysql_error());
        die("In query: $query<br>\nError: ".mysql_error());
      }
      syslog(LOG_DEBUG, " -- queried daily times for unit [$unit].");
      //TODO: error check
      while ($days = mysql_fetch_object($result)) {
        $all_dates[$days->dispatch_date] = 1;
        $utilization_times[$unit][$days->dispatch_date] = $days->utiliz;
        $utitization_numcalls[$unit][$days->dispatch_date] = $days->calls;
      }
      mysql_free_result($result);

      $query = $query_overall_base . " AND unit = '$unit' ";
      $result = mysql_query($query);
      if (!$result) {
        syslog(LOG_WARNING, " dying: In query: $query --- Error: ".mysql_error());
        die("In query: $query<br>\nError: ".mysql_error());
      }
      syslog(LOG_DEBUG, " -- queried daily times for unit [$unit].");
      $overalls = mysql_fetch_object($result);
      //TODO: error check
      syslog(LOG_DEBUG, " -- queried overall times for set [$unit].");
      $utilization_times[$unit]["total"] = $overalls->utiliz;
      $utilization_numcalls[$unit]["total"] = $overalls->calls;
      mysql_free_result($result);

      syslog(LOG_DEBUG, " -- processed unit [$unit], overall time ".$utilization_times[$unit]["total"].", calls (".$utilization_numcalls[$unit]["total"].")");
     
    }

    $all_dates = array_keys($all_dates);
    sort($all_dates);
    foreach ($units as $unit) {
      foreach ($all_dates as $dispatch_date) {
        if (!array_key_exists($dispatch_date, $utilization_times[$unit])) {
          $utilization_times[$unit][$dispatch_date] = '--:--';
          $utilization_numcalls[$unit][$dispatch_date] = '---';
        }
      }
    }
    syslog(LOG_DEBUG, " -- closed database connection.");
    mysql_close($link);

    syslog(LOG_DEBUG, " -- starting PDF generation.");

    $pdf=new PDF();
    $pdf->SetFont('Arial','',10);
    $pdf->Open();
    $pdf->AliasNbPages();

    $pdf->AddPage('P','Letter');
    $pdf->SetDrawColor(64);
    $pdf->SetFont('Arial','B',12);
    $pdf->Ln(6);
    $pdf->Cell(50, 6, 'Report for dates: ');
    $pdf->Cell(100, 6, $daterange);
    $pdf->Ln(6);

    $pdf->Cell(50, 6, 'For incident types: ');
    if ($incident_types_selector == 'all') {
      $pdf->Cell(50, 6, 'All types (except TRAINING)');
    }
    else {
      $pdf->AutoCell(12, 100, 5, join(', ', $_GET['incident_types_selector']), 0, 'L');
    }
    $pdf->SetFont('Arial','B',12);

    //$pdf->SetWidths($widths);
    $pdf->Ln(12);

    $pdf->Cell(45,6, '');
    $pdf->Cell(80,6, 'Unit Daily Utilizations (in hh:mm, number of assigned calls that day shown in parentheses)');
    $pdf->Ln(6);

    $pdf->Cell(45,6, '');
    foreach ($all_dates as $display_date) {
      $pdf->AutoCell(12, 30, 6, $display_date, 1);  
    }
    $pdf->Cell(45,6, 'Total:');
    $pdf->Ln(7);

    foreach ($units as $unit) {
      $pdf->AutoCell(12, 30, 5, $unit, 1, 'C');
    
      foreach ($all_dates as $display_date) {
        $pdf->SetFont('Arial','B',12);
        
        // 2015-08-23 hack for now:
        
        if (!isset($utilization_times[$unit][$display_date])) {
          $utilization_times[$unit][$display_date] = 0;
        }
        if (!isset($utilization_numcalls[$unit][$display_date])) {
          $utilization_numcalls[$unit][$display_date] = 0;
        }
        if (!isset($overall_times[$display_date])) {
          $overall_times[$display_date] = 0;
        }
        if (!isset($overall_numcalls[$display_date])) {
          $overall_numcalls[$display_date] = 0;
        }

        $pdf->Cell(40,5,  $utilization_times[$unit][$display_date], 0, 0, 'C');
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(40,5,  $utilization_numcalls[$unit][$display_date], 0, 0, 'C');
      }

      $pdf->Cell(40,5, $overall_times[$display_date], 0, 0, 'C');
      $pdf->Cell(40,5, $overall_numcalls[$display_date], 0, 0, 'C');

      $pdf->Ln(5);
    }

    
    syslog(LOG_DEBUG, " -- setting PDF generation.");
    $pdf->SetDisplayMode('fullpage','single');
    
    $pdf->Output("CAD Unit Utilization Report $daterange.pdf",'D');
    syslog(LOG_DEBUG, " -- finished writing the pdf report.");
  
?>
