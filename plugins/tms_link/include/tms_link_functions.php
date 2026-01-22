<?php

function unistr_to_ords($str, $encoding = 'UTF-8'){        
    // Turns a string of unicode characters into an array of ordinal values,
    // Even if some of those characters are multibyte.
    $str = mb_convert_encoding((string) $str,"UCS-4BE",$encoding);
    $ords = "";
    
    // Visit each unicode character
    for($i = 0; $i < mb_strlen($str,"UCS-4BE"); $i++){        
        // Now we have 4 bytes. Find their total
        // numeric value.
        $s2 = mb_substr($str,$i,1,"UCS-4BE");                    
        $val = unpack("N",$s2);            
        $ords.= $val[1];                
    }        
    return $ords;
}

function getEncodingOrder()
   {
   $ary[] = 'UTF-32';
   $ary[] = 'UTF-32BE';
   $ary[] = 'UTF-32LE';
   $ary[] = 'UTF-16';
   $ary[] = 'UTF-16BE';
   $ary[] = 'UTF-16LE';
   $ary[] = 'UTF-8';
   $ary[] = 'ASCII';
   $ary[] = 'ISO-2022-JP';
   $ary[] = 'JIS';
   $ary[] = 'windows-1252';
   $ary[] = 'windows-1251';
   $ary[] = 'UCS-2LE';
   $ary[] = 'SJIS-win';
   $ary[] = 'EUC-JP';
    
   return $ary;
   }

function tms_convert_value($value, $key, array $module)
    {
    $tms_rs_mapping_index = array_search($key, array_column($module['tms_rs_mappings'], 'tms_column'));
    if($tms_rs_mapping_index !== false)
        {
        $mappings=$module['tms_rs_mappings'];
        $mappings=array_values($mappings);
        if (strtoupper($mappings[$tms_rs_mapping_index]["encoding"]) == "AUTODETECT")
            {
            return mb_convert_encoding((string) $value, 'UTF-8', mb_detect_encoding((string) $value, getEncodingOrder()));
            }
        elseif(strtoupper($mappings[$tms_rs_mapping_index]["encoding"]) != "UTF-8")
            {
            return mb_convert_encoding((string) $value, 'UTF-8', $mappings[$tms_rs_mapping_index]['encoding']);
            }
        else
            {
            return $value;
            }
        }

    // Check if field is defined as UTF-16 or it's not an UTF-8 field
    if(in_array($key, $GLOBALS['tms_link_text_columns']) || !in_array($key, $GLOBALS['tms_link_numeric_columns']))
        {
        return mb_convert_encoding((string) $value, 'UTF-8', 'UCS-2LE');
        }

    return $value;
    }


