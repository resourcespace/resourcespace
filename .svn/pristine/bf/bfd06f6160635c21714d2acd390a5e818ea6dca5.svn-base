<?php
    
function HookGoogle_visionEditEdithidefield($field)
    {
    global $google_vision_face_detect_field,$google_vision_face_dependent_field,$ref,$resource;
    if ($field["ref"]!==$google_vision_face_dependent_field || $ref < 0 || $google_vision_face_detect_field == 0)
        {
        return false;
        }
    
    $facedata = get_data_by_field($ref,$google_vision_face_detect_field);
    
    if(trim($facedata) != "")
        {
        // Show this field
        return false;    
        }

    // Hide this field
    return true;
    }