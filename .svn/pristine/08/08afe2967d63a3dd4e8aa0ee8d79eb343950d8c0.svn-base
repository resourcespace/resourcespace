<script>
function downloadImageBankFile(element)
    {
    event.preventDefault();

    var form = jQuery('<form id="downloadImageBankFile"></form>')
        .attr("action", "<?php echo $baseurl; ?>/plugins/image_banks/pages/download.php")
        .attr("method", "get");

    form.append(jQuery("<input></input>").attr("type", "hidden").attr("name", "file").attr("value", element.href));
    form.append(jQuery("<input></input>").attr("type", "hidden").attr("name", "id").attr("value", jQuery(element).data("id")));

    form.appendTo('body').submit().remove();
    }

function createNewResource(event, element)
    {
    event.preventDefault();

    CentralSpaceShowProcessing();

    jQuery.ajax(
        {
        type: 'POST',
        url: "<?php echo $baseurl; ?>/plugins/image_banks/pages/ajax.php",
        data: {
            ajax: true,
            original_file_url: element.href,
            <?php echo generateAjaxToken("ImageBanks_createNewResource"); ?>
        },
        dataType: "json"
        }).done(function(response, textStatus, jqXHR) {
            var view_page_anchor = document.createElement("a");
            view_page_anchor.setAttribute("href", baseurl_short + "?r=" + response.data.new_resource_ref);
            CentralSpaceLoad(view_page_anchor, true, false);
        }).fail(function(data, textStatus, jqXHR) {
            if(data.status == 500 && typeof data.responseJSON.data.message === undefined)
                {
                styledalert(data.status, data.statusText);
                return;
                }
            styledalert(data.statusText, data.responseJSON.data.message);
        }).always(function() {
            CentralSpaceHideProcessing();
        });
    }
</script>