function tms_link_get_tms_data($resource, $tms_object_id = "", $resourcechecksum = "", $onlymodule="")
    {
    global $lang, $tms_link_dsn_name,$tms_link_user,$tms_link_password;

    debug('tms_link: tms_link_get_tms_data() $resource=' . $resource . ' $tms_object_id=' . json_encode($tms_object_id) . ' $resourcechecksum=' . $resourcechecksum . ' $onlymodule=' . $onlymodule);

    if(is_int_loose($resource))
        {
        $resource_data = get_resource_data($resource);
        if(!$resource_data)
            {
            debug('tms_link: unable to get resource data for resource: ' . $resource);
            return $lang["resourceidnotfound"];
            }
        }

    $conn = odbc_connect($tms_link_dsn_name, $tms_link_user, $tms_link_password);
    if(!$conn)
        {
        $error = odbc_errormsg();
        debug('tms_link: odbc connection error: ' . $error);
        return $error;
        }

    $modules_mappings = tms_link_get_modules_mappings();
    $convertedtmsdata = array();

    foreach ($modules_mappings as $module) {
        if(trim($onlymodule) != "" && $onlymodule != $module['module_name'])
            {
            continue;
            }
        if(isset($resource_data) && !in_array($resource_data["resource_type"],$module["applicable_resource_types"])) 
            {
            // Not valid module for this resource
            continue;
            }
        // Get TMS UID value we have for this resource
        if($tms_object_id == "")
            {
            $tms_object_id = get_data_by_field($resource, $module['rs_uid_field']);
            }

        if($tms_object_id == "" || empty($module['tms_rs_mappings']))
            {
            continue;
            }

        $tms_object_id = tms_link_split_identifiers($tms_object_id);

        if ($module['tms_uid_field_int'] ?? true) {
            $tms_object_id = array_filter($tms_object_id,'is_positive_int_loose');
        }
        if (empty($tms_object_id)) {
            debug('tms_link: Invalid tms object id(s): ' . json_encode($tms_object_id) . ' for resource: ' . $resource);
            continue;
        }
        $conditionsql = " WHERE {$module['tms_uid_field']} IN ('" . implode("','", $tms_object_id) . "')";

        $tmscountsql = "SELECT Count(*) FROM {$module['module_name']} {$conditionsql};";
        debug('tms_link: tms count query to odbc: ' . $tmscountsql);
        $tmscountset = odbc_exec($conn, $tmscountsql);
        $tmscount_arr = odbc_fetch_array($tmscountset);
        $resultcount = end($tmscount_arr);
        if($resultcount == 0)
            {
            debug('tms_link: No data returned from tms for tms object id(s): ' . json_encode($tms_object_id));
            odbc_close($conn);
            return $lang["tms_link_no_tms_data"];
            }

        $columnsql = $module['tms_uid_field'];
        foreach($module['tms_rs_mappings'] as $tms_rs_mapping)
            {
            if(trim($columnsql) != '')
                {
                $columnsql .= ", ";
                } 
            if(strtoupper($tms_rs_mapping["encoding"]) != "UTF-8")
                {
                $columnsql .= "CAST (" . $tms_rs_mapping['tms_column'] . " AS VARBINARY(MAX)) " . $tms_rs_mapping['tms_column'];
                }
            else
                {
                $columnsql .= $tms_rs_mapping['tms_column'];
                }  
            }
        $tmssql = "SELECT {$columnsql} FROM {$module['module_name']} {$conditionsql};";
        debug('tms_link: tms column query to odbc: ' . $tmssql);
        $tmsresultset = odbc_exec($conn, $tmssql);

        for($r = 1; $r <= $resultcount; $r++) {
            $tmsdata = odbc_fetch_array($tmsresultset, $r);

            if (!$tmsdata)
                {
                $r++;
                debug("tms_link: unable to fetch array for tms query row $r in query: $tmssql");
                continue;
                }

            foreach ($tmsdata as $key => $value) {
                $convertedtmsdata[$module['module_name']][$r][$key] = tms_convert_value($value, $key, $module);
            }
        }
    }

    odbc_close($conn);
    return $convertedtmsdata;
    }

function tms_link_get_tms_resources(array $module)
    {
    $module_rtfs = array_filter(array_filter(array_map('intval', [$module['rs_uid_field'], $module['checksum_field']])), 'is_int_loose');
    if(empty($module_rtfs))
        {
        return [];
        }

    $sql_rtf_in = ps_param_insert(count($module_rtfs));

    return ps_query(
        "   SELECT rn.resource,
                   GROUP_CONCAT(CASE WHEN n.resource_type_field = ? THEN n.`name` ELSE NULL END SEPARATOR ', ') AS identifier,
                   max(CASE WHEN n.resource_type_field = ? THEN n.`name` ELSE NULL END) AS `checksum`
              FROM resource_node AS rn
        INNER JOIN node AS n ON rn.node = n.ref AND n.resource_type_field IN ({$sql_rtf_in})
             WHERE rn.resource > 0
          GROUP BY rn.resource
          HAVING identifier IS NOT NULL
          ORDER BY rn.resource",
        array_merge(
            [
                'i', $module['rs_uid_field'],
                'i', $module['checksum_field'],
            ],
            ps_param_fill($module_rtfs, 'i')
        )
    );
    }

