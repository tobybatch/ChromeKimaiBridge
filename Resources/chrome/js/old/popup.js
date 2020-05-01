// This callback function is called when the content script has been 
// injected and returned its results
function onPageDetailsReceived(pageDetails)  { 
  chrome.storage.sync.get({
    kimaiurl: "",
    trelloApiKey: "bb74958f133bc2f1a54afc0ccebef56d",
    githubApiKey: "XXXX"
  }, function(items) {
    checkKimaiUrl(items.kimaiurl);

    // Is this github or trello?
    var location = new URL(pageDetails.url);
    var hostname = location.hostname;
    var pathname = location.pathname;
    var path = pathname.split("/");
    var project = false;
    var issue = false;

    if (hostname == "github.com" && pathname != "/") {
      project = path[1] + '-' + path[2];
      issue = path.join("-");
      // Just tidy this up, remove the leading -
      issue = issue.substring(1);
    }
    else if (hostname == "trello.com") {
      // get boardname and issue id
    }
    else {
      // It's not github or trello show kimai front page and exit early
      $("#content").attr("src", items.kimaiurl);
      return;
    }

    var url = items.kimaiurl + "/en/chrome-ext";

    if (project != undefined) {
      url += "/" + project;
      if (issue != undefined) {
        url += "/" + issue;
      }
    }

    $("#content").attr("src", url);
  });
} 

chrome.storage.sync.get({
  kimaiurl: ""
}, function(items) {
  checkKimaiUrl(items.kimaiurl);
});

function checkKimaiUrl(kimaiurl) {
  if (kimaiurl.length == 0) {
    chrome.tabs.create({'url': "/html/options.html" });
  }

  // Check we get a 200 or a 403 from the kimai server
  $.ajax(kimaiurl)
    .fail(function(data) {
      chrome.tabs.create({'url': "/html/options.html?status=" + data.status});
    });
}

$( document ).ready(function() {
  chrome.runtime.getBackgroundPage(function(eventPage) {
    eventPage.getPageDetails(onPageDetailsReceived);
  });
});
