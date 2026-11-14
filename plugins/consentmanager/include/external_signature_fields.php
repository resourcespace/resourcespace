<?php
// Shared signature controls for the live signing stage and administrator preview.
?>
                        <label for="cm-signatory"><?php echo escape($lang['cm_external_signatory']); ?></label>
                        <input id="cm-signatory" name="signatory" required maxlength="200" autocomplete="name">
                        <label for="cm-method"><?php echo escape($lang['cm_external_method']); ?></label>
                        <select id="cm-method" name="method">
                            <?php foreach (['drawn', 'typed'] as $method) {
                                if ($GLOBALS['consentmanager_external_' . $method]) { ?>
                                    <option value="<?php echo escape($method); ?>"><?php echo escape($lang['cm_external_method_' . $method]); ?></option>
                                <?php }
                            } ?>
                        </select>
                        <?php if ($consentmanager_external_drawn) { ?>
                            <div id="cm-drawn-controls">
                            <p><?php echo escape($lang['cm_external_draw_help']); ?></p>
                            <canvas id="cm-signature" class="cm-signature" width="900" height="220"
                                tabindex="0" aria-describedby="cm-signature-error"
                                aria-label="<?php echo escape($lang['cm_external_method_drawn']); ?>"></canvas>
                            <input id="cm-signature-data" name="signature" type="hidden">
                            <p id="cm-signature-error" class="cm-error" role="alert" hidden><?php echo escape($lang['cm_external_draw_required']); ?></p>
                            <button id="cm-signature-clear" type="button"><?php echo escape($lang['cm_external_clear']); ?></button>
                            </div>
                        <?php } ?>