function tms_link_test()
    {
    global $tms_link_dsn_name,$tms_link_user,$tms_link_password, $tms_link_checksum_field, $tms_link_table_name,$tms_link_object_id_field, $tms_link_text_columns, $tms_link_numeric_columns;  
    $conn=odbc_connect($tms_link_dsn_name, $tms_link_user, $tms_link_password);

    if($conn)
        {
        $tmssql = "SELECT TOP 10 * FROM " . $tms_link_table_name . " ;";

        // Execute the query to get the data from TMS
        $tmsresultset = odbc_exec($conn,$tmssql);

        $resultcount=odbc_num_rows ($tmsresultset);
        if($resultcount == 0)
            {
            global $lang;
            odbc_close($conn);
            return $lang["tms_link_no_tms_data"];
            }

        $convertedtmsdata=array();
        for ($r=1;$r<=$resultcount;$r++)
            {
            $tmsdata=odbc_fetch_array ($tmsresultset,$r);
            foreach($tmsdata as $key=>$value)
                {
                $convertedtmsdata[$key]=$value;
                }
            }

        odbc_close($conn);
        return $convertedtmsdata;
        }
    else
        {
        $error=odbc_errormsg();
        exit($error);
        return $error;
        }
    }

function tms_add_mediaxref($mediamasterid,$tms_object_id,$create=true)
  {
  global $conn,$tms_link_tms_loginid;
  
  // Check if the file already exists
  $tmssql = "select MediaXrefID FROM MediaXRefs where MediaMasterID='" . $mediamasterid . "' and ID='" . $tms_object_id . "' and TableID='108'";
  debug("tms_link: SQL - " . $tmssql);
  $mediaxrefresult=odbc_exec($conn,$tmssql);

  if(!$mediaxrefresult)
    {
    return false;
    }
  $mediaxrefs=array();

  while($row = odbc_fetch_array($mediaxrefresult))
    {
    $mediaxrefs[] = $row["MediaXrefID"];
    }
  if(count($mediaxrefs)>0)
    {
    return $mediaxrefs[0];
    }
  elseif($create)
    {
    $tmssql="INSERT INTO MediaXRefs (MediaMasterID, ID, TableID, Rank, PrimaryDisplay, LoginID) values ('" . $mediamasterid . "', '" . $tms_object_id  . "', 108, 1, 0, '" . $tms_link_tms_loginid. "')";
    debug("tms_link: SQL - " . $tmssql);
    $tms_update_mediaxrefs=odbc_exec($conn,$tmssql);
    if(!$tms_update_mediaxrefs)
      {
      $errormessage=odbc_errormsg();
      exit($errormessage);
      }
    return tms_add_mediaxref($mediamasterid, $tms_object_id, false);
    }
  else
    {
    return false;
    }
  
  } 
  
