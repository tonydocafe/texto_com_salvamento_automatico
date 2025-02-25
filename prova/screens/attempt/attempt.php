
<?php
/**
 * Prepara a execução da aplicação de uma prova.
 *
 * @package /mod/prova/screens/attempt
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/mod/prova/locallib.php');
require_once($CFG->dirroot . '/mod/prova/src/attempt/lib.php');
$PAGE->set_context(context_system::instance());

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 1, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);
$autosave = optional_param('autosave', false, PARAM_BOOL);// teste

$attemptobj = prova_create_attempt_handling_errors($attemptid, $cmid);
$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_url($attemptobj->attempt_url(null, $page));


// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/prova:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_provaattempt_exception($attemptobj->get_prova(), 'notyourattempt');
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/prova:attempt');
} else {
	\mod_prova\manager::has_permission_to_access();
	// TODO: averiguar o que isto deveria fazer
    // navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} elseif ($attemptobj->get_state() == prova_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_prova');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'prova', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page));
}

$autosaveperiod = 60;
$PAGE->requires->yui_module('moodle-mod_prova-autosave', 'M.mod_prova.autosave.init', array($autosaveperiod));

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_provaattempt_exception($attemptobj->get_provaobj(), 'noquestionsfound');
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
    redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
}

// Initialise the JavaScript.
$PAGE->requires->js_init_call('M.mod_prova.init_attempt_form', null, false, prova_get_js_module());

// Arrange for the navigation to be displayed in the first region on the page.

$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->attempt_page_title($page));
$PAGE->set_heading($attemptobj->get_course()->fullname);
$PAGE->set_pagelayout('popup');
$PAGE->requires->css('/mod/prova/components/addprovas/preview.scss');

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}


echo $output->attempt_page($attemptobj, $page, null, $slots, $nextpage);


