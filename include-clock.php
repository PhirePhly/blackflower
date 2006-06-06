<?php
        ?>
  <SCRIPT type="text/javascript">
   var displayClockTimer = null

   function displayClockStop()
   {
     clearTimeout(displayClockTimer)
   }

   function displayClockStart()
   {
     var time = new Date()
     var hours = time.getHours()
     hours=((hours < 10) ? "0" : "") + hours
     var minutes = time.getMinutes()
     minutes=((minutes < 10) ? "0" : "") + minutes
     var seconds = time.getSeconds()
     seconds=((seconds < 10) ? "0" : "") + seconds
     var clock = hours + ":" + minutes + ":" + seconds
     document.forms[0].displayClock.value = clock
     displayClockTimer = setTimeout("displayClockStart()",1000)
   }
  </SCRIPT>

