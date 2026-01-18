/**
 * This file is designed using modernizr, as well as some helpful functions found on stackoverflow.
 * 
 * The point of it is to replace the functionality of the "datetime-local" field used in FlightPath, but which
 * unfortunately not supported in FireFox as of the time of this writing.
 * 
 * modernizr will detect if the browser can handle datetime-local, and if not, we will proceed to declare the field as a javascript-based
 * date/time picker.
 */


/*! modernizr 3.6.0 (Custom Build) | MIT *
 * https://modernizr.com/download/?-inputtypes-setclasses-cssclassprefix:mod_ !*/
!function(e,t,n){function a(e,t){return typeof e===t}function s(){var e,t,n,s,i,o,c;for(var u in r)if(r.hasOwnProperty(u)){if(e=[],t=r[u],t.name&&(e.push(t.name.toLowerCase()),t.options&&t.options.aliases&&t.options.aliases.length))for(n=0;n<t.options.aliases.length;n++)e.push(t.options.aliases[n].toLowerCase());for(s=a(t.fn,"function")?t.fn():t.fn,i=0;i<e.length;i++)o=e[i],c=o.split("."),1===c.length?Modernizr[c[0]]=s:(!Modernizr[c[0]]||Modernizr[c[0]]instanceof Boolean||(Modernizr[c[0]]=new Boolean(Modernizr[c[0]])),Modernizr[c[0]][c[1]]=s),l.push((s?"":"no-")+c.join("-"))}}function i(e){var t=u.className,n=Modernizr._config.classPrefix||"";if(f&&(t=t.baseVal),Modernizr._config.enableJSClass){var a=new RegExp("(^|\\s)"+n+"no-js(\\s|$)");t=t.replace(a,"$1"+n+"js$2")}Modernizr._config.enableClasses&&(t+=" "+n+e.join(" "+n),f?u.className.baseVal=t:u.className=t)}function o(){return"function"!=typeof t.createElement?t.createElement(arguments[0]):f?t.createElementNS.call(t,"http://www.w3.org/2000/svg",arguments[0]):t.createElement.apply(t,arguments)}var l=[],r=[],c={_version:"3.6.0",_config:{classPrefix:"mod_",enableClasses:!0,enableJSClass:!0,usePrefixes:!0},_q:[],on:function(e,t){var n=this;setTimeout(function(){t(n[e])},0)},addTest:function(e,t,n){r.push({name:e,fn:t,options:n})},addAsyncTest:function(e){r.push({name:null,fn:e})}},Modernizr=function(){};Modernizr.prototype=c,Modernizr=new Modernizr;var u=t.documentElement,f="svg"===u.nodeName.toLowerCase(),p=o("input"),d="search tel url email datetime date month week time datetime-local number range color".split(" "),m={};Modernizr.inputtypes=function(e){for(var a,s,i,o=e.length,l="1)",r=0;o>r;r++)p.setAttribute("type",a=e[r]),i="text"!==p.type&&"style"in p,i&&(p.value=l,p.style.cssText="position:absolute;visibility:hidden;",/^range$/.test(a)&&p.style.WebkitAppearance!==n?(u.appendChild(p),s=t.defaultView,i=s.getComputedStyle&&"textfield"!==s.getComputedStyle(p,null).WebkitAppearance&&0!==p.offsetHeight,u.removeChild(p)):/^(search|tel)$/.test(a)||(i=/^(url|email)$/.test(a)?p.checkValidity&&p.checkValidity()===!1:p.value!=l)),m[e[r]]=!!i;return m}(d),s(),i(l),delete c.addTest,delete c.addAsyncTest;for(var h=0;h<Modernizr._q.length;h++)Modernizr._q[h]();e.Modernizr=Modernizr}(window,document);



// Runs on page load.
$(document).ready(function() {
    if(!Modernizr.inputtypes['datetime-local']) {
      
        // Let's convert the existing time we were given with something more attractive and compatible.
        var currentVal = $('input[type=datetime-local]').val();        
        var ts = Date.parse(currentVal);
        var myDefaultDate = fs_getFormattedDate(ts);
        
        $('input[type=datetime-local]').val(myDefaultDate)
        
        
        $('input[type=datetime-local]').datetimepicker({
            step: 5,
            format:'m/d/Y h:i a',
            formatTime:'h:i a',
            formatDate:'m/d/Y',
            defaultDate: myDefaultDate            
          });
       
       
    }  
});



function fs_getFormattedDate(ts) {
    var date = new Date(ts);

    var month = date.getMonth() + 1;
    var day = date.getDate();
    var hour = date.getHours();
    var min = date.getMinutes();
    var sec = date.getSeconds();

    var ampm = "am";
    
    if (hour == 0) {
      hour = 12;
    }
    
    if (hour > 12) {
      hour = hour - 12;
      ampm = "pm";
    }
    

    month = (month < 10 ? "0" : "") + month;
    day = (day < 10 ? "0" : "") + day;
    hour = (hour < 10 ? "0" : "") + hour;
    min = (min < 10 ? "0" : "") + min;
    sec = (sec < 10 ? "0" : "") + sec;

    

    //var str = date.getFullYear() + "-" + month + "-" + day + "_" +  hour + ":" + min + ":" + sec;
    var str = month + "/" + day + "/" + date.getFullYear() + " " +  hour + ":" + min + " " + ampm;

    /*alert(str);*/

    return str;
}