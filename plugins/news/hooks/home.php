<?php
function HookNewsHomeHomebeforepanels()
    {
    global $lang,$site_text,$baseurl;
    include_once __DIR__."/../inc/news_functions.php";
    $recent = 3;
    $news = get_news_headlines("",$recent);
    $results=count($news);
    ?>

    <div class="BasicsBox" id="NewsPanel">
        <h2><span class="icon-newspaper"></span>&nbsp;<?php echo escape($lang['title']); ?></h2>
        <?php
            if($results > 0)
            {
            for($n = 0; ($n < $results); $n++)
                {
                ?>
                <p><?php echo LINK_CARET; ?><a href="<?php echo $baseurl; ?>/plugins/news/pages/news.php?ref=<?php echo $news[$n]['ref']; ?>"><?php echo $news[$n]['title']; ?></a></p>
                <?php
                }
            }
        else
            {
            echo escape($lang['news_nonewmessages']);
            }
        ?>
    </div>
    <?php
    }

