
function stampTimestamp() {
  var stampTime = new Date()
  var hours = stampTime.getHours()
  hours=((hours < 10) ? "0" : "") + hours
  var minutes = stampTime.getMinutes()
  minutes=((minutes < 10) ? "0" : "") + minutes
  var seconds = stampTime.getSeconds()
  seconds=((seconds < 10) ? "0" : "") + seconds
  var value = hours + ":" + minutes + ":" + seconds
  return (value)
}

function stampFulltime() {
  var stampTime = new Date()
  var year = stampTime.getFullYear()
  var month = stampTime.getMonth()+1
  month=((month < 10) ? "0" : "") + month
  var day = stampTime.getDate()
  day=((day < 10) ? "0" : "") + day
  var mytime = year + "-" + month + "-" + day + " " + stampTimestamp()
  return (mytime)
}

function handleIncidentType() {
  if (document.myform.call_type.value == "not selected") {
    document.myform.disposition.selectedIndex = 0;
    document.myform.ts_complete.value = "0000-00-00 00:00:00";
    document.myform.dts_complete.value = "";
    // If the release_query checkbox is present, disable it and reset values
    if (document.myform.release_query != null) {
      document.myform.release_query.disabled = 1;
      document.myform.release_query.checked = 0;
      document.myform.release_query.value = 0;
    }
  }
}

function handleDisposition() {
  if (document.myform.disposition.value != "") {
    // We don't want to complete the incident unless it has a defined type assiciated with it
    if (document.myform.call_type.value == "not selected") {
      document.myform.disposition.selectedIndex = 0;
      alert('You must choose a Call Type before marking the incident as Completed.');
    }
    else {
      //alert('type ok setting times and release'); -- debugging code!  don't leave in production checkins...
      // If the completed timestamps do not already have values, fill them in now
      // just in case maybe we're changing the disposition type after completion of the incident
      //if ((document.myform.ts_complete.value == "0000-00-00 00:00:00" ||
           //document.myform.ts_complete.value == "")
          //&& document.myform.dts_complete.value == "") {
        //document.myform.ts_complete.value = stampFulltime();
        //document.myform.dts_complete.value = stampTimestamp();
      //}
      // If the release_query checkbox is present, enable
      if (document.myform.release_query != null) {
        document.myform.release_query.disabled = false;
        document.getElementById('mustassign').textContent = ' ';
      }
      if(document.myform.disposition.value == "Duplicate") {
        $('#duplicate_label').show();
        $('#duplicate_of').show();
        if($('#duplicate_of').val() == "") {
	    		$("button[name='save_incident_closewin']").first().attr("disabled", true);
  	  		$("button[name='save_incident']").first().attr("disabled", true);
  	  	} else {
	    		$("button[name='save_incident_closewin']").first().attr("disabled", false);
  	  		$("button[name='save_incident']").first().attr("disabled", false);
  	  	}
      } else {
		    $('#duplicate_label').hide();
    		$('#duplicate_of').hide();
    		$("button[name='save_incident_closewin']").first().attr("disabled", false);
    		$("button[name='save_incident']").first().attr("disabled", false);
      }
    }
  }
  else {
    //document.myform.ts_complete.value = "0000-00-00 00:00:00";
    //document.myform.dts_complete.value = "";
    // If the release_query checkbox is present, disable it
    if (document.myform.release_query != null) {
      document.myform.release_query.disabled = true;
      document.getElementById('mustassign').textContent = '(Must Assign a Disposition first)';
    }
    $('#duplicate_label').hide();
    $('#duplicate_of').hide();
		$("input[name='save_incident_closewin']").first().attr("disabled", false);
		$("input[name='save_incident']").first().attr("disabled", false);
  }
}

var needToCancel = true;
var myform;

$(document).ready(function() { 
    
    myform = $('form[name=myform]').first();
    
    myform.submit(function() {
      needToCancel = false;
    });
    
    $('.noEnterSubmit').keypress(function(e){
        if ( e.which == 13 ) e.preventDefault();
    });
    
    if(document.myform.disposition.value == "Duplicate") {
    	$('#duplicate_label').show();
    	$('#duplicate_of').show();
    } else {
    	$('#duplicate_label').hide();
    	$('#duplicate_of').hide();
    }
    
    $('#duplicate_of').change(
      function() {
        if($('#duplicate_of').val() == "") {
	    		$("button[name='save_incident_closewin']").first().attr("disabled", true);
  	  		$("button[name='save_incident']").first().attr("disabled", true);
  	  	} else {
	    		$("button[name='save_incident_closewin']").first().attr("disabled", false);
  	  		$("button[name='save_incident']").first().attr("disabled", false);
  	  	}
      }
    );

});

$(window).unload(function() 
  { 
    if(needToCancel) {
    
      var url = myform.attr('action');

      var data = myform.serialize();
      data += '&cancel_changes=';
      
      $.post(url, data);
      
    }
  } 
);