function tms_link_create_tms_thumbnail($resource, $alternative=-1)
    {
    global $conn,$tms_link_dsn_name,$tms_link_user,$tms_link_password, $tms_link_checksum_field, $tms_link_table_name,$tms_link_object_id_field, $tms_link_text_columns, $tms_link_numeric_columns, $tms_link_tms_loginid,$storagedir, $tms_link_media_path, $tms_link_push_image_sizes;
    
    //  Set up connection, need to increase bytes returned
    ini_set("odbc.defaultlrl", "100K");
    $conn=odbc_connect($tms_link_dsn_name, $tms_link_user, $tms_link_password);

    if($conn)
        {
        // Check if we already have a TMS ID
        $modules_mappings = tms_link_get_modules_mappings();
        foreach($modules_mappings as $module)
            {
            if($module['tms_uid_field'] == "ObjectID")
                {
                $idfield = $module['rs_uid_field'];
                break;
                }
            }

        if(!isset($idfield))
            {
            odbc_close($conn);
            return false;
            }
        $tms_object_id = get_data_by_field($resource, $idfield);
        if($tms_object_id == "")
            {
            odbc_close($conn);
            return false; // No TMS ID found, we can't add the image to TMS
            }

        // Get TMS Path ID of filestore path
        $pathid=tms_get_mediapathid($tms_link_media_path);
        debug("tms_link: Found PathID for " . $tms_link_media_path . " - " . $pathid);
      
        // Get details of the image to send to TMS
        foreach($tms_link_push_image_sizes as $tms_link_push_image_size)
            {
            $preview_path=get_resource_path($resource,true,$tms_link_push_image_size,false,'jpg',-1,1,false,'',$alternative);
            if(file_exists($preview_path) && filesize_unlimited($preview_path)<65536)
                {
                if(isset($storagedir) && $storagedir!="")
                    {
                    $tmsrelfilepath=substr($preview_path,strlen($storagedir) + 1);
                    }
                else
                    {
                    $tmsrelfilepath=substr($preview_path,strpos($preview_path,'filestore')+10);
                    }
                break;
                }
            }
        if(!isset($tmsrelfilepath))
            {
            debug("tms_link: No valid image files found to be uploaded");
            odbc_close($conn);
            return false;
            }

        // Check if mediafile already exists
        $existingmediafile=tms_check_thumb($pathid,$preview_path,$tmsrelfilepath);    
        if($existingmediafile!==false)
            {
            // Update MediaRenditions with new thumbnail and return as everything else stays the same
            debug("tms_link: Found existing media record for Object ID #" . $tms_object_id . " - Mediamaster: " . $existingmediafile["MediaMasterID"]);
            tms_update_media_rendition_thumb($existingmediafile["MediaMasterID"],$existingmediafile["PrimaryFileID"], $pathid, $preview_path,$tmsrelfilepath);
            odbc_close($conn);
            return true;
            }

         // No existing record for the path defined, Add a new record

        // ============================ MediaMaster Table ================================
        // Get a MediaMaster record ID to use, if there is not one unused then create one
        $mediamasterid = tms_get_mediamasterid(true,$resource);
        if(!$mediamasterid)
            {
            debug("tms_link: ERROR: Unable to get a MediaMasterID. ");
            odbc_close($conn);
            return false;
            }
        debug("tms_link: Using MediaMasterID: " . $mediamasterid);

        // ============================ MediaRenditions Table ============================
        $renditionid=tms_get_renditionid($mediamasterid,$resource,true);
        if(!$renditionid)
            {
            debug("tms_link: ERROR - Unable to create a new RenditionID. ");
            odbc_close($conn);
            return false;
            }
        debug("Using RenditionID: " . $renditionid);

        // UPDATE MediaMaster with new value
        $tmssql="UPDATE MediaMaster Set DisplayRendID = '" . $renditionid . "', PrimaryRendID = '" . $renditionid . "' WHERE MediaMasterID = '" . $mediamasterid . "'";
        odbc_exec($conn,$tmssql);
        $tms_set_rendition=odbc_exec($conn,$tmssql);
        if(!$tms_set_rendition)
            {
            $errormessage=odbc_errormsg();
            debug("tms_link: SQL = " . $tmssql);
            debug("tms_link: Unable to update MediaMaster table with RenditionID " . $errormessage);
            odbc_close($conn);
            return false;
            }

        $mediafileid=tms_add_mediafile($renditionid,$pathid,$preview_path,$tmsrelfilepath,true);
        debug("tms_link: added new mediafile in MediaXRefs. Media FileID: " . $mediafileid);
        
        // Update MediaRenditions with new mediafile
        $updaterendition=tms_update_media_rendition($mediamasterid,$mediafileid);
        if(!$updaterendition)
            {
            debug("tms_link: ERROR: Unable to update media rendition");
            odbc_close($conn);
            return false;
            }

        // Update MediaRenditions with new thumbnail
        tms_update_media_rendition_thumb($mediamasterid,$mediafileid, $pathid, $preview_path,$tmsrelfilepath); 

        // ============================ MediaXRefs Table - Create Link to TMS Objects Module
        $mediaxrefid=tms_add_mediaxref($mediamasterid,$tms_object_id,true);
        if(!$mediaxrefid)
            {
            debug("tms_link: ERROR: Unable to create row in MediaXRefs");
            odbc_close($conn);
            return false;
            }
        }
    else
        {
        $error=odbc_errormsg();
        exit($error);
        return false;
        }
    }

