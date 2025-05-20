/**
 * @file
 * Main javascript file for Batch module.
*/


// When the page has finished loading, let's start the batch process up...
$(document).ready(function() {
  

  //activate the ajax function...
  batchContinueBatch();
   
  
});



// Continue the batch process...
function batchContinueBatch() {
  var batchId = FlightPath.settings.batch_id;
  var basePath = FlightPath.settings.basePath;
  
  // use "unclean" URLs so that it's compatible with non-Clean URL sites.
  // Contact via Ajax...
  var jqxhr = $.get(basePath + "/index.php?q=batch-ajax-callback/" + batchId, function(data) {  
  
    
    
    // Handle data. 
    if (data.error != null) {
      alert(data.error);
      return;
    }
    
    if (data.success != "SUCCESS") {
      // We got some kind of error!
      alert("Error processing batch.  Returned 'data' message: \n\n" + data);
      return;
    }
    
    // No error. Let's update our values...
    var progressMsg = data.progress_message;
    var percent = data.percent;
        
    
    // Update the screen.
    $("#batch-progress-message").html("<div>" + progressMsg + "</div>");
    // Update the width of the progress bar.
    $("#batch-progress-bar").css("width", percent + "%");
    
    // Add percent to the progress bar?
    if (data.display_percent == true) {
      $("#batch-progress-bar").html("<span class='percent'>" + percent + "%</span>");
    }
    
    
    // If we are finished, then we can proceed to our finished handler.
    if (data.finished == "finished") {      
      // Redirect to our finished page...  (use unclean URL path for compatibility)
      window.location = basePath + "/index.php?q=batch-finished/" + batchId;
      return;
    }
    
    
    // We now need to go back through the loop.  Call this function after a brief delay...
    setTimeout(batchContinueBatch(), 250);
    
    
    
  })  
  .fail(function (data) {
    alert("Error processing batch. Unable to continue.");
    console.log(data);
    return;    
  });
  
  
  
  
  
  
  
}