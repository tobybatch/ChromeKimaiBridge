$( document ).ready(function() {

  $("#save").on('click', function (e) {
    e.preventDefault();
    var url = $('#kimaiurl').val().replace(/\/$/, "");

    $.ajax(url)
      .success(function(data){
        browser.storage.local.set({
          kimaiurl: url
        });
      })
      .fail(function(data) {
        $("#message").text("Cannot connect to " + url + "(" + data.status + ")");
      });
  });

  var storageItem = browser.storage.local.get('kimaiurl');
  storageItem.then((res) => {
    $("#kimaiurl").val(res.kimaiurl);
  });
});
