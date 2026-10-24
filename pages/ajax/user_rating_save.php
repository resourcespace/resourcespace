<?php

# AJAX ratings save

include "../../include/boot.php";
include "../../include/authenticate.php";

enforcePostRequest(true);

user_rating_save(getval("userref", "", true), getval("ref", "", true), getval("rating", ""));
