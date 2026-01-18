$(document).ready(function() {
      
    // Attach behavior that we "disable-element" once a content-btn is pressed (submit or delete)
    /*
    $(".content-submit-btn").click(function() {
      $(".content-submit-btn").addClass("disable-element");
      $(".content-delete-btn").addClass("disable-element");
    });

    $(".content-delete-btn").click(function() {
      $(".content-submit-btn").addClass("disable-element");
      $(".content-delete-btn").addClass("disable-element");
    });
    */

});






function contentDeleteFile(fieldname, fid) {
  var x = confirm("Are you sure you wish to remove this file?");
  if (!x) return false;
  
  // get rid of the fid from "previous upload" value and make the file field visible again  
  var v = $("#element-previous_upload_" + fieldname).val();
  var newV = "";
  var temp = v.split(',');
  for (var t = 0; t < temp.length; t++) {
    if (temp[t] == fid) continue;  // this is the fid we want to remove.    
    newV += temp[t] + ","; // add everything else.
  }
  
  $("#element-previous_upload_" + fieldname).val(newV);
  
  $("#element-wrapper-" + fieldname).removeClass("element-wrapper--hidden");  
  
  // Also hide the markup where we say what the previously uploaded file was.
  $(".markup-element-markup_" + fieldname + " .file-field-line-" + fid).hide();
  
  
  // manage file_count, and show the add-more-link
  var file_count = $("#element-file_count_" + fieldname).val();
  var x = $("#element-file_count_" + fieldname).val();
  x--;
  if (x < 1) x = 1;
  $("#element-file_count_" + fieldname).val(x);
  
  var file_limit = $("#element-file_limit_" + fieldname).val();
  if (x < file_limit ) {
    // hide the add more link.
    $(".add-more-link-" + fieldname).show();
  }  
  
  
}




/**
 * Add another File input, under this one. 
 * @param {Object} fieldname
 */
function contentAddMoreFile(fieldname) {
  
  var file_count = $("#element-file_count_" + fieldname).val();
  var file_limit = $("#element-file_limit_" + fieldname).val();
  
  
  
  var fe = "<div class='add-more-file'><input type='file' name='" + fieldname + "[]' ></div>";
  
  $('#element-inner-wrapper-' + fieldname).append(fe);
  
  var x = $("#element-file_count_" + fieldname).val();
  x++;
  $("#element-file_count_" + fieldname).val(x);

  // if the file count is > than our limit, then we must stop!
  if (file_count >= file_limit - 1) {
    // hide the add more link.
    $(".add-more-link-" + fieldname).hide();
  }  
  
  
}