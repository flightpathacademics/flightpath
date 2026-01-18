/**
 *  JS for the calendar module. 
*/

$(document).ready(function() {
  
  if ($('body').hasClass('content-edit-type--schedule_unavailable_time')) {
    calendarUpdateScreenBasedOnTimeSelector();
    
    $("input[name='time_selector']").change(function() {
      calendarUpdateScreenBasedOnTimeSelector();
    });
    
  }
  
});



// Update screen elememnts based on our time selector.
function calendarUpdateScreenBasedOnTimeSelector() {
  var val = $("input[name='time_selector']:checked").val();
    
  // hide/unhide elements based on if val is blank or "manual"
  if (val == "") {
    $('#element-wrapper-day_start_hour').show();
    $('#element-wrapper-day_stop_hour').show();
    
    $('#element-wrapper-start_time').hide();
    $('#element-wrapper-end_time').hide();
        
  }
  else if (val == 'manual') {
    
    $('#element-wrapper-day_start_hour').hide();
    $('#element-wrapper-day_stop_hour').hide();
    
    $('#element-wrapper-start_time').show();
    $('#element-wrapper-end_time').show();
        
  }
  else if (val == 'none') {
    $('#element-wrapper-day_start_hour').hide();
    $('#element-wrapper-day_stop_hour').hide();
    
    $('#element-wrapper-start_time').hide();
    $('#element-wrapper-end_time').hide();
            
  }
  
    
}