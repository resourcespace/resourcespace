function metadataReport(ref, context)
    {
	jQuery('#' + context + 'MetadataReportSection').load(
		baseurl + "/pages/ajax/metadata_report.php?ref="+ref+"&context=" + context,
        function()
            {
            CentralSpaceHideProcessing();
            }
		);
	}