/**
 * tms_get_mediamasterid
 * Retrieve a list of unused Media Master IDs from the TMS database and return the first available.
 * If no IDs are found then create a new one and then retry
 * To avoid Media Master IDs being used by multiple resources $tms_link_mediapaths_resource_reference_column can be set.
 * This will store the resource ID in the MediaMaster table when creating the new ID so that it is not used by another Resource
 *
 * @param  bool $create    flag to create a new ID if none found
 * @param  int  $resource  ResourceSpace resource ID
 * @return bool|string      False if no ID found, otherwise the Media Master ID is returned
 */
function tms_get_mediamasterid(bool $create = true, ?int $resource = null)
  {
  global $conn, $errormessage, $tms_link_tms_loginid,$tms_link_mediapaths_resource_reference_column ;

  if (!preg_match('/^[a-zA-Z0-9_]*$/', $tms_link_tms_loginid)) {
          debug('tms_link: $tms_link_tms_loginid can only contain letters, numbers or underscore.');
          return false;
        }

  // Get the latest inserted ID that we have not used
  $tmssql = "SELECT MediaMasterID FROM MediaMaster 
      WHERE LoginID = '" . $tms_link_tms_loginid . "'
        AND DisplayRendID='-1'
        AND PrimaryRendID='-1'";

  if ($tms_link_mediapaths_resource_reference_column != "" && $resource !=null)
      {
      if (!preg_match('/^[a-zA-Z0-9_]*$/', $tms_link_mediapaths_resource_reference_column))
          {
          debug('tms_link: $tms_link_mediapaths_resource_reference_column can only contain letters, numbers or underscore.');
          return false;
          }
      $tmssql .= " AND $tms_link_mediapaths_resource_reference_column = CAST(CAST(" . (int) $resource . " AS VARCHAR(20)) AS INT)";
      }

  $mediamasterresult = odbc_exec($conn, $tmssql);

  if(!$mediamasterresult)
    {
    debug("tms_link: SQL = " . $tmssql);
    $errormessage=odbc_errormsg();
    debug("tms_link: ERROR = " . $errormessage);
    return false;
    }
  $mediamasterids=array();

  while($row = odbc_fetch_array($mediamasterresult))
    {
    $mediamasterids[] = $row["MediaMasterID"];
    }
  if(count($mediamasterids)>0)
    {
    debug("tms_link: FOUND " . count($mediamasterids) . " available MediaMasterIDs =" . implode(",",$mediamasterids));
    return $mediamasterids[0];
    }
  elseif($create)  
    {
    $insert_columns = ["LoginID","DisplayRendID","PrimaryRendID"];
    $insert_values  = [$tms_link_tms_loginid,-1,-1];

    if ($tms_link_mediapaths_resource_reference_column != "" && $resource !="")
      {
      $insert_columns[]=$tms_link_mediapaths_resource_reference_column;
      $insert_values[]=$resource;
      }
    
    $insert_columns = implode(",",$insert_columns);
    $insert_values= implode("','",$insert_values);

    $tmssql="INSERT INTO MediaMaster ($insert_columns) VALUES ('$insert_values')";
    $tmsinsert=odbc_exec($conn,$tmssql);
    if(!$tmsinsert)
      {
      $errormessage=odbc_errormsg();
      debug("tms_link: ERROR = " . $errormessage);
      return false;
      }
    return tms_get_mediamasterid(false, $resource);
    }
  else  
    {
    return false;
    }
  }  
  
    
