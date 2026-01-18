/**
* @file
* JS file for the audit module
**/



/**
Called from the popup when we wish to submit our approval form.
*/
function auditPopupEditApprovalFormSubmit(approval_type, student_id) {
  
  // Get values from the form.
  // Get the approval value  
  //var approval_value = $("#element-approval").val();
  var approval_value = $("input[name=approval]:checked").val();
  var comment = $("#element-comment").val();
  
  
  // Now, we will pass this information back to the opener webpage's hidden form and submit it.
  parent.$("#element-approval_type").val(approval_type);
  parent.$("#element-approval_value").val(approval_value);
  parent.$("#element-approval_student_id").val(student_id);
  parent.$("#element-approval_comment").val(comment);
  
  parent.showUpdate(false);

  // Close the dialog
  parent.fpCloseSmallIframeDialog();

  
  // Submit the form
  parent.$("#fp-form-audit_hidden_approval_form").submit();
  
     
  return false;  // done with the submission.  Returning false prevents the form from actually submitting.
}

