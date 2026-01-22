<style>
<?php
if(isset($bind_placeholders['refnumberfontsize']))
    {
    ?>
    page { font-size: <?php echo (int) $bind_placeholders['refnumberfontsize']; ?>px; }
    <?php
    }

if(isset($bind_placeholders['titlefontsize']))
    {
    ?>
    #pageTitle { font-size: <?php echo (int) $bind_placeholders['titlefontsize']; ?>px; }
    <?php
    }

$add_header = isset($bind_placeholders['contactsheet_header']) && ($bind_placeholders['contact_sheet_include_applicationname'] || isset($bind_placeholders['add_contactsheet_logo']));
?>

#logo { height: 50px; max-width: 100%; }
.centeredText { text-align: center; }

.resourceContainer { vertical-align: top; }
.resourcePreview { width: <?php echo $bind_placeholders['column_width']; ?>px; }
.contactsheet_textbold {font-weight: bold;}
</style>
<page
    <?php
    if ($add_header) {
        if($bind_placeholders['contact_sheet_include_applicationname']
                && isset($bind_placeholders['add_contactsheet_logo']) 
                && strlen($applicationname) > 23 ) {
            echo ' backtop="30mm"';
        } else {
            echo ' backtop="25mm"';
        }
    }
    if (isset($bind_placeholders['contact_sheet_footer'])) {
        echo ' backbottom="25mm"';
    }
    ?>
    >
<?php
if($add_header)
    {
    ?>
    <page_header>
        <table cellspacing="0" style="width: 100%;">
            <tr>
            	<?php
            	if($bind_placeholders['contact_sheet_include_applicationname']) {
                    
                    if (isset($bind_placeholders['add_contactsheet_logo'])) {
                        echo '<td style="width: 60%;">';
                    } else {
                        echo '<td style="width: 100%;">';
                    }                
                    ?>

                		<h1><?php echo escape($applicationname); ?></h1>
                	</td>
                <?php
                }
            if(isset($bind_placeholders['add_contactsheet_logo'])) {
            ?>
                <td style="width: 40%;" align=right>
                    <img id="logo" src="<?php echo $bind_placeholders['contact_sheet_logo']; ?>" alt="Logo" <?php if(isset($bind_placeholders['column_width_resize']) && $bind_placeholders['column_width_resize']){ ?> style="width:100%;height:auto;"<?php } ?>>
                </td>
            <?php
            }
            ?>
            </tr>
        </table>
        <hr>
    </page_header>
    <?php
    }

if(isset($bind_placeholders['contact_sheet_footer']))
    {
    ?>
    <page_footer>
        <hr>
        <table style="width: 100%;">
            <tr>
                <td class="centeredText" style="width: 90%">
                    <span><?php echo escape($lang['contact_sheet_footer_address']); ?></span>
                    <p><?php echo escape($lang['contact_sheet_footer_copyright']); ?></p>
                </td>
                <td style="text-align: right; width: 10%">[[page_cu]] of [[page_nb]]</td>
            </tr>
        </table>
    </page_footer>
    <?php
    }
    ?>


    <!-- Real content starts here -->
    <h3 id="pageTitle"><?php echo escape($bind_placeholders['title']); ?></h3>

<table>
<tbody>
<?php
global $contact_sheet_field_name, $contact_sheet_field_name_bold;

$row    = 1;
$column = 0;

$max_rows = ceil(count($bind_placeholders['resources']) / $bind_placeholders['columns']);

foreach($bind_placeholders['resources'] as $resource_ref => $resource)
    {
    if(0 == $column)
        {
        ?>
        <tr>
        <?php
        }
        ?>

    <td class="resourceContainer" width="<?php echo (int) $bind_placeholders['column_width']; ?>">
    <?php
    if($bind_placeholders['config_sheetthumb_include_ref'])
        {
        ?>
        <span class="resourceRef"><?php echo (int) $resource_ref; ?></span><br>
        <?php
        }

    if(!$bind_placeholders['contact_sheet_metadata_under_thumbnail']) {
		foreach($resource['contact_sheet_fields'] as $contact_sheet_field)
		{
            //Build string to wordwrap for column layout
            $csf_output = "";

            // If field name should be displayed...
            if($contact_sheet_field_name) {

                // ...check if it should be bolded or not
                if($contact_sheet_field_name_bold) {
                    $csf_output .= "<b>" . escape($contact_sheet_field['title']) . ": </b>";
                } else {
                    $csf_output .= escape($contact_sheet_field['title']) . ": ";
                }
            }
            
            // If field contains richtext...
            if ($contact_sheet_field['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
                // ...output in the same way as view.php, without escaping
                $csf_output .= '<br>' . strip_tags_and_attributes($contact_sheet_field['value'], ['a'], ['href', 'target']);
            } else {
                $csf_output .=  escape($contact_sheet_field['value']);
            }

			?>
			<span>
                <?php echo html_break_long_words($csf_output, (int)($bind_placeholders['column_width']/7)); ?>
            </span><br>
			<?php
		}
	}

    if(isset($bind_placeholders['contact_sheet_add_link']))
        {
        // IMPORTANT: having space between a tag and img creates some weird visual lines (HTML2PDF issues maybe?!)
        ?>
        <a target="_blank" href="<?php echo $baseurl; ?>/?r=<?php echo (int) $resource_ref; ?>"><img class="resourcePreview" src="<?php echo $resource['preview_src']; ?>" alt="Resource Preview"></a>
<?php
        }
    else
        {
        ?>
        <img class="resourcePreview" src="<?php echo $resource['preview_src']; ?>" alt="Resource Preview">
        <?php
        }
    
    if($bind_placeholders['contact_sheet_metadata_under_thumbnail']) {
		foreach($resource['contact_sheet_fields'] as $contact_sheet_field) {
                // If field name should be displayed...
                if($contact_sheet_field_name) {

                    // ...check if it should be bolded or not
                    if($contact_sheet_field_name_bold) {
                        ?><span><b><?php echo escape($contact_sheet_field['title']); ?></b>:
                        <?php 
                    } else {
                        ?><span><?php echo escape($contact_sheet_field['title']); ?>:
                        <?php 
                    }
                } else {
                    ?><span><?php
                }

                // If field contains richtext...
                if ($contact_sheet_field['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
                    // ...output in the same way as view.php, without escaping
                    $csf_output = strip_tags_and_attributes($contact_sheet_field['value'], ['a'], ['href', 'target']);
                    echo '<br>' . html_break_long_words($csf_output, (int)($bind_placeholders['column_width']/7)) . '<br></span>';
                } else {
                    echo html_break_long_words(escape($contact_sheet_field['value']), (int)($bind_placeholders['column_width']/7)) . '<br></span>';
                }
        }
	}
    ?>
    </td>

    <?php
    $column++;

    // We've reached the number of columns for this row
    if($column == $bind_placeholders['columns'])
        {
        ?>
        </tr>
        <?php
        $row++;
        $column = 0;
        }
    }

    if($row == $max_rows)
        {
        ?>
        </tr>
        <?php
        }
    ?>
</tbody>
</table>

</page>
