var i = 0;

function timedCount() {
    var word = '';
    i++;
    if (i >= 20) i = 1;
    for (x = 0; x < i; x++) {
        word += '>';
    }
    postMessage(word);
    setTimeout("timedCount()", 500);
}

timedCount(); 