$(function()
{
    var timertext = $("[timer]");

    setInterval(function()
    {
        $.POST("/panel/timer/tim2.php", {type : "timerupdate"} , function(data)
        {
            timertext.html(data)
        });
    }, 1000);
});