function tms_get_renditionid($mediamasterid,$resourceid,$create=true)
  {
  global $conn, $tms_link_tms_loginid;
  // Get the latest ID that we have not used
  $tmssql = "select RenditionID, RenditionNumber,SortNumber,MediaTypeID,ParentRendID,LoginID FROM MediaRenditions where MediaMasterID='" . $mediamasterid . "' and LoginID='" . $tms_link_tms_loginid . "'";
  $renditionresult=odbc_exec($conn,$tmssql);

  if(!$renditionresult)
    {
    debug("tms_link: SQL = " . $tmssql); 
    $errormessage=odbc_errormsg();    
    debug("tms_link: ERROR = " . $errormessage);  
    return false;
    }
  $renditionids=array();

  while($row = odbc_fetch_array($renditionresult))
    {
    $renditionids[] = $row["RenditionID"];
    }
  if(count($renditionids)>0)
    {
    debug("tms_link: FOUND " . count($renditionids) . " available RenditionIDs<br><ul><li>" . implode("</li><li>",$renditionids));
    return $renditionids[0];
    }
  elseif($create)  
    {
    $tmssql="INSERT INTO MediaRenditions (MediaMasterID, RenditionNumber,SortNumber,MediaTypeID,ParentRendID,LoginID) VALUES ('" . $mediamasterid . "', '" . $resourceid . "','" . $resourceid . "', 1, -1, '" . $tms_link_tms_loginid . "')";
    $tmsinsert=odbc_exec($conn,$tmssql);
    if(!$tmsinsert)
      {
      debug("tms_link: SQL = " . $tmssql);
      $errormessage=odbc_errormsg();
      debug("tms_link: ERROR = " . $errormessage);
      return false;
      }
    return tms_get_renditionid($mediamasterid, $resourceid, false);
    }
  else
    {
    return false;
    }
  }
  
  
function tms_get_mediapathid($path,$create=true)
  {
  global $conn,$tms_link_tms_loginid;
  $tmssql = "select PathID FROM MediaPaths where PhysicalPath = '" . $path . "'";
  $mediapathresult=odbc_exec($conn,$tmssql);
  if(!$mediapathresult)
    {
    debug("tms_link: SQL = " . $tmssql); 
    $errormessage=odbc_errormsg();    
    debug("tms_link: ERROR = " . $errormessage); 
    return false;
    }
   
  // Run query to check that we have some results            
  $tmscountsql = "SELECT Count(*) FROM MediaPaths where PhysicalPath = '" . $path . "'";
  $tmscountset = odbc_exec($conn,$tmscountsql);
  $tmscount_arr = odbc_fetch_array($tmscountset);
  $resultcount = end($tmscount_arr);
   
  if($resultcount>0)
    {
    $mediapathids = odbc_fetch_array($mediapathresult);
    return $mediapathids["PathID"];
    }
  else
    {
    if(!$create)
      {
      return false;  
      }
    debug("tms_link: creating media path for " . $path);
    $tmssql="INSERT INTO MediaPaths (Path,LoginID, PhysicalPath) VALUES ('" . $path . "','" . $tms_link_tms_loginid . "','" . $path . "')";
    $tmsinsert=odbc_exec($conn,$tmssql);
    if(!$tmsinsert)
      {
      debug("tms_link: SQL = " . $tmssql);
      $errormessage=odbc_errormsg();
      debug("tms_link: ERROR = " . $errormessage);
      return false;
      }
    return tms_get_mediapathid($path, false);
    }
  
  }


function tms_update_media_rendition($mediamasterid,$mediafileid)
  {
  global $conn;
  $tmssql="UPDATE MediaRenditions Set PrimaryFileID = '" . $mediafileid . "' where MediaMasterID = '" . $mediamasterid . "'";
  $tms_update_mediafile=odbc_exec($conn,$tmssql);
  if(!$tms_update_mediafile)
    {
    debug("tms_link: SQL = " . $tmssql); 
    $errormessage=odbc_errormsg();    
    debug("tms_link: ERROR = " . $errormessage); 
    return false;
    }
  return true;
  }

function tms_check_thumb($pathid,$filepath,$filename)
  {
  global $conn, $tmslocalpath;
  if(!file_exists($filepath)){return false;}
  
  $tmssql="SELECT MediaMasterID, RenditionID, PrimaryFileID FROM MediaRenditions WHERE ThumbPathID = '" . $pathid . "' and ThumbFileName='" . $filename . "'";
  debug("tms_link: SQL = " . $tmssql); 

  $tms_checkthumb_result=odbc_exec($conn,$tmssql);
  if(!$tms_checkthumb_result)
    {
    $errormessage=odbc_errormsg();
    exit($errormessage);
    }
  $thumbids=array();
  while($row = odbc_fetch_array($tms_checkthumb_result))
    {
    $thumbids[] = $row;
    }
  if(count($thumbids)>0)
    {
    return $thumbids[0];
    }
    debug("tms_link: No match found for existing preview file"); 

  return false;
  }
  
  
