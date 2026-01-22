<?php

$feedback_questions = array();

$feedback_prompt_text = "<h2 style=\"color:black;\">User Survey</h2>
<p>Please help us to improve the system by taking a moment to give us your feedback.</p>";

$feedback_questions[0]['text'] = "<br /><h2>Accessibility</h2>";
$feedback_questions[0]['type'] = 4;
$feedback_questions[0]['options'] = "";
$feedback_questions[1]['text'] = "1. Do you find it easy to log on to the site?";
$feedback_questions[1]['type'] = 3;
$feedback_questions[1]['options'] = "Yes,No,Sometimes";
$feedback_questions[2]['text'] = "If no, please specify………";
$feedback_questions[2]['type'] = 1;
$feedback_questions[2]['options'] = "";
$feedback_questions[3]['text'] = "<br /><br/><h2>Usage</h2>";
$feedback_questions[3]['type'] = 4;
$feedback_questions[3]['options'] = "";
$feedback_questions[4]['text'] = "Do you find the navigation of the site simple?";
$feedback_questions[4]['type'] = 3;
$feedback_questions[4]['options'] = "Yes,No";
$feedback_questions[5]['text'] = "If no, could you please suggest how you would like to see improvements?";
$feedback_questions[5]['type'] = 2;
$feedback_questions[5]['options'] = "";
$feedback_questions[6]['text'] = "Have you ever experienced problems with the site? Please select from the list.";
$feedback_questions[6]['type'] = 5;
$feedback_questions[6]['options'] = "Logging on,Navigation of site,Locating resources,Viewing resources,Downloading resources,Other";
$feedback_questions[7]['text'] = "If other, please specify:";
$feedback_questions[7]['type'] = 1;
$feedback_questions[7]['options'] = "";
$feedback_questions[8]['text'] = "<br /><br /><h2>Content</h2>";
$feedback_questions[8]['type'] = 4;
$feedback_questions[8]['options'] = "";
$feedback_questions[9]['text'] = "Have you always found the resources you need?";
$feedback_questions[9]['type'] = 3;
$feedback_questions[9]['options'] = "Yes,No,Sometimes";
$feedback_questions[10]['text'] = "Do you find the quality of the resources acceptable?";
$feedback_questions[10]['type'] = 3;
$feedback_questions[10]['options'] = "Yes,No,Sometimes";
$feedback_questions[11]['text'] = "Are there any resources you would like to be made available?";
$feedback_questions[11]['type'] = 1;
$feedback_questions[11]['options'] = "";
$feedback_questions[12]['text'] = "Do you have any suggestions to help improve the site?";
$feedback_questions[12]['type'] = 2;
$feedback_questions[12]['options'] = "";
