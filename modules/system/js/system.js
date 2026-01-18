

var tubUserExpanded = false;  // global var to keep track if the user menu is expanded or not

/**
 * This will allow us to have HTML tags in dialog titles.
 */
$.widget("ui.dialog", $.extend({}, $.ui.dialog.prototype, {
    _title: function(title) {
        if (!this.options.title ) {
            title.html("&#160;");
        } else {
            title.html(this.options.title);
        }
    }
})); 



// Set up our modal dialogs on startup, as well as other settings
$(document).ready(function() {
      
    // Hide our expanded menus when we click outside of them.
    jQuery(document).click(function (e) {
      
        if (!jQuery(e.target).hasClass("dropdown-trigger") 
            && jQuery(e.target).parents(".dropdown").length === 0  ) 
        {          
          fpCloseAllUserMenus();
        }
    });
  
  
    
    // TODO: use settings for width/height, if its been set.  This allows us to let the end user configure the size.
    
    
    
    var modalWidth = 500;
    var modalHeight = 400;
    
    if (jQuery(window).width() < 550) {
      modalWidth = 390;
    }

    if (jQuery(window).width() < 450) {
      modalWidth = 350;
    }

    
    $("#fp-iframe-dialog-small").dialog({
      modal: true,      
      show: {
        effect: "blind",
        duration: 300
      },
      resize: function (event, ui) {
                      var heightDifference = 10;
                      var widthDifference = 1;
                      $("#fp-iframe-dialog-small iframe.dialog-iframe").height($(this).height() - heightDifference);
                      $("#fp-iframe-dialog-small iframe.dialog-iframe").width($(this).width() - widthDifference);
                  },   
      open: function (event, ui) {
                      var heightDifference = 10; 
                      var widthDifference = 1;  
                                                                 
                      $("#fp-iframe-dialog-small iframe.dialog-iframe").height($(this).height() - heightDifference);
                      $("#fp-iframe-dialog-small iframe.dialog-iframe").width($(this).width() - widthDifference);
                      
                      // Get rid of annoying focus on close button.
                      $( this ).siblings( ".ui-dialog-titlebar" ).find( "button" ).blur();                       
                      
                  },
      close: function (event, ui) {                      
                      $("#fp-iframe-dialog-small iframe.dialog-iframe").attr('src', 'about:blank');
                      
                  },
      autoOpen: false,
      resizable: false,
      width: modalWidth,      
      height: modalHeight      
    });     
    
    
  
  
  
  
    ////////////////////////
    // Large iframe dialog
    modalWidth = 700;
    modalHeight = 600;
    
    
    if (jQuery(window).width() < 695) {
      modalWidth = 600;
      modalHeight = 550;
    }
    
    if (jQuery(window).width() < 550) {
      modalWidth = 390;
      modalHeight = 500;
    }
    
    if (jQuery(window).width() < 450) {
      modalWidth = 380;
    }

    
    $("#fp-iframe-dialog-large").dialog({
      modal: true,
      show: {
        effect: "blind",
        duration: 300
      },      
      resize: function (event, ui) {
                      var heightDifference = 10;
                      var widthDifference = 1;
                      $("#fp-iframe-dialog-large iframe").height($(this).height() - heightDifference);
                      $("#fp-iframe-dialog-large iframe").width($(this).width() - widthDifference);
                  },   
      open: function (event, ui) {
                      var heightDifference = 10;
                      var widthDifference = 1;  
                                           
                      $("#fp-iframe-dialog-large iframe").height($(this).height() - heightDifference);
                      $("#fp-iframe-dialog-large iframe").width($(this).width() - widthDifference);
                      // Get rid of annoying focus on close button.
                      $( this ).siblings( ".ui-dialog-titlebar" ).find( "button" ).blur();                       

                  },
      close: function (event, ui) {                      
                      $("#fp-iframe-dialog-large-iframe").attr('src', 'about:blank');
                  },
      autoOpen: false,
      resizable: false,
      width: modalWidth,      
      height: modalHeight
    });     
  
    // Give the dialog an initial screen        
    $("#fp-iframe-dialog-large-iframe").attr('src', 'about:blank');
  
  
    
    
    // Go through all of our autocomplete_fields (if any) and set that up.
    if (FlightPath.settings.autocomplete_fields) {
      for (var t = 0; t < FlightPath.settings.autocomplete_fields.length; t++) {
        var e = FlightPath.settings.autocomplete_fields[t];
        var eid = e.id;
        var epath = e.path;
        
        // TODO:  involve jqueryUI to set this up....
        $("#" + eid).autocomplete({
          source: FlightPath.settings.basePath + "/index.php?q=" + epath
        });
        
      }
    }
    
    
    
    
    
    
    
    
    
    
  
});



