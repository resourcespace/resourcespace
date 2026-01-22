<?php

command_line_only();



$code = '$my_var = ($some_undefined_var == "somevalue");';
if (validate_remote_code($code)) {
    echo 'Code with undefined variables use - ';
    return false;
}


$code = '$no_semicolon = true';
if (validate_remote_code($code)) {
    echo 'Code with syntax errors - ';
    return false;
}


$code = 'test1003_callUndefinedFunction();';
if (validate_remote_code($code)) {
    echo 'Code with undefined function calls - ';
    return false;
}


$code = 'trigger_error("caused by test.php 1003");';
if (validate_remote_code($code)) {
    echo 'Code with some error - ';
    return false;
}


$code = '$test1003_var = false;';
$test1003_var = true;
if (!validate_remote_code($code)) {
    echo 'Code setting a variable - ';
    return false;
} elseif ($test1003_var === false) {
    echo 'Validation NOT affecting global scope variables - ';
    return false;
}



return true;
