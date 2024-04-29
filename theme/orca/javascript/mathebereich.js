// in dem farbigen Quadrat "LE 1" etc. auswählen und mit CSS-Klasse versehen
$(document).ready(function () {

    $.fn.wrapStart = function (numWords) {
        var node = this.contents().filter(function () {
                return this.nodeType == 3
            }).first(),
            text = node.text(),
            first = text.split(" ", numWords).join(" ");
        if (!node.length)
            return;
        node[0].nodeValue = text.slice(first.length);
        node.before('<span class=\"le\">' + first + '</span>');
    };
    $(".quadrat h3").wrapStart(2);
});

//OMB+ Chat
function openMathChat() {
    var chatWindow = window.open('https://beta.orca.nrw/moodle/chat.html', 'MathChat', 'width=500,height=500,menubar=no,scrollbars=no,toolbar=no');
    chatWindow.focus();
}