function fpGetScrollTop() {
  var scrollTop = document.documentElement.scrollTop;
  if (!scrollTop || scrollTop == undefined) {  // Still empty or undefined?
    scrollTop = window.scrollY;
    if (!scrollTop || scrollTop == undefined) {  // Still empty or undefined?
      scrollTop = 0;
    }
  }
  
  return scrollTop;
}




function fpToggleUserMenu() {
  
  
  if (tubUserExpanded == false) {
    jQuery('#tub-user-pulldown').slideDown('fast');
    tubUserExpanded = true;
  }
  else if (tubUserExpanded == true) {
    jQuery('#tub-user-pulldown').slideUp('fast');
    tubUserExpanded = false;    
  }
  
}


function fpCloseAllUserMenus() {
  jQuery('#tub-user-pulldown').slideUp('fast');
  tubUserExpanded = false;    
  
}




function fpToggleHamburgerMenu() {
  $('#mobile-hamburger-menu').slideToggle();
}














  function fpOpenLargeIframeDialog(url, title) {
    
    // make sure we have the "?initial_dialog_open=yes" set in the URL, so we will know if we
    // should keep "nudging" or not in Chrome.
    if (!url.includes("?")) {
      url = url + "?";
    }
    else {
      url = url + "&";
    }

    url = url + "initial_dialog_open=yes";    
    
    
    $("#fp-iframe-dialog-large-iframe").attr('src', url);
    $("#fp-iframe-dialog-large").dialog({title: title});    
    $("#fp-iframe-dialog-large").dialog('open');
  }


  function fpCloseLargeIframeDialog() {
      $("#fp-iframe-dialog-large").dialog('close'); 
  }



  /**
   * This is to fix a bug in Chrome where the iframe does not display any content (though it is there)
   * until the dialog is moved slightly.  This gets called by the dialog itself, via:  parent.fpNudgeDialog() in its document.ready().
   */
  function fpNudgeDialog() {

    setTimeout(function() {
    
    
      $("div[role=dialog]").each(function() {
        
        /*
        $(this).addClass('ui-draggable-dragging');        
        $(this).addClass('ui-dialog-dragging');
        var y = $(this).css('top');
        var oldy = y;        
        y = parseFloat(y.replace("px", ""));        
        var newy = (y + 1) + "px";              
        $(this).css('top',  newy);
      
      */
      
        // Instead of nudging a pixel, we will add a span to the end of the dialog, which should
        // not affect it's size or position.  This... appears... to fix the weird bug in Chrome.  More
        // testing is needed.
        $(this).append("<span></span>");
          
      });
        
      
     
      
      
      
      
      
    
    }, 75);

    
  }



  function fpOpenSmallIframeDialog(url, title) {

    // make sure we have the "?initial_dialog_open=yes" set in the URL, so we will know if we
    // should keep "nudging" or not in Chrome.
    if (!url.includes("?")) {
      url = url + "?";
    }
    else {
      url = url + "&";
    }

    url = url + "initial_dialog_open=yes";


    $("#fp-iframe-dialog-small iframe.dialog-iframe").attr('src', url);
    
    $("#fp-iframe-dialog-small").dialog({title: title});
   
    $("#fp-iframe-dialog-small").dialog('open');
        
  }

  



  function fpCloseSmallIframeDialog() {

    $("#fp-iframe-dialog-small").dialog('close');
      
  }


  //+ Jonas Raoni Soares Silva  
  //@ http://jsfromhell.com
  // Found this function on the Internet.  It acts like php str_replace function:
  // f = find  ex:  "bob"
  // r = replace  ex:  "sally"
  // s = string  ex:  "bob is a girl name"
  function str_replace(f, r, s) {
      var ra = r instanceof Array, sa = s instanceof Array, l = (f = [].concat(f)).length, r = [].concat(r), i = (s = [].concat(s)).length;
      while(j = 0, i--)
        while(s[i] = s[i].split(f[j]).join(ra ? r[j] || "" : r[0]), ++j < l);
    return sa ? s : s[0];
  }


  /**
   * Similar to the php function flightpath, this will remove any non alphanumeric characters
   * and replace with a _ (underscore)
   * @param {Object} str
   */
  function fp_get_machine_readable(str) {
    return str.replace(/[\W_]+/g,"_");
  }    


  // Replacement for built-in alert(str).  Uses the DayPilot code.  See the /includes/ directory.
  // if string_mode == "base64" we will decode.  Useful for passing HTML.
  function fp_alert(str, string_mode) {
    if (string_mode == 'base64') {
      str = decodeURIComponent(escape(atob(str)));  // all the extra escape and such is to handle possible emoji.  See: https://stackoverflow.com/questions/56647747/how-to-base64-encode-emojis-in-javascript
    }
    DayPilot.Modal.alert(str);
  }




  /**
   * Show a loading modal with no buttons.
   *  
   */
  function fp_show_loading(msg) {
    if (!msg) msg = "Updating...";    
    
    var modal = new DayPilot.Modal({
      height: 75,
      width: 250,
      top:40
      });
    var styles = "<style>body{font-family:'Segoe UI', Arial, Helvetica, sans-serif;text-align:center;padding-top:15px;font-weight:bold;font-size:120%;}</style>";
    var spinner = "<img src='" + FlightPath.settings.basePath + "/modules/system/css/throbber.gif" + "' height='18' style='position:relative;top:4px;'> &nbsp;";
    
    modal.showHtml(styles + spinner + msg);    
    
  }  
  
  




  // Replacememnt for built in confirm(str).  Uses the DayPilot code, like fp_alert().
  // if string_mode == "base64" we will decode the str variable.  Useful for passing HTML.
  // the action_if_yes_64 is javascript code which has been encoded as base64.
  function fp_confirm(str, string_mode, action_if_yes_64) {
    if (string_mode == 'base64') {
      str = decodeURIComponent(escape(atob(str)));  // all the extra escape and such is to handle possible emoji.  See: https://stackoverflow.com/questions/56647747/how-to-base64-encode-emojis-in-javascript
    }

    
    DayPilot.Modal.confirm(str).then(function(modal) {
      if (modal.result) {
        // they said YES, so perform the action.        
        action_if_yes = decodeURIComponent(escape(atob(action_if_yes_64)));  // all the extra escape and such is to handle possible emoji.  See: https://stackoverflow.com/questions/56647747/how-to-base64-encode-emojis-in-javascript
        eval(action_if_yes);
      }
      else {
        // do nothing, they cancelled.
      }
    });
      
  }


	
	function popupreportcontact()
	{
	  
	  // Figure out the window's options from our settings, if they exist.
	  var win_options = FlightPath.settings.popupAdminWinOptions;
	  if (!win_options) {
	    win_options = "toolbar=no,status=2,scrollbars=yes,resizable=yes,width=600,height=400"; 
	  }
	  
	  
	  // To make compatible with non-clean URLs, use the "unclean" url...
	  var url = FlightPath.settings.basePath + "/index.php?q=popup-report-contact";
	  
		err_window = window.open(url,
		   "errwindow", win_options);

		err_window.focus();  // make sure the popup window is on top.

	}



	function showUpdate(boolShowLoad)
	{
	  var msg = "Updating...";
	  if (boolShowLoad) msg = "Loading...";
	  fp_show_loading(msg);
	  
	  /*
		var scrollTop = document.body.scrollTop;
		var updateMsg = document.getElementById("updateMsg");
		if (boolShowLoad == true)
		{
			updateMsg = document.getElementById("loadMsg");
		}
		var w = document.body.clientWidth;
		//var h = document.body.clientHeight;
		//var t = scrollTop + (h/2);
		var t = scrollTop;
		updateMsg.style.left = "" + ((w/2) - 120) + "px";
		updateMsg.style.top = "" + t + "px";

		updateMsg.style.position = "absolute";  // must use absolute for ie.
		updateMsg.style.display = "";
		*/
	}
