<?php
// Shared rendering for the central request table. Controllers provide authorised, paginated metadata.
$requests = $request_list['rows'];
$list_page = $baseurl_short . 'plugins/consentmanager/pages/list.php';
$manage_page = $baseurl_short . 'plugins/consentmanager/pages/requests.php';
?>
<section aria-label="<?php echo escape($lang['cm_external_requests']); ?>">
    <?php if (!$requests) { ?>
        <p><?php echo escape($lang['cm_external_no_requests']); ?></p>
    <?php } else { ?>
        <div class="Listview consent-table">
            <table class="ListviewStyle">
                <thead>
                    <tr class="ListviewTitleStyle">
                        <th><?php echo escape($lang['cm_external_name']); ?></th>
                        <th><?php echo escape($lang['collection']); ?></th>
                        <th><?php echo escape($lang['cm_external_status']); ?></th>
                        <th><?php echo escape($lang['cm_external_expires']); ?></th>
                        <th><div class="ListTools"><?php echo escape($lang['tools']); ?></div></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $row) { ?>
                        <tr>
                            <td><?php consentmanager_render_status_name('#' . $row['ref'] . ' ' . $row['recipient_name'],
                                $row['withdrawn'] ? 'withdrawn' : $row['status'], false); ?></td>
                            <td><?php echo escape($row['collection_name']); ?></td>
                            <td>
                                <?php echo escape($lang['cm_external_status_' . ($row['withdrawn'] ? 'withdrawn' : $row['status'])]); ?>
                                <?php if ($row['error'] !== '') { ?>
                                    <p><?php echo escape($lang[$row['error']] ?? $lang['cm_external_failed']); ?></p>
                                <?php } ?>
                            </td>
                            <td><?php echo escape(nicedate($row['expires'], true)); ?></td>
                            <td>
                                <div class="ListTools">
                                    <a href="<?php echo escape(generateURL(
                                        $baseurl_short . 'plugins/consentmanager/pages/recipient_preview.php',
                                        ['request' => $row['ref']]
                                    )); ?>" target="_blank" rel="noopener">
                                        <i class="icon-eye" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['cm_external_recipient_view']); ?>
                                    </a>
                                    <a href="<?php echo escape(generateURL($manage_page, ['audit' => $row['ref']])); ?>"
                                        onclick="return CentralSpaceLoad(this, true);">
                                        <i class="icon-history" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['cm_external_audit']); ?>
                                    </a>
                                    <?php if ($row['status'] === 'completed') { ?>
                                        <a href="<?php echo escape(generateURL(
                                            $baseurl_short . 'plugins/consentmanager/pages/edit.php',
                                            ['ref' => $row['consent']]
                                        )); ?>" onclick="return CentralSpaceLoad(this, true);">
                                            <i class="icon-file-check" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['cm_external_view_consent']); ?>
                                        </a>
                                    <?php }
                                    $actions = [];
                                    if ($row['status'] === 'completed' && !$row['withdrawn']) {
                                        $actions = ['withdraw' => 'icon-user-x'];
                                    } elseif (!in_array($row['status'], ['completed', 'revoked', 'declined', 'expired'], true)) {
                                        $actions = ['send' => 'icon-send', 'link' => 'icon-link', 'revoke' => 'icon-ban'];
                                    }
                                    if ($actions) { ?>
                                        <form id="consent-request-<?php echo (int) $row['ref']; ?>" method="post"
                                            action="<?php echo escape(generateURL($manage_page, $consent_list_params ?? [])); ?>">
                                            <?php generateFormToken('cm_external_manage'); ?>
                                            <input name="request" type="hidden" value="<?php echo (int) $row['ref']; ?>">
                                            <input name="cm_action" type="hidden" value="">
                                        </form>
                                        <?php foreach ($actions as $action => $icon) {
                                            $onclick = 'if (confirm(' . encode_js_value($lang['cm_external_confirm_' . $action]) . ')) {'
                                                . 'const form=document.getElementById(' . encode_js_value('consent-request-' . $row['ref']) . ');'
                                                . 'form.elements.cm_action.value=' . encode_js_value($action) . ';'
                                                . 'CentralSpacePost(form,true); } return false;'; ?>
                                            <a href="<?php echo escape(generateURL($manage_page, ['audit' => $row['ref']])); ?>"
                                                onclick="<?php echo escape($onclick); ?>">
                                                <i class="<?php echo escape($icon); ?>" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['cm_external_' . $action]); ?>
                                            </a>
                                        <?php }
                                    } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
    <?php if (empty($request_detail)) { ?>
        <form method="post" action="<?php echo escape($list_page); ?>" id="consent-request-search"
            class="consent-list-controls" onsubmit="CentralSpacePost(this); return false;">
            <?php generateFormToken('cm_external_search'); ?>
            <input type="hidden" name="findtext" value="<?php echo escape($findtext ?? ''); ?>">
            <input type="hidden" name="consent_status" value="<?php echo escape($consent_status ?? 'all'); ?>">
            <input type="hidden" name="consent_offset" value="<?php echo (int) ($consent_offset ?? 0); ?>">
            <input type="hidden" name="pager_table" value="requests">
            <label for="request-status"><?php echo escape($lang['cm_external_status']); ?></label>
            <select name="request_status" id="request-status" onchange="CentralSpacePost(this.form);">
                <option value="all"><?php echo escape($lang['cm_external_all_statuses']); ?></option>
                <?php foreach (consentmanager_external_statuses() as $status) { ?>
                    <option value="<?php echo escape($status); ?>" <?php echo $request_status === $status ? 'selected' : ''; ?>>
                        <?php echo escape($lang['cm_external_status_' . $status]); ?>
                    </option>
                <?php } ?>
            </select>
            <label for="request-find"><?php echo escape($lang['consentsearch']); ?></label>
            <input class="shrtwidth" type="search" name="request_find" id="request-find" maxlength="200"
                placeholder="<?php echo escape($lang['searchbytext']); ?>" value="<?php echo escape($request_find); ?>">
            <input type="button" value="<?php echo escape($lang['clearbutton']); ?>"
                onclick="this.form.elements.request_find.value=''; CentralSpacePost(this.form);">
            <input type="submit" value="<?php echo escape($lang['searchbutton']); ?>">
            <?php if ($request_list['total'] > $request_list['per_page']) {
                consentmanager_render_pager([
                    'curpage' => (int) floor($request_list['offset'] / $request_list['per_page']) + 1,
                    'totalpages' => (int) ceil($request_list['total'] / $request_list['per_page']),
                    'offset' => $request_list['offset'],
                    'per_page' => $request_list['per_page'],
                    'url' => $list_page,
                    'url_params' => array_merge($consent_list_params, ['pager_table' => 'requests']),
                ]);
            } ?>
        </form>
    <?php } ?>
</section>
