// Saves options to chrome.storage

function getUrlVars()
{
  var vars = [], hash;
  var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
  for(var i = 0; i < hashes.length; i++)
  {
    hash = hashes[i].split('=');
    vars.push(hash[0]);
    vars[hash[0]] = hash[1];
  }
  return vars;
}

function kimaiUrlError(kiamiurl) {
      $("#status").html("There is a problem with the Kimai URL.  Check it is a valid kimai instance.");
      // $("#status").html("There is a problem with the Kimai URL.  Check this is a valid web page: <a href='" + kimaiurl + "'>" + kimaiurl + "</a>" );
}

$( document ).ready(function() {

  chrome.storage.sync.get({
    kimaiurl: ""
  }, function(items) {
    $('#kimaiurl').val(items.kimaiurl);
    if (items.kimaiurl.length > 0) {
      $('#message').text("I can't reach your Kimai instance.  Make sure it is up and reachable (do you need to be on a VPN?)");
    }
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

    // I know this is wrong.  Rob has told me but I can't remeber how to do it properley
    return false;
  });

  // Did we come here with an error?
  var vars = getUrlVars();
  if (vars.status != undefined) {
    chrome.storage.sync.get({
      kimaiurl: ""
    }, function(items) {
      kimaiUrlError(items.kimaiurl);
    });
  }

  // Check the URL and redirect if the kimai can't be found.
  chrome.storage.sync.get({
    kimaiurl: ""
  }, function(items) {
    $.ajax(items.kimaiurl)
      .fail(function(data) {
        kimaiUrlError(items.kimaiurl);
      })
  });

})
