<?php

command_line_only();


// valid date if empty string returned

// Case 1: If passed empty string should return NOT empty
$date_input = "";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #1 - ";
    return false;
}

// Case 2: Legitimate date should return empty
$date_input = "1990-04-05";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #2 - ";
    return false;
}

// Case 3: Illegitimate DAY should return NOT empty
$date_input = "1990-04-50";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #3 - ";
    return false;
}

// Case 4: Illegitimate MONTH should return NOT empty
$date_input = "1990-40-05";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #4 - ";
    return false;
}

// Case 5: Check all 0s should return NOT empty
$date_input = "0000-00-00";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #5 - ";
    return false;
}

// Case 6: Legitimate LEAP YEAR should return empty
$date_input = "2020-02-29";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #6 - ";
    return false;
}

// Case 7: Illegitimate LEAP YEAR should return NOT empty
$date_input = "2019-02-29";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #7 - ";
    return false;
}

// Case 8: Junk in DAY should return NOT empty
$date_input = "1990-04-oe";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #8 - ";
    return false;
}

// Case 9: Junk in MONTH should return NOT empty
$date_input = "1990-ad-05";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #9 - ";
    return false;
}

// Case 10: Junk in YEAR should return NOT empty
$date_input = "ioaj-04-05";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #10 - ";
    return false;
}

// Case 11: Missed DAY and LEGITIMATE YEAR/MONTH combo should return empty (as this can form a correct partial date as long as the rest is legitimate)
$date_input = "1990-04";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #11 - ";
    return false;
}

// Case 12: Missed DAY and ILLEGITIMATE YEAR/MONTH combo should return NOT empty (as this IS NOT a valid partial date)
$date_input = "1990-40";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #12 - ";
    return false;
}

// Case 13: Legitimate YEAR on its own should return empty (as this IS a valid partial date)
$date_input = "1990";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #13 - ";
    return false;
}

// Case 14: Legitimate DATE TIME including SECONDS should return empty
$date_input = "1990-04-05 12:00:00";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #14 - ";
    return false;
}

// Case 15: Legitimate DATE TIME without SECONDS should return empty
$date_input = "1990-04-05 12:00";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #15 - ";
    return false;
}

// Case 16: Illegitimate MINUTES should return NOT empty
$date_input = "1990-04-05 12:60";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #16 - ";
    return false;
}

// Case 17: Illegitimate HOURS should return NOT empty
$date_input = "1990-04-05 25:00";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #17 - ";
    return false;
}

// Case 18: If passed NULL should return NOT empty
$date_input = null;
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #18 - ";
    return false;
}

// Case 19: If passed string should return NOT empty
$date_input = "Hello";
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #19 - ";
    return false;
}

// Case 20: If passed TRUE should return NOT empty
$date_input = true;
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #20 - ";
    return false;
}

// Case 21: If passed FALSE should return NOT empty
$date_input = false;
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #21 - ";
    return false;
}

// Case 22: If passed INT should return NOT empty
$date_input = 1234567890;
$valid_date_output = check_date_format($date_input);

if ($valid_date_output === "") {
    echo "Use case #22 - ";
    return false;
}

// Case 23: HOWEVER if passed 4 digit INT it will return empty (assumes its a YEAR)
$date_input = 1234;
$valid_date_output = check_date_format($date_input);

if ($valid_date_output !== "") {
    echo "Use case #23 - ";
    return false;
}

return true;
