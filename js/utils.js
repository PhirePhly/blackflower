// util.js
//
// Utilities that are typically used by the CAD system

function popup(url, name, height, width)
{
  var myWindow = window.open(url,name,'width='+width+',height='+height+',scrollbars')
  // FLAG: TODO: modal=yes is a partially ineffective hack, i'd prefer to have truly modeless persistent windows, but Javascript SUCKS.
  if (myWindow.focus) {
    myWindow.focus()
  }
  return false;
}

function resizeMe()
{
  document.cookie = "width=" + window.innerWidth;
  document.cookie = "height=" + window.innerHeight;
  window.location.reload();
  return false;
}

// ---------------------------------------------------------------------------

/*Javascript for Bubble Tooltips by Alessandro Fulciniti
http://pro.html.it - http://web-graphics.com */

function enableTooltips(id) {
  var links,i,h;
  if (!document.getElementById || !document.getElementsByTagName)
    return;
  AddCss();
  h = document.createElement("span");
  h.id="btc";
  h.setAttribute("id","btc");
  h.style.position = "absolute";
  document.getElementsByTagName("body")[0].appendChild(h);
  if (id==null)
    links = document.getElementsByTagName("a");
  else
    links = document.getElementById(id).getElementsByTagName("a");
  for(i=0; i<links.length; i++) {
    Prepare(links[i]);
  }
}

function Prepare(el) {
  var tooltip,t,b,s,l;
  t = el.getAttribute("title");
  if (t==null || t.length==0)
    return; //t = "link:";
  el.removeAttribute("title");
  tooltip = CreateEl("span","tooltip");
  s = CreateEl("span","top");
  s.appendChild(document.createTextNode(t));
  tooltip.appendChild(s);
  b = CreateEl("b","bottom");
  l = el.getAttribute("href");
  if (l.length>28)
    l = l.substr(0,25)+"...";
  b.appendChild(document.createTextNode(l));
  tooltip.appendChild(b);
  setOpacity(tooltip);
  el.tooltip = tooltip;
  el.onmouseover = showTooltip;
  el.onmouseout = hideTooltip;
  el.onmousemove = Locate;
}

function showTooltip(e) {
  document.getElementById("btc").appendChild(this.tooltip);
  Locate(e);
}

function hideTooltip(e) {
  var d = document.getElementById("btc");
  if (d.childNodes.length > 0)
    d.removeChild(d.firstChild);
}

function setOpacity(el) {
  el.style.filter = "alpha(opacity:95)";
  el.style.KHTMLOpacity = "0.95";
  el.style.MozOpacity = "0.95";
  el.style.opacity = "0.95";
}

function CreateEl(t,c) {
  var x = document.createElement(t);
  x.className = c;
  x.style.display = "block";
  return(x);
}

function AddCss() {
  var l = CreateEl("link");
  l.setAttribute("type","text/css");
  l.setAttribute("rel","stylesheet");
  l.setAttribute("href","bt.css");
  l.setAttribute("media","screen");
  document.getElementsByTagName("head")[0].appendChild(l);
}

function Locate(e){
  var posx = 0,posy = 0;
  if (e==null)
    e = window.event;
  if (e.pageX || e.pageY) {
    posx = e.pageX;
    posy = e.pageY;
  } else if (e.clientX || e.clientY) {
    if (document.documentElement.scrollTop) {
      posx = e.clientX+document.documentElement.scrollLeft;
      posy = e.clientY+document.documentElement.scrollTop;
    } else {
      posx = e.clientX+document.body.scrollLeft;
      posy = e.clientY+document.body.scrollTop;
    }
  }
  document.getElementById("btc").style.top = (posy+10)+"px";
  document.getElementById("btc").style.left = (posx-20)+"px";
}

// ---------------------------------------------------------------------------

function writeWATestCookie(){
  var today = new Date();
  var the_date = new Date(today.getYear() + 1900, today.getMonth(), today.getDate(), today.getHours(), today.getMinutes(), today.getSeconds()+30);
  var the_cookie_date = the_date.toGMTString();
  var the_cookie = "WA=enabled";
  var the_cookie = the_cookie + ";expires=" + the_cookie_date;
  var the_cookie = the_cookie + ";path=/";
  document.cookie=the_cookie;
}

writeWATestCookie();
var cookiesEnabled = (document.cookie.indexOf("WA")!=-1)? true : false;
if (!cookiesEnabled){
  document.write ("<br /><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"WhiteTable PublicError\">");
  document.write ("<tr><td id=\"Top\"><img src=\"Images/1.gif\" width=\"1\" height=\"1\" alt=\"\"/></td></tr>");
  document.write ("<tr>");
  document.write ("<td id=\"Mid\">");
  document.write ("<div class=\"EmptyContianerImage\">");
  document.write ("<div class=\"EmptyContianerText\">");
  document.write ("Set Up Your Web Browser To Allow Cookies");
  document.write ("</div>");
  document.write ("<div class=\"EmptyContianerExplain\">");
  document.write ("Cookies are required to use this site. Please enable your web browser to allow (accept) cookies. For steps in configuring this, see the Help for your browser.");
  document.write ("&nbsp;&nbsp;");
  document.write ("</div>");
  document.write ("</div>");
  document.write ("</td>");
  document.write ("</tr>");
  document.write ("<tr><td id=\"Bot\"><img src=\"Images/1.gif\" width=\"1\" height=\"1\" alt=\"\"/></td></tr>");
  document.write ("</table><br />");
}