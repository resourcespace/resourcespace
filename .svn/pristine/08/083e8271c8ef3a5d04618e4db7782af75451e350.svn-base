<?php
function google_visionProcess($resource, $verbose = false, $ignore_resource_type_constraint = false)
    {
    global $google_vision_api_key,$google_vision_label_field,$google_vision_landmarks_field,$google_vision_text_field,$google_vision_logo_field,$google_vision_restypes;
    global $baseurl,$google_vision_features, $google_vision_face_detect_field, $google_vision_face_detect_fullface, $google_vision_face_detect_verbose;
    
    if($google_vision_face_detect_field > 0)
        {
        $google_vision_features[] = "FACE_DETECTION";
        }
    $resource_data=get_resource_data($resource); # Load resource data (cached).

    if(
        $resource_data === false
        || (!in_array($resource_data["resource_type"], $google_vision_restypes) && !$ignore_resource_type_constraint))
        {
        return false;
        }
    
    # API URL
    if (substr($google_vision_api_key,0,4)=="http")
        {
        $url=$google_vision_api_key . "?baseurl=" . urlencode($baseurl); # Proxy support. Forward all requests via an intermediate service. Useful for RS hosts wishing to centralise image processing and also avoid revealing their key to all RS installations.
        }
    else
        {
        $url="https://vision.googleapis.com/v1/images:annotate?key=" . $google_vision_api_key;
        }
        
    # Find a suitable file
    $file=get_resource_path($resource,true,"pre");
    if (!file_exists($file)) {return false;} # No suitable file.
    
    # Fetch and encode the file.
    $data = file_get_contents($file);
    $base64 = base64_encode($data);
                                
    # Form the JSON request.
    $request='{
      "requests": [
        {
          "image": {
            "content": "' . $base64 . '"
          },
          "features": [
            {"type": "' . join('"},{"type": "', $google_vision_features) . '"}
            ]
        }
      ]
    }';
    
    debug("google_vision: \$request = {$request}");
    
    # Build a HTTP request, and fetch results.
    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
            'content' => $request,
            'ignore_errors' => true
        )
    );
    $context  = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);

    if ($verbose) {
        echo $result;
    }
        
    $result=json_decode($result,true); # Parse and return as associative arrays
    debug("google_vision: \$result = " . print_r($result, true));
    
    if(isset($result['error']))
        {
        debug('google vision error: ' . $result['error']['code'] . ': ' . $result['error']['message']);
        return false;
        }
        
    $nodes=array();
    $title="";
    
    #--------------------------------------------------------
    # Process annotations
    #--------------------------------------------------------
    if (isset($result["responses"][0]["labelAnnotations"]))      
        {
        # Keywords found. Loop through them and resolve node IDs for each, or add new nodes if no matching node exists.

        # Form labels into array.
        $labels_raw=$result["responses"][0]["labelAnnotations"];
        $labels=array();
        foreach ($labels_raw as $label) {$labels[]=$label["description"];}

        # Translation option
        global $google_vision_translation_api_key;
        if (isset($google_vision_translation_api_key) && $google_vision_translation_api_key!="")
            {
            $labels=google_visionTranslate($labels);
            }

        foreach ($labels as $label)
            {
            # Create new or fetch existing node
            $nodes[]=set_node(null, $google_vision_label_field, ucfirst($label), null, 9999);  
           if ($title=="") {$title=$label;}
            }
                
        add_resource_nodes($resource,$nodes);
        }
  
    #--------------------------------------------------------
    # Process landmarks
    #--------------------------------------------------------
    if (isset($result["responses"][0]["landmarkAnnotations"]))      
        {
        # Keywords found. Loop through them and resolve node IDs for each, or add new nodes if no matching node exists.
        $landmarks=array();
        foreach ($result["responses"][0]["landmarkAnnotations"] as $label)
            {
            if(isset($label["description"]))
                {
                $landmarks[]=$label["description"];
                $title=$label["description"]; # Title is always the landmark, if a landmark is visible.
                }
            }
        update_field($resource,$google_vision_landmarks_field,join(", ",$landmarks));
        }  
        
    #--------------------------------------------------------
    # Process logos
    #--------------------------------------------------------
    if (isset($result["responses"][0]["logoAnnotations"]))      
        {
        # Logos found. Loop through them and resolve node IDs for each, or add new nodes if no matching node exists.
        $logos=array();
        foreach ($result["responses"][0]["logoAnnotations"] as $label)
            {
            if(isset($label["description"]) && isset($label["score"]) && $label["score"]>0.75) // High score bar of 75% for logos as in testing there were many false detections.
                {
                $logos[]=$label["description"];
                }
            }
        update_field($resource,$google_vision_logo_field,join(", ",$logos));
        }  

    #--------------------------------------------------------
    # Process text
    #--------------------------------------------------------
    if (isset($result["responses"][0]["textAnnotations"]))      
        {
        # Keywords found. Loop through them and resolve node IDs for each, or add new nodes if no matching node exists.
        $text=array();
        foreach ($result["responses"][0]["textAnnotations"] as $label)
            {
            $text[]=$label["description"];
            break; # Stop here because the first one seems to be the most useful, being a sensible grouping of all available text in aproximate reading order.
            }
        update_field($resource,$google_vision_text_field,join(", ",$text));
        }
        
    #--------------------------------------------------------
    # Process facial recognition data
    #--------------------------------------------------------
    if ($google_vision_face_detect_field > 0 && isset($result["responses"][0]["faceAnnotations"]))
        {
        # Keywords found. Loop through them and resolve node IDs for each, or add new nodes if no matching node exists.
        $faces=array();
        if($google_vision_face_detect_verbose)
            {
            $faces[0] = "Full face (boundingPoly),Face (fdboundingPoly),Landmarks,Other";
            }
            
        $f=1;
        foreach ($result["responses"][0]["faceAnnotations"] as $face)
            {
            $faces[$f] = "";
            
            // Full boundingPoly
            if(isset($face["boundingPoly"]) && ($google_vision_face_detect_fullface || $google_vision_face_detect_verbose))
                {
                $faces[$f] .= "\"";
                foreach($face["boundingPoly"] as $bply)
                    {
                    foreach($bply as $bpv)
                        {
                        $faces[$f] .= "{x:" . (isset($bpv["x"]) ? $bpv["x"] : "0") . ",y:" . (isset($bpv["y"]) ? $bpv["y"] : "0") . "}";
                        }
                    }
                $faces[$f] .= "\"";                
                if($google_vision_face_detect_verbose)
                    {
                    $faces[$f] .= ",";
                    }
                    
                unset($face["boundingPoly"]);
                }
            elseif($google_vision_face_detect_verbose)
                {
                $faces[$f] .= ",";
                }
            
            // fdBoundingPoly (visible skin)
            if(isset($face["fdBoundingPoly"]) && (!$google_vision_face_detect_fullface || $google_vision_face_detect_verbose))
                {
                $faces[$f] .= "\"";
                foreach($face["fdBoundingPoly"] as $fdbply)
                    {
                    foreach($fdbply as $fdbpv)
                        {
                        $faces[$f] .= "{x:" . (isset($fdbpv["x"]) ? $fdbpv["x"] : "0") . ",y:" . (isset($fdbpv["y"]) ? $fdbpv["y"] : "0") . "}";
                        }
                    }
                $faces[$f] .= "\"";                
                if($google_vision_face_detect_verbose)
                    {
                    $faces[$f] .= ",";
                    }
                    
                unset($face["fdBoundingPoly"]);
                }
            elseif($google_vision_face_detect_verbose)
                {
                $faces[$f] .= ",";
                }
            
            // Facial features (Landmarks)
            if(isset($face["landmarks"]) && $google_vision_face_detect_verbose)
                {
                $faces[$f] .= "\"[";
                foreach($face["landmarks"] as $lndmk)
                    {
                    $faces[$f] .= "{type:" . (isset($lndmk["type"]) ? $lndmk["type"] : "?") . ",x:" . (isset($lndmk["position"]["x"]) ? $lndmk["position"]["x"] : "0") . ",y:" . (isset($lndmk["position"]["y"]) ? $lndmk["position"]["y"] : "0") . ",z:" . (isset($lndmk["position"]["z"]) ? $lndmk["position"]["z"] : "0") . "}";
                    }
                $faces[$f] .= "]\",";
                unset($face["landmarks"]);
                }
            elseif($google_vision_face_detect_verbose)
                {
                $faces[$f] .= ",";
                }
             
            if($google_vision_face_detect_verbose)
                {
                // Add in remaining data e.g. angle, emotion
                foreach($face as $facedata=>$value)
                    {
                    $faces[$f] .= "{" . $facedata . ":" . $value . "}";
                    }
                }
            
            $f++;
            }
        
        $allfaces = implode("\r\n",$faces);
        update_field($resource,$google_vision_face_detect_field,$allfaces);
        }  
        
    # Automatically set the title to the best keyword (highest ranked label, or landmark if set)
    global $google_vision_autotitle,$view_title_field;
    if ($google_vision_autotitle && $title!="")
        {
        update_field($resource,$view_title_field,ucfirst($title));
        }
    
    # Mark as processed
    $parameters=array("i",$resource);
    ps_query("update resource set google_vision_processed=1 where ref=?", $parameters);
    
    return true;
    }


function google_visionTranslate($labels)
    {
    // Google Vision only returns English keywords. The separate translation API can be used to translate these to other languages.
    global $google_vision_translation_api_key,$google_vision_translation_keep_english,$google_vision_translation_languages;

    $labels_joined=join(" :: ",$labels);
    $languages=explode(",",$google_vision_translation_languages);

    # Start from scratch or create new?
    if ($google_vision_translation_keep_english) {$new_labels=$labels;} else {$new_labels=array();}

    foreach ($languages as $language)
        {
        # API URL
        $url="https://translation.googleapis.com/language/translate/v2?key=" . $google_vision_translation_api_key;

        # Form the JSON request.
        $request='{
            "q": "' . $labels_joined . '",
            "source": "en",
            "target": "' . trim($language) . '",
            "format": "text"
        }';
        
        debug("google_vision: \$request = {$request}");
        
        # Build a HTTP request, and fetch results.
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => $request,
                'ignore_errors' => true
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
    
        debug("google_vision: \$result = " . print_r($result, true));
  
        $result=json_decode($result);
        if (isset($result->data->translations[0]->translatedText))
            {
            $new_labels=array_merge($new_labels,explode(" :: ",$result->data->translations[0]->translatedText));
            }
        }

    return $new_labels;
    }