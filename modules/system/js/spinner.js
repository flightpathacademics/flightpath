// This js file deals specifically with "spinner" gifs showing or hiding when we click a button.
$(document).ready(function() {

    // Attach behavior to any element with the "show-spinner" class.
    $(".show-spinner").click(function() {
      var name = $(this).attr("name");
      if (name) {
        $(this).addClass("disable-element");
        $(".loading-spinner-" + name).show();
      }
    });


    // If we are returning from having clicked "back", we don't want
    // a previously disabled button to still be disabled/spinner showing.
    $(window).on('pageshow', function() {
      $(".show-spinner").removeClass('disable-element');
      $(".loading-spinner").hide();
    });



});