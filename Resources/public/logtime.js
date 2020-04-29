$( document ).ready(function() {
    console.log( "ready!" );

    $(".btn-time").on("click", function() {
        var time = parseInt($("#form_duration").val() || 0);
        time += parseInt($(this).data('time'));
        if (time < 15) {
            time = 15;
        }
        $("#form_duration").val(time);
    });
});