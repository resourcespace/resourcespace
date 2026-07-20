<?php
/**
 * Valencia DAM – Annotation API-Bindings für ResourceSpace
 *
 * INSTALLATION auf dem DAM-Server (dam.valencia.ch):
 *   1. Ordner plugins/valencia_annotations/ anlegen
 *   2. Diese Datei nach plugins/valencia_annotations/api/api_bindings.php kopieren
 *   3. plugins/valencia_annotations/valencia_annotations.yaml kopieren
 *   4. Plugin im Admin unter «Plugins» aktivieren
 *   5. PHP-OPcache leeren / Apache neu laden
 *
 * ResourceSpace-Core hat getResourceAnnotations() / getAnnotoriousResourceAnnotations(),
 * aber keine api_*-Wrapper – die API liefert sonst leere Antworten.
 */

/**
 * Alle Annotationen eines Assets (inkl. Kommentartext, Autor, Tags).
 * Ignoriert die Page-Filterung von getResourceAnnotations (Bilder haben oft page=NULL oder 0).
 *
 * @param int $resource Resource-ID
 * @return array
 */
function api_valencia_get_resource_annotations($resource)
{
    $resource = (int) $resource;
    if ($resource <= 0) {
        return [];
    }

    $rows = ps_query(
        sprintf(
            'SELECT %s, c.body AS "text"
                 FROM annotation AS a
            LEFT JOIN `comment` AS c ON a.ref = c.annotation
                WHERE a.resource = ?
             ORDER BY a.ref ASC',
            columns_in('annotation', 'a')
        ),
        ['i', $resource]
    );

    $out = [];
    foreach ($rows as $annotation) {
        $author = '';
        if (!empty($annotation['user'])) {
            $user_data = get_user((int) $annotation['user']);
            if (is_array($user_data)) {
                $author = (string) ($user_data['fullname'] ?: $user_data['username'] ?: '');
            }
        }

        $tag_rows = getAnnotationTags($annotation);
        $tags = [];
        foreach ($tag_rows as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ($name !== '') {
                $tags[] = $name;
            }
        }

        $text = trim((string) ($annotation['text'] ?? ''));
        if ($text === '' && !empty($tags)) {
            $text = implode(', ', $tags);
        }

        $out[] = [
            'ref' => (int) $annotation['ref'],
            'resource' => (int) $annotation['resource'],
            'resource_type_field' => (int) ($annotation['resource_type_field'] ?? 0),
            'page' => isset($annotation['page']) ? (int) $annotation['page'] : null,
            'x' => (float) $annotation['x'],
            'y' => (float) $annotation['y'],
            'width' => (float) $annotation['width'],
            'height' => (float) $annotation['height'],
            'text' => $text,
            'author' => $author,
            'tags' => $tags,
            'editable' => function_exists('annotationEditable')
                ? (bool) annotationEditable($annotation)
                : false,
            'shapes' => [
                [
                    'type' => 'rect',
                    'geometry' => [
                        'x' => (float) $annotation['x'],
                        'y' => (float) $annotation['y'],
                        'width' => (float) $annotation['width'],
                        'height' => (float) $annotation['height'],
                    ],
                ],
            ],
        ];
    }

    return $out;
}

/**
 * Annotationen-Anzahl für ein Asset.
 *
 * @param int $resource Resource-ID
 * @return int
 */
function api_valencia_get_resource_annotation_count($resource)
{
    return (int) getResourceAnnotationsCount((int) $resource);
}

/**
 * Batch: Annotationen-Anzahlen für mehrere Assets.
 *
 * @param string $refs Kommagetrennte Resource-IDs
 * @return array map resource_id => count, z.B. {"6096":1,"6097":0}
 */
function api_valencia_get_resource_annotation_counts($refs)
{
    $ids = [];
    foreach (preg_split('/\s*,\s*/', (string) $refs) as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        return new stdClass(); // {} in JSON
    }

    $rows = ps_query(
        'SELECT resource, COUNT(*) AS annocount
           FROM annotation
          WHERE resource IN (' . ps_param_insert(count($ids)) . ')
       GROUP BY resource',
        ps_param_fill($ids, 'i')
    );

    $map = [];
    foreach ($ids as $id) {
        $map[(string) $id] = 0;
    }
    foreach ($rows as $row) {
        $map[(string) ((int) $row['resource'])] = (int) $row['annocount'];
    }

    return $map;
}

/** Compat: Standard-Namen, falls Clients diese erwarten. */
function api_getResourceAnnotations($resource, $page = 0)
{
    // page wird bewusst ignoriert – Mac-App braucht alle Markierungen am Bild
    return api_valencia_get_resource_annotations($resource);
}

function api_getAnnotoriousResourceAnnotations($resource, $page = 0, $ctx = '[]')
{
    return api_valencia_get_resource_annotations($resource);
}

function api_getResourceAnnotationsCount($resource)
{
    return api_valencia_get_resource_annotation_count($resource);
}
