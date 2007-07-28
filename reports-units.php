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

function SetReportsCriteria($unit, $date) {
  $this->showcriteria = "For $unit activity on $date ";
}

function Header()
{
    $this->Image('Logos/logo-esd.jpg',175,8,16);
    $this->SetFillColor(230);

    // top row
    $this->SetY(12);
    $this->SetFont('Arial','B',16);
    $this->Cell(160,5,'Black Rock City ESD - Unit Report',0,0);

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
}

function index_sort($a, $b)
{
  if ($a == $b) {
    return 0;
  }
  return (date('H:i:s', strtotime($a[0])) > date('H:i:s', strtotime($b[0]))) ? 1 : -1;
}

// End subclass definition
// Begin main program

if (isset($_GET["unit"]) && isset($_GET["selected-date"])) {
  
  $unit = MysqlClean($_GET,"unit",20);
  $date = MysqlClean($_GET,"selected-date",20);
  $pdf=new PDF();
  $pdf->SetReportsCriteria($unit, $date);
  $pdf->SetFont('Arial','',10);
  $pdf->Open();
  $pdf->AliasNbPages();
  $pdf->AddPage('');
  $pdf->SetFillColor(230);
  
  syslog(LOG_INFO, $_SESSION['username'] . " generated units report");
  $index = array();
  $associated_incidents = array();
  $associated_messages = array();
  $associated_callnums = array();
  
  $incidents = MysqlQuery("SELECT incident_id,call_number FROM incidents");
  while ($incident = mysql_fetch_object($incidents)) {
    $associated_callnums[$incident->incident_id] = $incident->call_number;
  }
  mysql_free_result($incidents);

  $incident_units = MysqlQuery("SELECT * FROM incident_units WHERE unit='$unit' AND DATE_FORMAT(dispatch_time, '%Y-%m-%d') LIKE '$date%'");
  while ($incident_unit = mysql_fetch_object($incident_units)) {
    if ($incident_unit->arrival_time == "0000-00-00 00:00:00") $incident_unit->arrival_time = "";
    if ($incident_unit->cleared_time == "0000-00-00 00:00:00") $incident_unit->cleared_time = "";
    $associated_incidents[$incident_unit->incident_id] =  $incident_unit;
    array_push($index, array($incident_unit->dispatch_time, "Incident", $incident_unit->incident_id));
  }
  mysql_free_result($incident_units);

  $messages = MysqlQuery("SELECT * FROM messages WHERE unit='$unit' AND DATE_FORMAT(ts, '%Y-%m-%d') LIKE '$date%'");
  while ($message = mysql_fetch_object($messages)) {
     $associated_messages[$message->oid] = $message;
     array_push($index, array($message->ts, "Message", $message->oid));
  }
  mysql_free_result($messages);

  usort($index, "index_sort");
  
  foreach ($index as $index_item) {
    if ($index_item[1] == "Incident") {
      $line = $associated_incidents[$index_item[2]];
      $pdf->Ln(2);
      $pdf->SetFont('Arial','B',12);
      if ($associated_callnums[$line->incident_id] != '') {
        $pdf->Cell(70, 5, 'Attached to Call #' . $associated_callnums[$line->incident_id], 0, 1, "L");
      } else {
        $pdf->Cell(70, 5, 'Attached to Call (Error: Call Number not available - Incident ID# ' .$line->incident_id.')', 0, 1, "L");
      }

      $thisrow_top = $pdf->GetY();
      $pdf->SetFont('Arial','',10);
      $pdf->Cell(5,5); $pdf->Cell(30, 5, "Dispatch Time:", 0, 0, "R");  $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->dispatch_time, 1, 1);
      $pdf->Cell(5,5); $pdf->Cell(30, 5, "Arrival Time:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->arrival_time, 1, 1);
      $pdf->Cell(5,5); $pdf->Cell(30, 5, "Released Time:", 0, 0, "R");    $pdf->Cell(3); $pdf->MultiCell(80, 5, $line->cleared_time, 1, 1);
      $pdf->Ln(5);
      $pdf->SetFont('Arial','B',10);
      if ($associated_callnums[$line->incident_id] != '') {
        $pdf->Cell(5,5); $pdf->Cell(30, 5, "Notes Logged For $unit For Call ".$associated_callnums[$line->incident_id], 0, 1);
      } else {
        $pdf->Cell(5,5); $pdf->Cell(30, 5, "Notes Logged For $unit For Incident ".$line->incident_id, 0, 1);
      }

      $pdf->SetFont('Arial','',10);
    
      $notequery = "SELECT * FROM incident_notes WHERE incident_id=".$line->incident_id." AND unit='$unit' ORDER BY note_id";
      $noteresult = mysql_query($notequery) or die("In query: $notequery<br>\nError: ".mysql_error());
      if (mysql_num_rows($noteresult) > 0) {
        $pdf->Cell(10,5);
        $pdf->Cell(40,5,"Time");
        $pdf->Cell(40,5,"Unit");
        $pdf->Cell(40,5,"Note");
        $pdf->Ln(5);
        while ($note = mysql_fetch_object($noteresult)) {
          $pdf->Cell(10,5);
          $pdf->Cell(40,5,$note->ts,1,0);
          $pdf->Cell(40,5,$note->unit,1,0);
          $pdf->MultiCell(80,5,$note->message,1);
        }
      }
      else {
        $pdf->Cell(5,5);
        $pdf->Cell(50,5, "- No notes logged by $unit for this incident -");
        $pdf->Ln(5);
      }
      mysql_free_result($noteresult);
          
      $pdf->Ln(5);
    }
    elseif ($index_item[1] == "Message") {
      $line = $associated_messages[$index_item[2]];
      $pdf->SetFont('Arial','B',12);
      $pdf->Cell(50, 5, "Message at ".date('H:i:s',strtotime($line->ts)), 0, 0, "L"); $pdf->Cell(3);
      $pdf->SetFont('Arial','',12);
      $pdf->MultiCell(120, 5, $line->message, 1, 1);
    }
    else {
      print ("Invalid index element: ".$index_item[1]."<br>");
    }
    //$thisrow_endhdr = $pdf->GetY();
    //$pdf->SetY($thisrow_endhdr+5);
  }
  $pdf->SetDisplayMode('fullpage','single');
  mysql_close($link);
  
  $pdf->Output();
}
else 
{
  die("Unit selection not set.");
}

?>
