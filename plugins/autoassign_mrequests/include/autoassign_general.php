<?php

function get_mapped_fields()
{
    $query = 'SELECT DISTINCT field_id AS value FROM assign_request_map;';

    return ps_array($query, array());
}

function get_mapped_user_by_field($id, $value)
{
    $query = "SELECT user_id AS value
                FROM assign_request_map
               WHERE field_id = ? AND field_value = ?";

    $parameters = array("i",$id, "s",$value);
    return ps_value($query, $parameters, 0);
}