function tms_update_media_rendition_thumb($mediamasterid,$mediafileid, $pathid, $filepath,$filename)
  {
  global $conn, $tmslocalpath;
  if(!file_exists($filepath)){return false;}
  
  $imagesize=filesize ($filepath);
  $imagebinarydata=file_get_contents($filepath);  
  
    $unpacked = unpack('H*hex', $imagebinarydata);
    $safeimagebinarydata =  '0x' . $unpacked['hex'];
  
  debug("tms_link: Adding thumbnail from " . $filepath);
    
  $tmssql="UPDATE MediaRenditions Set ThumbPathID = '" . $pathid . "', ThumbFileName='" . $filename . "',ThumbBlob=" . $safeimagebinarydata . ",ThumbBlobSize='" . $imagesize . "'";
      
  $tmssql .= " where MediaMasterID = '" . $mediamasterid . "' and PrimaryFileID = '" . $mediafileid . "'";
  
  $tms_update_thumb=odbc_exec($conn,$tmssql);
  if(!$tms_update_thumb)
    {
    $errormessage=odbc_errormsg();
    exit($errormessage);
    }
  return true;
  }
  
function tms_add_mediafile($renditionid,$pathid,$filepath,$relfilepath,$create=true)
  {
  global $conn,$tms_link_user;
  
  // Check if the file already exists
  $tmssql = "select FileID FROM MediaFiles where RenditionID='" . $renditionid . "' and PathID='" . $pathid . "' and Filename='" . $relfilepath. "' and LoginID='" . $tms_link_user. "'";
  debug("tms_link: " . $tmssql);
  $mediafileresult=odbc_exec($conn,$tmssql);

  if(!$mediafileresult)
    {
    return false;
    }
  $mediafileids=array();

  while($row = odbc_fetch_array($mediafileresult))
    {
    $mediafileids[] = $row["FileID"];
    }
  if(count($mediafileids)>0)
    {
    debug("Found existing mediafile");
    return $mediafileids[0];
    }
  elseif($create)
    {
    $imageinfo=getimagesize($filepath);
    $imagesize=filesize ($filepath);

    $imagepxwidth=$imageinfo[0];
    $imagepxheight=$imageinfo[1];

    // The following was worked out from existing TMS data. Memory size =(PixelH * PixelW * 3) + (PixelH * n) WHERE n is 1,2, or 3
    $imagememorysize=($imagepxwidth * $imagepxheight * 3) + ($imagepxheight * 3);

    $tmssql="INSERT INTO MediaFiles
      (RenditionID, PathID, Filename, FormatID, PixelH, PixelW, ColorDepthID, FileSize, MemorySize,  FileDate, LoginID, LockChecksum, IsConfidential) 
      values ('" . $renditionid . "','" . $pathid . "', '" . $relfilepath. "', 2, '" . $imagepxheight . "', '" . $imagepxwidth . "', 0, '" . $imagesize . "','" . $imagememorysize . "', GETDATE(), '" . $tms_link_user. "', 0, 0)";
    $tms_insert_mediafile=odbc_exec($conn,$tmssql);
    if(!$tms_insert_mediafile)
      {
      $errormessage=odbc_errormsg();
      exit($errormessage);
      }
    return tms_add_mediafile($renditionid, $pathid, $filepath, $relfilepath, false);
    }
  else
    {
    debug("tms_link: Unable to create new mediafile for rendition: " . $renditionid);
    return false;
    }
  
  }
  
