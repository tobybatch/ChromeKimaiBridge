
console.log("popup.js");

function loadIframe(pageDetails) {
    chrome.storage.sync.get({
    kimaiurl: ""
}, function(items) {
    checkKimaiUrl(items.kimaiurl);

    // Is this github or trello?
    var location = new URL(pageDetails.url);
    var hostname = location.hostname;
    var pathname = location.pathname;
    var path = pathname.split("/");
    var project = false;
    var issue = false;

    $("#debug").html("<div id='debug'></div>");
     if (hostname == "trello.com") {
        // get boardname and issue id
        // https://trello.com/c/Lx0R3RTA/28-trello-create-a-new-power-up
        var cardId = path[2];
        getTrelloCardData(location, cardId, items.kimaiurl)
    }
    else if (hostname == "github.com" && (path[3] == "issues" || path[3] == "pull") && path.length == 5) {
        console.log(path);
        project = path[1] + '-' + path[2];
        issue = path.join("-");
        // Just tidy this up, remove the leading -
        issue = issue.substring(1);
        url = items.kimaiurl + "/chrome/popup/" + project + "/" + issue;
        $("#content").attr("src", url);
    }
    else {
        // It's not github or trello show kimai front page and exit early
        $("#content").attr("src", items.kimaiurl);
        return;
    }
});
}

function getTrelloCardData(url, cardId, kimaiurl) {
  console.log(url + ".json");
    $.ajax(url + ".json")
        .success(function (data) {
          console.log(data);
            var boardId = data['idBoard'];
            // build URL with card ID and board ID
            url = kimaiurl + "/chrome/popup/" + boardId + "/" + cardId;
            $("#content").attr("src", url);
        });
}

/**
 * Check if the URL is a valid kimai install.
 * If not valid then open the options page to set it
 *
 * @param kimaiurl
 */
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

// On load check if the kimai url is set
chrome.storage.sync.get({
    kimaiurl: ""
}, function(items) {
    checkKimaiUrl(items.kimaiurl);
});

// Get the background page and ask it for the current URL
$( document ).ready(function() {
    chrome.runtime.getBackgroundPage(function(eventPage) {
        eventPage.getPageDetails(loadIframe);
    });
});
