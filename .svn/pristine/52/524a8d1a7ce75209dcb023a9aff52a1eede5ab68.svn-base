<?php function HookGoogle_analyticsAllFootertop()
{

global $google_analytics_key, $use_google_analytics_4;

if (!is_array($google_analytics_key) || count($google_analytics_key)==0)
    {
    return false;
    }

if (!$use_google_analytics_4)
    {
?> 
<!-- Google Analytics (Universal Analytics) -->
<script type="text/javascript">
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', '<?php echo $google_analytics_key[0];?>', 'auto');  // Replace with your property ID.
ga('send','pageview');

</script>
<!-- End Google Analytics (Universal Analytics) -->
<?php
    }
else
    {
?>
<!-- Google tag (gtag.js) (Google Analytics 4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $google_analytics_key[0];?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '<?php echo $google_analytics_key[0];?>');

</script>
<!-- End Google tag (gtag.js) (Google Analytics 4) -->
<?php
    }

}


function HookGoogle_analyticsAllExtra_meta()
    {
    global $google_analytics_verification_code;

    if($google_analytics_verification_code == '')
        {
        return;
        }
    ?>
    <meta name="google-site-verification" content="<?php echo escape($google_analytics_verification_code); ?>" />
    <?php
    }
