<?php

# Could not match on provided keywords? Attempt to return some suggestions.
if (!$fullmatch) {
    if ($suggested == $keywords) {
        # Nothing different to suggest.
        debug("No alternative keywords to suggest.");
        return "";
    } else {
        # Suggest alternative spellings/sound-a-likes
        $suggest = "";
        if (strpos($search, ",") === false) {
            $suggestjoin = " ";
        } else {
            $suggestjoin = ", ";
        }

        foreach ($suggested as $suggestion) {
            if ($suggestion != "") {
                if ($suggest != "") {
                    $suggest .= $suggestjoin;
                }
                $suggest .= $suggestion;
            }
        }
        debug("Suggesting $suggest");
        return $suggest;
    }
}
