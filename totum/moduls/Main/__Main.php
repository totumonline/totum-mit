<div id="main-page"><?=$mainHtml?></div>
<script>

    $('#main-page').height(window.innerHeight - 165).niceScroll({
        cursorwidth: 7,
        mousescrollstep: 190,
        mousescroll: 190,
        autohidemode: false,
        enablekeyboard: true,
        cursoropacitymin: 1,
        railoffset: {left: 4}
    });

</script>