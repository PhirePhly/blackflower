<?php

define('FPDF_FONTPATH','font/');
require('fpdf.php');
require_once('db-open.php');
include('local-dls.php');
require_once('session.inc');

$subsys="reports";

  if ($_SESSION["access_level"] < 5) {
    header_html('Dispatch :: Access Restricted');
    include('include-title.php');
    print "Access level too low to access Reports page.";
    exit;
  }

header('Content-type: application/pdf');

class PDF extends FPDF
{
var $widths;
var $aligns;
var $showcriteria;

function SetReportsCriteria($value) {
  if ($value == "") 
    $this->showcriteria = "For [all incidents in database] ";
  else
    $this->showcriteria = "For incidents on: " . date('l', strtotime($value)) . " " . $value;
}

function Header()
{
    $this->Image('Logos/logo-esd.jpg',175,8,20);
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

function Row($data)
{
    //Calculate the height of the row
    $nb=0;
    for($i=0;$i<count($data);$i++)
        $nb=max($nb,$this->NbLines($this->widths[$i],$data[$i]));
    $h=5*$nb;
    //Issue a page break first if needed
    $this->CheckPageBreak($h);
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
  $this->Cell(15,5,'No.', 1,0);
  $this->Cell(35,5,'Call Type',1,0);
  $this->Cell(65,5,'Details',1,0);
  $this->Cell(38,5,'Time Opened',1,0);
  $this->Cell(38,5,'Time Closed',1,0);
  $this->Ln(7);
}

function StatsColumnHeader() {
  $this->SetY(30);
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
  $pdf->SetReportsCriteria($_GET["selected-date"]);
  $pdf->SetFont('Arial','',10);
  $pdf->Open();
  $pdf->AliasNbPages();

  $pdf->AddPage('');
  $pdf->SetWidths(array(50,25));

  $query = "SELECT * FROM incident_types;";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

  $pdf->StatsColumnHeader();
  $totalincidents = 0;
  while ($line = mysql_fetch_object($result)) {
    $query = "SELECT COUNT(*) AS subtotal FROM incidents WHERE ts_opened LIKE '".MysqlClean($_GET, "selected-date", 20)."%' AND (visible = 1 OR completed = 1) AND call_type='".$line->call_type."'";
    $subresult = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
    $subline = mysql_fetch_object($subresult);
    $totalincidents = $totalincidents + $subline->subtotal;
    $pdf->Row(array($line->call_type, $subline->subtotal));
    mysql_free_result($subresult);
  }
  mysql_free_result($result);
  $pdf->Ln(7);
  $pdf->Row(array('TOTAL', $totalincidents));


  $pdf->AddPage('');
  $pdf->SetWidths(array(15,35,65,38,38,38,38));
  
  $query = "SELECT * FROM incidents WHERE ts_opened LIKE '".MysqlClean($_GET, "selected-date", 20)."%' AND (visible = 1 OR completed = 1)";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());

  while ($line = mysql_fetch_object($result)) {
    if ($pdf->GetY() < 31) 
      $pdf->DLSColumnHeader();
    if ($line->ts_opened == "0000-00-00 00:00:00") $line->ts_opened = "";
    if ($line->ts_complete == "0000-00-00 00:00:00") $line->ts_complete = "";
    $pdf->Row(array($line->incident_id, $line->call_type, $line->call_details, $line->ts_opened, $line->ts_complete));
  }
  mysql_free_result($result);

  if ($pdf->GetY() > 31)
    $pdf->AddPage('');

  $pdf->SetFillColor(230);
  
  // TODO: don't repeat this query needlessly
  $query = "SELECT * FROM incidents WHERE ts_opened LIKE '".MysqlClean($_GET,"selected-date", 20)."%' AND (visible = 1 OR completed = 1)";
  $result = mysql_query($query) or die("In query: $query<br>\nError: ".mysql_error());
  $numrows = mysql_num_rows($result);
  $thisrow=0;
  while ($line = mysql_fetch_object($result)) {
    $thisrow++;
  
    if ($line->ts_opened == "0000-00-00 00:00:00") $line->ts_opened = "";
    if ($line->ts_dispatch == "0000-00-00 00:00:00") $line->ts_dispatch = "";
    if ($line->ts_arrival == "0000-00-00 00:00:00") $line->ts_arrival = "";
    if ($line->ts_complete == "0000-00-00 00:00:00") $line->ts_complete = "";
  
    $pdf->Ln(2);
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(190, 8, "Incident #".$line->incident_id, 1, 1, "L", 1);
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
    $pdf->Cell(38, 5, $line->ts_opened, 1,2);
    $pdf->Cell(38, 5, $line->ts_dispatch, 1,2);
    $pdf->Cell(38, 5, $line->ts_arrival,1,2);
    $pdf->Cell(38, 5, $line->ts_complete, 1,2);
    $pdf->SetXY($pdf->GetX(), $pdf->GetY()+2);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(38, 5, $line->disposition, 0, 2);
    $pdf->SetFont('Arial','',10);
  
    $pdf->SetY($thisrow_endhdr+5);
  
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(30, 5, "Units Assigned to Incident", 0, 1);
    $pdf->SetFont('Arial','',10);
  
    $unitquery = "SELECT * FROM incident_units WHERE incident_id=".$line->incident_id." ORDER BY dispatch_time";
    $unitresult = mysql_query($unitquery) or die("In query: $unitquery<br>\nError: ".mysql_error());
  
    if (mysql_num_rows($unitresult)>0) {
      $pdf->Cell(5,5);
      $pdf->Cell(40,5,"Unit Name");
      $pdf->Cell(40,5,"Time Dispatched");
      $pdf->Cell(40,5,"Time Arrived");
      $pdf->Cell(40,5,"Time Cleared");
      $pdf->Ln(5);
      while ($unit = mysql_fetch_object($unitresult)) {
        $pdf->Cell(5,5);
        $pdf->Cell(40,5,$unit->unit,1,0);
        $pdf->Cell(40,5,$unit->dispatch_time,1,0);
        $pdf->Cell(40,5,$unit->arrival_time,1,0);
        $pdf->Cell(40,5,$unit->cleared_time,1,0);
        $pdf->Ln(5);
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
      $pdf->Cell(40,5,"Time");
      $pdf->Cell(40,5,"Unit");
      $pdf->Cell(40,5,"Note");
      $pdf->Ln(5);
      while ($note = mysql_fetch_object($noteresult)) {
        $pdf->Cell(5,5);
        $pdf->Cell(40,5,$note->ts,1,0);
        $pdf->Cell(40,5,$note->unit,1,0);
        $pdf->MultiCell(100,5,$note->message,1);
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
  
  $pdf->Output();
#}
#else 
#{
        #die( "selected incidents reports not yet available.");
#}
