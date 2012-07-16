<?php
  $subsys="help";
  require_once('session.inc');
  require_once('functions.php');

  header_html("Dispatch :: Help");
?>
<body vlink="blue" link="blue" alink="cyan">
<?php include('include-title.php'); ?>

<table width="750">
<p>
<tr><td colspan="2" width="100%" bgcolor="blue">
<font color="white"><b>&nbsp; &nbsp; HELP</b></font></td>
<tr><td width="20">&nbsp;</td><td>
<p>
<p class="textj">
This page provides brief instructions on how to use the software.  This software is still
under development, so if you have a question about it, a feature suggestion or bug report,
send email to <a href="mailto:cad-info@forlorn.net">&lt;cad-info@forlorn.net&gt;</a>.
<p>
<b>General Usage Notes</b>
<p class="textj">
    Black Flower CAD is a Computer Aided Dispatch application which runs on a Netscape based Web browser (such
    as Netscape, Mozilla, Firefox, et cetera.)  The back end is a SQL database with PHP interface pages.
    Certain parts of the critical JavaScript code in CAD are NOT currently written to work
    under Internet Explorer, so use IE only at your own risk.  It is recommended that you
    view CAD in Full Screen mode (F11 or <b><u>V</u>iew|<u>F</u>ull Screen</b>) in order to take advantage
    of all available screen real estate.
<p class="textj">
    The tabbed menu selectors at the top of the screen allow the operator to load the different sections of
    the CAD application.  The Incidents, Unit Listing and Log View screens are the main operational modes, while
    the Settings, Reports, and Help screens provide the other available options.
<p class="textj">
    Every 15 seconds, most of the main CAD webpages will automatically refresh from the server.  You will then
    see any new messages that have been entered into CAD on other stations.
    In general, pages that need to refresh will refresh; while pages such as the Settings, Reports and
    Help screens will not.
<p class="textj">
    You can also refresh the webpage whenenver you would like, by Reloading your browser (pressing Ctrl-R).
    The screen will also automatically reload whenever you enter new data or move to a different screen.
<p class="textj">
    Incidents, Incident Notes, and Log messages, that have been entered in the previous five minutes are <b>shown in bold</b>.
<p class="textj">
    Please note that the clocks shown on the Log Viewer and Incidents pages reflect the web browsing client
    computer's time; all other times throughout the program reflect the server time.
    The administrator of this software should take care to ensure that the clients'
    times are synchronized with the server's time.


<p>
<b>The <u>Incidents</u> mode</b>

<p class="textj">
    The Incidents mode allows operators to create incidents, dispatch and manage unit assets,
    and input a series of timestamped notes specific to each incident.  By default, only the
    currently open Incidents will be viewed.  This is the recommended mode.  If you do need to
    revisit previous incidents for any reason, an option is available from the Settings screen to
    toggle whether completed incidents are included in the list displayed in the Incidents screen.
<p class="textj">
    When Editing an Incident, while the Incident Notes refresh from the server every
    15 seconds (and therefore allow multiple operator stations to easily track the progress of an
    incident), the other fields displayed for that Incident do not automatically refresh.  You
    will need to manually refresh the Edit Incidents page, or Save Incident and come back
    to it later, in order to see another operator's general updates to that Incident.
<p class="textj">
    <b>WARNING: Unless you use the Incident Locking feature (v1.8.0+), in the situation where:
    <ul>
    <li> Operator #1 is viewing an incident in the Edit Incident screen
    <li> Operator #2 opens the same incident, makes and saves changes (other than adding Notes or
    managing assigned Units)
    <li> Operator #1 selects to Save Incident (whether or not they made changes to it)
    </ul>
<p class="textj">
    CAD will warn Operator #1 that the incident has been edited by another station, and will not allow
    Operator #1 to save their changes.
    </b>
<p class="textj">
    For that reason it is recommended that you either use Incident Locking, or that multiple operators do not simultaneously Edit data in the
    same Incident.  If this is unavoidable (for example, so one operator can read information to dispatch
    a unit, while another operator is getting additional information from the reporting party) you must
    be careful to Cancel Changes on the first operator's unmodified screen, rather than Save Incident.
    <ul>
       <p class="textj">
          <i>Tip</i>: You can navigate between the relevant fields in the Edit Incident screen using the tab key.
  </ul>
<p class="textj">
    Only units which are In Service may be assigned to an incident.  By default, a newly created unit is not
    set In Service.  When creating Units, the operator must assign the appropriate units to be In Service.
