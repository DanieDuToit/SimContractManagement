var i = 0;
var w;
"use strict";
$(document).ready(function () {
    $('#startworker').click(function () {
        console.log('Inside Start Worker');

        var ajaxurl = 'Worker.php',
            data = {'action': ''};
        $.post(ajaxurl, data, function (response) {
            console.log('Response: ', response)
            w.terminate();
            w = undefined;

            document.getElementById("resultMessage").innerHTML = response;
        });

        if (typeof(Worker) !== "undefined") {
            if (typeof(w) == "undefined") {
                w = new Worker("javascript/worker.js");
            }
            w.onmessage = function (event) {
                document.getElementById("result").innerHTML = event.data;
            };
        } else {
            document.getElementById("result").innerHTML = "Sorry, your browser does not support Web Workers...";
        }
    });
    $('#stopworker').click(function () {
        console.log('Inside Stop Worker');
        w.terminate();
        w = undefined;
        return false;
    });
});
