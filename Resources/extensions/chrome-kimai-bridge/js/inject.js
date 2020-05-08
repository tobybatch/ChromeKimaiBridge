// This is injected into the current page and uses the chrome event/listener
chrome.runtime.sendMessage({
    'title': document.title,
    'url': window.location.href
});