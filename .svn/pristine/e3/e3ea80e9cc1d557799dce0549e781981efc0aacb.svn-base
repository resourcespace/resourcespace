<?php /* -------- Text Box (formatted / TinyMCE) ---------------- */ 

global $tinymce_plugins, $tinymce_toolbar, $user_pref_appearance;

?>

<div style="display: inline-block; width: 100%;">
    <br />
    <textarea
        class="stdwidth"
        name="<?php echo escape($name); ?>"
        id="<?php echo ((isset($modal) && $modal) ? "Modal_" : "CentralSpace_") . escape($name); ?>"
        <?php echo $help_js; ?>
    ><?php if ($value == strip_tags($value)) {
        $value = nl2br($value);
     }
     echo escape(strip_tags_and_attributes($value, array("a"), array("href","target","rel","title")))?></textarea>
</div>

<script type="text/javascript">
    tinymce.remove('textarea#<?php echo ((isset($modal) && $modal) ? "Modal_" : "CentralSpace_") . escape($name); ?>');
    tinymce.init({
        selector: 'textarea#<?php echo ((isset($modal) && $modal) ? "Modal_" : "CentralSpace_") . escape($name); ?>',
        plugins: '<?php echo escape(check_tinymce_plugins($tinymce_plugins)); ?>',
        menubar: '',
        toolbar: "<?php echo escape(check_tinymce_toolbar($tinymce_toolbar)); ?>",
        font_size_formats: '5pt 5.5pt 6.5pt 7.5pt 8pt 9pt 10pt 10.5pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt',
        min_height: 400,
        max_height: 400,
        invalid_elements: 'script,iframe,embed,object,applet,meta,frame,frameset,link', //Explicitly removes Javascript-based elements
        invalid_attributes: 'on*', // Removes all 'on' event attributes
        license_key: 'gpl',
        promotion: false,
        branding: false,
        contextmenu: false, // Required to enable native copy and pasting
        <?php
        if (isset($user_pref_appearance)) {
            if ($user_pref_appearance == "dark") {
                ?>
                skin: "oxide-dark",
                content_css: "dark",
                <?php
            } elseif ($user_pref_appearance == "device") {
                ?>
                skin: (window.matchMedia("(prefers-color-scheme: dark)").matches ? "oxide-dark" : "oxide"),
                content_css: (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "default"),
                <?php
            }
        }
        ?>
        setup: (editor) => {
            editor.on('blur', function(e) {
                <?php
                if ($edit_autosave) {
                ?>
                if (tinymce.activeEditor.isDirty()) {
                    tinymce.activeEditor.save();
                    AutoSave('<?php echo $field["ref"]; ?>');                
                };
                <?php
                } else {
                ?>
                tinymce.activeEditor.save();
                <?php    
                }
                ?>
            });
            // Ensure that help text is shown when given focus
            editor.on('focus', function(e) {
                ShowHelp('<?php echo $field["ref"]; ?>');
            });
            editor.on('blur', function(e) {
                HideHelp('<?php echo $field["ref"]; ?>');
            });
        },
    });

</script>

