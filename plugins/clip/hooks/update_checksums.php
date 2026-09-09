<?php
/**
 * Copy checksum from the resource table to the resource_clip_vector table when a resource has had its checksum recreated.
 *
 * @param   array    $resource   Array of resource data for a given resource ref.
 * @return  void
 */
function HookClipUpdate_checksumsCopy_checksum(array $resource)
{
    global $clip_resource_types;

    if (!in_array($resource['resource_type'], $clip_resource_types)) {
        return;
    }

    $sql = 'UPDATE resource_clip_vector v JOIN `resource` r ON v.resource = r.ref
            SET v.checksum = r.file_checksum 
            WHERE r.resource_type IN (' . ps_param_insert(count($clip_resource_types)) . ')
            AND r.ref = ?;';

    ps_query($sql, array_merge(ps_param_fill($clip_resource_types, "i"), array('i', $resource['ref'])));
}