console.log("popup.js");

function loadIframe(kimaiurl, url) {
    // Is this github or trello?
    debugger;
    var hostname = url.hostname;
    var pathname = url.pathname;
    var path = pathname.split("/");
    var project = false;
    var issue = false;

    $("#debug").html("<div id='debug'></div>");
    if (hostname == "trello.com") {
        // get boardname and issue id
        // https://trello.com/c/Lx0R3RTA/28-trello-create-a-new-power-up
        var cardId = path[2];
        getTrelloCardData(url, cardId, kimaiurl)
    } else if (hostname == "github.com" && (path[3] == "issues" || path[3] == "pull") && path.length == 5) {
        console.log(path);
        project = path[1] + '-' + path[2];
        issue = path.join("-");
        // Just tidy this up, remove the leading -
        issue = issue.substring(1);
        url = items.kimaiurl + "/chrome/popup/" + project + "/" + issue;
        $("#content").attr("src", url);
    } else {
        // It's not github or trello show kimai front page and exit early
        $("#content").attr("src", kimaiurl);
        return;
    }
}

function getTrelloCardData(url, cardId, kimaiurl) {
  console.log(url + ".json");
    $.ajax(url + ".json")
        .success(function (data) {
          console.log(data);
            var boardId = data['idBoard'];
            if (typeof boardId !== "undefined") {
              // build URL with card ID and board ID
              url = kimaiurl + "/chrome/popup/" + boardId + "/" + cardId;
            } else {
              url = kimaiurl;
            }
            $("#content").attr("src", url);
        });
}

// var browser = browser || chrome
// Get the background page and ask it for the current URL
$(document).ready(function () {
    var storageItem = browser.storage.local.get('kimaiurl');
    storageItem.then((res) => {
        // check kimai url
        $.ajax(res.kimaiurl)
            .success(function (data) {
                browser.tabs.query({currentWindow: true, active: true})
                    .then((tabs) => {
                        // loadIframe(res.kimaiurl, tabs[0].url);
                        loadIframe(res.kimaiurl, "https://stackoverflow.com/questions/11594576/getting-current-browser-url-in-firefox-addon");
                    })
            })
            .fail(function (data) {
                $("#content").attr("src", "unreachable.html");
            });
    });
});

// http://localhost/workspace/kimai/kimai_trello_bundle/public