function tms_show_data($table,$columns,$utf16_columns,$conditionsql,$limit=10)
  {
  global $conn;
  $all_columns=array_merge($columns,$utf16_columns);

  // Add normal value fields
  $columnsql="";
  if($limit!=0)
    {
    $columnsql.="TOP 10 ";  
    }
  $columnsql .= implode(", ", $columns);
  
  // Add SQL to get back text fields as VARBINARY(MAX) so we can sort out encoding later
  foreach ($utf16_columns as $utf16_column)
    {
    $columnsql.=", CAST (" . $utf16_column . " AS VARBINARY(MAX)) " . $utf16_column;
    }
  
  $tmssql = "SELECT " . $columnsql . " FROM TMS.dbo.". $table . " " . $conditionsql;
  
  
  // Execute the query to get the data from TMS
  $tmsresultset = odbc_exec($conn,$tmssql);
  if(!$tmsresultset)
    {
    $error=odbc_errormsg();
    exit($error);
    }
  
  // Run query to check that we have some results            
  $tmscountsql = "SELECT Count(*) FROM TMS.dbo.". $table . " " . $conditionsql;
  $tmscountset = odbc_exec($conn,$tmscountsql);
  $tmscount_arr = odbc_fetch_array($tmscountset);
  $resultcount = end($tmscount_arr);
  
  echo "<h2>TMS OUTPUT</h2>";
  echo "<p>QUERY: " . $tmssql . "</p>";
  echo "FOUND " . $resultcount . " rows";

  echo "<div class='Listview'>";
  echo "<table style='border:solid 1px;'>";
  echo "<tr>";
  foreach($all_columns as $field)
    {
    echo "<th style='border:solid 1px;'><strong>" . $field . "</strong></th>";
    }
  echo "</tr>";

  for ($r=1;$r<=$resultcount;$r++)
    {
    $tmsdata=odbc_fetch_array ($tmsresultset,$r);
      
      echo "<tr>";
      foreach($all_columns as $field)
        {  
        if($field=="ThumbBLOB")
          {
          echo "<td  style='border:solid 1px;'><img src='data:image/jpeg;base64," . base64_encode($tmsdata[$field]) . "'/></td>";
          }
        else
          {
          echo "<td  style='border:solid 1px;'>" . $tmsdata[$field] . "</td>";
          }
        }
      echo "</tr>";
    }
  echo "</table>";
  echo "</div>";
  }
  
function tms_link_check_preview($ref, $alternative=-1)
    {
    global $tms_link_push_image,$tms_link_push_condition;
    if(!$tms_link_push_image){return false;}
    
    $metadata=get_resource_field_data($ref,false,false);
    
    $matchedfilter=false;
    for ($n=0;$n<count($metadata);$n++)
        {
        $name=$metadata[$n]["name"];
        $value=$metadata[$n]["value"];          
        if ($name!="")
            {
            $match=filter_match($tms_link_push_condition,$name,$value);
            if ($match==1) {$matchedfilter=false;break;} 
            if ($match==2) {$matchedfilter=true;} 
            }
        }
    if(!$matchedfilter){return false;}
    
    // Push condition has matched, add the preview image to TMS
    tms_link_create_tms_thumbnail($ref, $alternative);
    }

/**
* Save plugins' module saved mappings configuration on an ad-hoc basis
* 
* @uses get_plugin_config()
* @uses set_plugin_config()
* 
* @param mixed $value Configuration option new value
* 
* @return void
*/
function tms_link_save_module_mappings_config($value)
    {
    $tms_link_config = get_plugin_config('tms_link');
    if(is_null($tms_link_config))
        {
        $tms_link_config = array();
        }
    $tms_link_config['tms_link_modules_saved_mappings'] = base64_encode(serialize($value));

    set_plugin_config('tms_link', $tms_link_config);
    }


function tms_link_get_modules_mappings()
    {
    return unserialize(base64_decode($GLOBALS['tms_link_modules_saved_mappings']));
    }


function tms_link_encode_modules_mappings()
    {
    return base64_encode(serialize($GLOBALS['tms_link_modules_saved_mappings']));
    }
    
function tms_link_is_rs_uid_field($field_ref)
    {
    $tms_rs_uid_field_index = array_search($field_ref, array_column(tms_link_get_modules_mappings(), 'rs_uid_field'));

    if($tms_rs_uid_field_index === false)
        {
        return false;
        }
    
    return true;
    }

/**
 * Split string identifier values on commas with optional whitespace for multiple tms object IDs
 * 
 * @param  string|array $tms_object_id
 * @return array
 */
function tms_link_split_identifiers(string|array $tms_object_id): array
{
    if (!is_array($tms_object_id)) {
        $tms_object_id = preg_split('/,\s*/', $tms_object_id) ?: [];
    }

    return $tms_object_id;
}