<?php

/**
 * Run any outstanding offline archive jobs
 *
 * @param bool $showoutput  Display progress?
 *
 * @return bool|array  TRUE if ok, array of messages if any errors encountered
 *
 */
function offline_archive_run_jobs($showoutput = false)
    {
    global $lang;
    if ($GLOBALS["offline_archive_archivepath"] == "")
        {
        return ["No archive location configured, please add in plugin configuration page"];
        }

    $archive_errors = [];
    $restore_errors = [];
    if($showoutput)
        {
        echo " - Checking for pending archive jobs..\n";
        }
    $pendingarchivejob=ps_query("SELECT archive_code, archive_date, archive_status FROM offline_archive WHERE archive_status=0 LIMIT 0,1");

    if(count($pendingarchivejob)<1)
        {
        if($showoutput)
            {
            echo " - There are no outstanding archive jobs\n";
            }
        }
    else
        {
        $archivecode=$pendingarchivejob[0]["archive_code"];
        if($showoutput)
            {
            echo " - Found pending archive job: " . $archivecode . "\n";
            echo " - Checking for resources with the archive pending status..\n";
            }
        $resourcestoarchive=ps_query("SELECT ref,file_extension,file_path FROM resource WHERE archive='1'");

        if(count($resourcestoarchive)<1)
            {
            ps_query("UPDATE offline_archive SET archive_status=2 WHERE archive_code= ?", ['s', $archivecode]);
            if($showoutput)
                {
                echo " - There are no resources pending archive\n";
                }
            }
        else
            {
            if (!is_dir($GLOBALS["offline_archive_archivepath"] . "/" . $archivecode)){mkdir($GLOBALS["offline_archive_archivepath"] . "/" . $archivecode, 0755, true);}
            foreach ($resourcestoarchive as $resourcetoarchive)
                {
                $ref=$resourcetoarchive["ref"];
                $extension=$resourcetoarchive["file_extension"];
                if ($resourcetoarchive["file_path"]!="")
                    {
                    $origdirname=dirname($resourcetoarchive["file_path"]);
                    $origfilepath=$GLOBALS["syncdir"] . "/" . $resourcetoarchive["file_path"];
                    $destinationdir=$GLOBALS["offline_archive_archivepath"] . "/" . $archivecode . "/" . $origdirname;
                    }
                else
                    {
                    $origfilepath=get_resource_path($ref,true,"",false,$extension);
                    $destinationdir=$GLOBALS["offline_archive_archivepath"] . "/" . $archivecode;
                    }

                if($showoutput)
                    {
                    echo " - archive file - copying from :-\n   " . $origfilepath . "\nto\n   " . $destinationdir . "\n";
                    echo " - creating " . $destinationdir . " if does not exist\n";
                    }
                if(!file_exists($origfilepath))
                    {
                    $errortext = "Failed to find resource file for resource #" . $ref . ". Skipping resource";
                    if($showoutput)
                        {
                        echo " - " . $errortext . "\n";
                        }
                    $archive_errors[] = $errortext;
                    continue;
                    }
                $filename=basename($origfilepath);
                $destinationfile=$destinationdir . "/" . $filename;
                if (!is_dir($destinationdir)){mkdir($destinationdir, 0755, true);}
                if($GLOBALS["offline_archive_preservedate"])
                    {$modtime=filemtime($origfilepath);}
                copy($origfilepath,$destinationfile);
                if (file_exists($destinationfile))
                    {
                    if($GLOBALS["offline_archive_preservedate"])
                        {touch($destinationfile,$modtime);}
                    if($showoutput)
                        {
                        echo " - Successfully copied resource id #" . $ref. ". Deleting original file.\n";
                        }
                    // OK to delete existing file
                    try_unlink($origfilepath);

                    // Add archive code to resource metadata
                    update_field($ref,$GLOBALS["offline_archive_archivefield"],$archivecode);
                    ps_query("UPDATE resource SET archive='2' WHERE ref= ?", ['i', $ref]);
                    resource_log($ref,"s",0,$lang['offline_archive_resource_log_archived'] . $archivecode,1,2);
                    }
                else
                    {
                    // Copy failed - generate warning
                    $errortext = "Failed to copy resource id #" . $ref . ". Failed to copy to destination: " . $destinationdir;
                    if($showoutput)
                        {
                        echo " - " . $errortext . "\n";
                        }
                    $archive_errors[] = $errortext;
                    }

                }
            ps_query("UPDATE offline_archive SET archive_status=2 WHERE archive_code= ?", ['s', $archivecode]);
            } // Finish archive
        } // End of archive section

    //  Check for restore jobs
    if($showoutput)
        {
        echo " - Checking for pending restore jobs..\n";
        }
    $pendingrestores=ps_query("SELECT ref,file_extension,file_path FROM resource WHERE pending_restore=1");
    if(count($pendingrestores)==0)
        {
        if($showoutput)
            {
            echo " - There are no resources marked for restoration from archive\n";
            }
        }
    else
        {
        if($showoutput)
            {
            echo " - Found resources marked for restoration from archive\n";
            }
        foreach($pendingrestores as $pendingrestore)
            {
            $ref=$pendingrestore["ref"];
            $extension=$pendingrestore["file_extension"];

            if($showoutput)
                {
                echo " - Attempting to restore resource #" . $ref . "\n";
                }

            $archivecode = get_data_by_field($ref,$GLOBALS["offline_archive_archivefield"]);
            if(trim($archivecode) == "")
                {
                $restore_errors[]="Invalid archive code found\n";
                continue;
                }

            $archivepath=$GLOBALS["offline_archive_archivepath"] . "/" . $archivecode;
            if($showoutput)
                {
                echo " - Checking for archive folder at " . $archivepath . "\n";
                }
            if (is_dir($archivepath))
                {
                if($showoutput)
                    {
                    echo " - Found archive folder\n";
                    }
                //Found archive folder - look for file
                if ($pendingrestore["file_path"]!="")
                    {
                    $origdirname=dirname($pendingrestore["file_path"]);
                    if($GLOBALS["offline_archive_restorepath"] != "")
                        {
                        $dirpath=$GLOBALS["offline_archive_restorepath"] . "/" . $pendingrestore["file_path"];
                        //Check if has been restored/rearchived before in which case we need to remove duplicate restore folder prefix
                        if(strpos($pendingrestore["file_path"],$GLOBALS["offline_archive_restorepath"])===0)
                            {
                            if($showoutput)
                                {
                                echo " - Resource has been restored/rearchived before - we need to remove any duplicate restore folder prefix \n";
                                }
                            $dirpath=substr($dirpath,strlen($GLOBALS["offline_archive_restorepath"])+1);
                            }
                        }
                    else
                        {
                        $dirpath=$pendingrestore["file_path"];
                        }

                    $restorefile=$GLOBALS["syncdir"] . "/" . $dirpath;
                    $restoredir=dirname($restorefile);
                    if (!is_dir($restoredir)){mkdir($restoredir, 0755, true);}
                    $archivedir=$GLOBALS["offline_archive_archivepath"] . "/" . $archivecode . "/" . $origdirname;
                    }
                else
                    {
                    $restorefile=get_resource_path($ref,true,"",false,$extension);
                    $archivedir=$GLOBALS["offline_archive_archivepath"] . "/" . $archivecode;
                    }
                $filename=basename($restorefile);
                $archivefile=$archivedir . "/" . $filename;
                if($showoutput)
                    {
                    echo " - Checking for archive file at " . $archivefile . "\n";
                    }
                if (file_exists($archivefile))
                    {
                    // OK to copy to original location
                    if($showoutput)
                        {
                        echo " - Found archive file, copying to " . $restorefile . "\n";
                        }
                    if($GLOBALS["offline_archive_preservedate"])
                        {$modtime=filemtime($archivefile);}
                    copy($archivefile,$restorefile);
                    if (file_exists($restorefile))
                        {
                        if($GLOBALS["offline_archive_preservedate"])
                            {touch($restorefile,$modtime);}
                        if($showoutput)
                            {
                            echo " - Successfully restored resource id #" . $ref. "\n";
                            }
                        ps_query("UPDATE resource SET archive='0', pending_restore=0 WHERE ref= ?", ['i', $ref]);
                        if ($pendingrestore["file_path"]!="")
                            {
                            if($showoutput)
                                {
                                echo " - Staticsync file - updating file_path\n";
                                }
                            ps_query("UPDATE resource SET file_path= ? WHERE ref= ?", ['s', $dirpath, 'i', $ref]);
                            }
                        resource_log($ref,"s",0,$lang['offline_archive_resource_log_restored'],2,0);
                        }
                    else
                        {
                        // Copy failed - generate warning
                        $restore_errors[]="Failed to restore resource id #" . $ref . ". Failed to restore to destination: " . $destinationdir . "\n";
                        }
                    }
                else
                    {
                    // Failed to find archive file, on to next
                    $restore_errors[]="Failed to find archive file at " . $archivefile . "\n";
                    }
                }
            else
                {
                // Failed to find archive folder, on to next
                $restore_errors[]="Failed to find archive at " . $archivepath . "\nPlease ensure correct archive folder/tape/disk is present";
                }
            }
        }
    if(count($archive_errors) > 0 || count($restore_errors) > 0)
        {
        return array_merge($archive_errors,$restore_errors);
        }

    return true;
    }