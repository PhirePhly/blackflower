
var displayClockTimer = null

var displayClockElement = null

function displayClockStop() {
  clearTimeout(displayClockTimer)
}

function displayClockStart() {
  // If the displayClockElement variable is null, try and find
  // which form on the page contains the displayClock form element
  if (displayClockElement == null) {
    for (var i=0; i<document.forms.length; i++) {
      if (document.forms[i].displayClock) {
        displayClockElement = document.forms[i].displayClock
        break
      }
    }
  }

  // Proceed with setting up the clock
  var time = new Date()
  var hours = time.getHours()
  hours=((hours < 10) ? "0" : "") + hours
  var minutes = time.getMinutes()
  minutes=((minutes < 10) ? "0" : "") + minutes
  var seconds = time.getSeconds()
  seconds=((seconds < 10) ? "0" : "") + seconds
  var clock = hours + ":" + minutes + ":" + seconds
  // the old way...
  // document.forms[0].displayClock.value = clock
  displayClockElement.value = clock
  displayClockTimer = setTimeout("displayClockStart()",1000)
}

