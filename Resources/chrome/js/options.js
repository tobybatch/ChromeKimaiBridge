
$( document ).ready(function() {

  chrome.storage.sync.get({
    kimaiurl: ""
  }, function (items) {
    $('#kimaiurl').val(items.kimaiurl);
  });

  $("#save").on('click', function () {
    var url = $('#kimaiurl').val().replace(/\/$/, "");
    chrome.storage.sync.set({
      kimaiurl: url
    }, function() {
      // Update status to let user know options were saved.
      $('#status').html('Options saved. You can close this tab now.');
      // this throws "Refused to evaluate a string as JavaScript because 'unsafe-eval'"...
      setTimeout(8000, window.close);
    });

    // I know this is wrong.  Rob has told me but I can't remember how to do it properly
    return false;
  });

  $("#test").on('click', function () {
    var url = $('#kimaiurl').val().replace(/\/$/, "");
    if (url.length == 0) {
      $('#status').html("Please enter a URL to a Kimai server");
      return false;
    }
    $('#status').html("Checking URL accessible...");
    $.ajax({
      url: url,
      success: function(){
        // It's a valid URL, check for a kimai manifest
        $('#status').html("Checking for a kimai manifest...");
        $.ajax({
          url: url + "/manifest.json",
          success: function(data){
            if (data.name != "Kimai Time-Tracker") {
              // That's not a kimai manifest!
              $('#status').html(
                  "Weird, there is a manifest file but it's not a Kimai manifest, " +
                  "check that the URL does point to a Kimai install"
              );
            } else {
              // It's a kimai, is the bundle installed
              $('#status').html("Checking bundle...");
              $.ajax({
                url: url + "/chrome/status",
                success: function(data){
                  if (data.name == "Kimai chrome plugin") {
                    $('#status').html("Looks good (version " + data.version + "), that value has been saved.");
                    chrome.storage.sync.set({kimaiurl: url});
                  } else {
                    // Unreachable?
                    $('#status').html("The bundle is installed but mis-configured, check installation of the bundle," +
                        "see <a href='https://github.com/tobybatch/ChromeKimaiBridge'>here</a>.");
                  }
                },
                error: function(){
                  $('#status').html("Can't find the chrome plugin, you may need to install the kimai plugin." +
                      "see <a href='https://github.com/tobybatch/ChromeKimaiBridge'>here</a>.");
                },
              });
            }
          },
          error: function(){
            $('#status').html("That does not look like a Kimai server, " +
                "make sure the URL points to the root of Kimai site");
          }
        })
      },
      error: function(){
        $('#status').html("That URL is inaccessible.");
      },
    });

    return false;
  });

  function testIsKimai(url) {}
  function testHasBundle(url) {}
})

// http://localhost/workspace/kimai/kimai_trello_bundle