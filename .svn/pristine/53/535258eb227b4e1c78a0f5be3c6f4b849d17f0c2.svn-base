<?php
    
use Captioning\Format\SubripFile;

function HookVideo_tracksAllStaticsync_after_alt ($resource, $altfile="")
    {
    if(!is_array($altfile))
        {
        return false;
        }
    global $lang;
    if (mb_strtolower($altfile["extension"])=="srt")  
        {
        $newvtt["name"]=trim($altfile["name"])==""?str_replace("?", "VTT", $lang["fileoftype"]):str_ireplace("SRT", "VTT",$altfile["name"]);
        $newvtt["ref"] = add_alternative_file($resource, $newvtt["name"], $altfile["altdescription"], $altfile["basefilename"] . ".vtt", "vtt", $altfile["file_size"]);
        $newvtt["path"] = get_resource_path($resource, true, '', true, "vtt", -1, 1, false, '',  $newvtt["ref"]);
        
        try {
            $srt = new SubripFile($altfile["path"]);
            $srt->convertTo('webvtt')->save($newvtt["path"]);
            }
        catch(Exception $e)
            {
            echo "Error: ".$e->getMessage()."\n";
            }       
        }
    return true;        
    }
    
