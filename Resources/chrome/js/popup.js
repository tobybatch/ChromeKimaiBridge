function loadIframe(pageDetails) {
    chrome.storage.sync.get({
        kimaiurl: ""
    }, function (items) {
        checkKimaiUrl(items.kimaiurl);

        // Is this github or trello?
        var location = new URL(pageDetails.url);
        var hostname = location.hostname;
        var pathname = location.pathname;
        var path = pathname.split("/");
        var project = false;
        var issue = false;

        $("#debug").html("<div id='debug'></div>");
        // Trello is always turned on
        if (hostname == "trello.com") {
            console.log("On trello");
            var cardId = path[2];
            getTrelloCardData(location, cardId, items.kimaiurl)
        } else {
            // Send the URI to kimai and see what we get
            kurl = kimaiurl;
            $.ajax(url + "?uri=" + location)
                .success(function (data) {
                    var boardId = data['boardId'];
                    var cardId = data['cardId'];
                    kurl = kimaiurl;
                    if (boardId !== undefined) {
                        // build URL with card ID and board ID
                        kurl += "/chrome/popup/" + boardId + "/" + cardId;
                    }
                    $("#content").attr("src", kurl);
                })
                .fail(function (data) {
                    $("#content").attr("src", url)
                });
        }
    });
}

function getTrelloCardData(url, cardId, kimaiurl) {
    $.ajax(url + ".json")
        .success(function (data) {
            var boardId = data['idBoard'];
            kurl = kimaiurl;
            if (boardId !== undefined) {
                // build URL with card ID and board ID
                kurl += "/chrome/popup/" + boardId + "/" + cardId;
            }
            $("#content").attr("src", kurl);
        })
        .fail(function (data) {
            $("#content").attr("src", url)
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
        chrome.tabs.create({'url': "/html/options.html"});
    }

    // Check we get a 200 or a 403 from the kimai server
    $.ajax(kimaiurl)
        .fail(function (data) {
            chrome.tabs.create({'url': "/html/options.html?status=" + data.status});
        });
}

// On load check if the kimai url is set
chrome.storage.sync.get({
    kimaiurl: ""
}, function (items) {
    checkKimaiUrl(items.kimaiurl);
});

// Get the background page and ask it for the current URL
$(document).ready(function () {
    chrome.runtime.getBackgroundPage(function (eventPage) {
        eventPage.getPageDetails(loadIframe);
    });
});
