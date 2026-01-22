<?php

function HookVideo_timestamp_linksViewFooterbottom()
    {
    ?>
    <script>
    // Find timestamps in fields and replace with links to jump the video.

   window.setTimeout(function () {
    var video=jQuery(".videojscontent video");
    if (video.length==1)
        {
        // There's a video tag.

        // For each field...
        var mfields=jQuery(".item p,.itemNarrow p,.CommentBody");
        mfields.each(function(index)
            {
            var newtext=this.innerHTML.replace(/([0-9][0-9]:[0-9][0-9]:[0-9][0-9])/gim,'<a href="#" onClick="VideoJumpTo(\'$1\');return false;">$1</a>');
            this.innerHTML=newtext;
            });   
        }
    },1000);

    function VideoJumpTo(timestamp)
        {
        // Convert timestamp to seconds.
        var s=timestamp.split(":");
        var ts=(Number(s[0])*60*60)+(Number(s[1])*60)+Number(s[2]);

        //Target the video and set the time.
        var video=jQuery(".videojscontent video")[0];
        video.currentTime=ts;video.play();

    document.getElementById('previewimagewrapper').scrollIntoView();

        }
    </script>
    <?php
    }
