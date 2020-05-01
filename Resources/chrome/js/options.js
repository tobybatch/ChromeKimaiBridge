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
  });

  $("#save").on('click', function () {
    chrome.storage.sync.set({
      kimaiurl: $('#kimaiurl').val().replace(/\/$/, "")
    }, function() {
      // Update status to let user know options were saved.
      $('#status').html('Options saved.');
    });
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
