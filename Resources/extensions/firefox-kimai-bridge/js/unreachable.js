$( document ).ready(function() {
  $("#open-settings").on("click", function() {
      browser.runtime.openOptionsPage();
  });
});