<p class="textj">
    Units which are already assigned to an incident may not be assigned to a different incident.
    You must release them from the first incident before you will be able to select them for
    assignment to any other.
<p class="textj">
    In the Edit Incident screen, when you select a Unit to be attached to an Incident, this also
    automatically assigns the Dispatch Time for that incident.  You must select the On Scene button for
    the unit in question, when it arrives, in order to set the Unit On Scene Time for the incident in general.
    When the incident has been completed, selecting the Disposition of the incident will assign the Completed
    Time to the Incident.
<p class="textj">
    Units may be manually Released from Incidents, or you may do so automatically for the entire incident:
<p class="textj">
    When completing an incident and saving it out of the system, you will have the (default) option to release
    the assigned units to In Service status.  If for whatever reason you do NOT want to release
    the units, you must un-check that checkbox before saving the completed incident.  <b>Saving the
    completed incident will mark it as completed and remove it from the Incidents screen; you
    will not have the chance after this point to release a unit from a completed incident.  You must
    then edit the unit manually to release it.</b>

<p>
<b>The <u>Unit Listing</u> mode</b>

<p class="textj">
    The Unit Listing screen shows a list of units, their associated status, and the last Message that
    was logged for that unit in the Log Viewer screen.  Please note that Incident Notes are not
    included with regards to Last Message display, only Messages which are unrelated to any Incident.
<p class="textj">
    The Unit Listing is divided into three categories:  Units, which are typical role-based units,
    Individuals, which are typically officer-level roving units that will also initiate or respond to
    calls, and Generic units.  Generic units are a way to designate in CAD that a unit has
    been requested from a foreign dispatch center, without necessarily knowing what unit number it is
    or how many units that dispatch center may have available.  For example, if a request was made for
    Ambulance and Law Enforcement assistance, a Generic unit can be assigned from each agency, but will
    still leave each agency's Generic unit In Service and available to be dispatched to other, simultaneous,
    calls.
<p class="textj">
    To view or edit the status of a unit, click on its name in the Unit Listing.  You are then presented
    with a Unit dialog.  To change the unit's status from the Unit dialog, select the new status, enter an
    optional comment, and click the Save button.  Unit Types and Branches should be set appropriately when
    the unit is first defined, and not usually changed after that.  Unit Personnel will be the same for
    Individual and Generic units, but may be changed each shift for true Units.
<p class="textj">
    The Update time in the Unit Listing reflects the last time that unit's Status was changed, whereas
    the Last Message time reflects the last time a message was entered.  Messages are primarily logged
    by the operator in the Log Viewer screen.  Unit-specific messages are also created every time
    an operator manually changes the unit's status in the Unit dialog, and when the Unit is assigned
    to or released from an Incident.

<p>
<b>The <u>Log Viewer</u> mode</b>
<p class="textj">
    In the Log Viewer mode, you are presented with a list of log
    entries.  The data entry fields of the top line are used to add new entries into the log.
<p class="textj">
    The Log Viewer list can be switched into one of three modes of display (this setting can be changed
    in the Settings screen):
    <ul>
      <li> Most Recent 25 -- This is the default mode, and will always display the most recent 25 messages.
      <li> Hourly Entries -- Displays the messages entered during a one hour time period.  Initially showing
           the current hour, you can navigate forwards and backwards through other hourly periods.
      <li> All Log Entries -- Displays every log entry in the database -- this will typically be <i>quite</i> a few entries.
    </ul>
<p class="textj">
    To add a new log message using the data entry fields at the top of the list, select a Unit from
    the drop-down Unit box, then write the text of your log message in the Message text-entry box.
    Then save the message in the database.
    <ul>
      <p class="textj">
          <i>Tip</i>: To save the message, as an alternative to clicking on the Save button, you can also just
           press Enter in the Message text-entry box.<br>

       <p class="textj">
          <i>Tip</i>: The Log Viewer screen should load with the drop-down list box selected.  Then, you
          can press the first letter of the unit you're looking for, to scroll down through the list without
          needing to use the mouse.  Then press Tab to get to the Message box.
    </ul>
<p class="textj">
    If you decide you do <i>not</i> want to save a message you have begun to write, you can click
    the &quot;Clear&quot; button to clear the drop-down and text-entry boxes.<br>
<p class="textj">

</td></tr></table>

</body>
</html>


