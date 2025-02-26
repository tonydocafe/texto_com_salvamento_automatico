<?php 
/**
 * Biblioteca com a classe prova_attempt.
 * Responsável por gerar todas as funções das 
 * aplicações das provas
 *
 * @package /mod/prova/src/attempt
 */
/**
 table: prova_attempts
 columns:
    - id
    - prova     -| fk prova
    - userid    -| fk user
    - uniqueid  -| fk question_usages
    - attempts
    -       #### layout
    - currentpage
    - preview
    - state
    - timestart
    - timefinish
    - timemodified
    - timemodifiedoffiline
    - timecheckstate
    - sumgrades
 */
// TODO: integrar bloco addprova com esse biblioteca

defined('MOODLE_INTERNAL') || die();

/**
 * Class for prova exceptions. Just saves a couple of arguments on the
 * constructor for a moodle_exception.
 */
class moodle_provaattempt_exception extends moodle_exception {
    public function __construct($provaobj, $errorcode, $a = null, $link = '', $debuginfo = null) {
        if (!$link) {
            $link = $provaobj->view_url();
        }
        parent::__construct($errorcode, 'prova', $link, $a, $debuginfo);
    }
}

/**
 * Classe que ira encapsular os dados da aplicação da prova
 * e o objeto da mesma
 *
 * Initially, it only loads a minimal amout of information about each question - loading
 * extra information only when necessary or when asked. The class tracks which questions
 * are loaded.
 *
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */
class prova {
    /** @var stdClass the course settings from the database. */
    protected $course;
    /** @var stdClass the course_module settings from the database. */
    protected $cm;
    /** @var stdClass the prova settings from the database. */
    protected $prova;
    /** @var ProvaObj o objeto da prova que está sendo aplicada. */
    protected $provaobj;
    /** @var context the prova context. */
    protected $context;

    /** @var array of questions augmented with slot information. */
    protected $questions = null;
    /** @var bool whether the current user has capability mod/prova:preview. */
    protected $ispreviewuser = null;
    /** @var prova_access_manager the access manager for this prova. */
    protected $accessmanager = null;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param object $prova the row from the prova table.
     * @param object $cm the course_module object for this prova.
     * @param object $course the row from the course table for the course we belong to.
     * @param bool $getcontext intended for testing - stops the constructor getting the context.
     */
    public function __construct($prova, $cm, $course, $getcontext = true) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/prova/components/addprovas/provalib.php');

        $this->prova = $prova;
        $this->provaobj = new ProvaObj($prova->provaid);
        $this->cm = $cm;
        $this->prova->cmid = $this->cm->id;
        $this->course = $course;
        if ($getcontext && !empty($cm->id)) {
            $this->context = context_module::instance($cm->id);
        }
    }

    /**
     * Static function to create a new prova object for a specific user.
     *
     * @param int $provaid the the prova id.
     * @param int $userid the the userid.
     * @return prova the new prova object
     */
    public static function create($provaid, $userid = null) {
        global $DB;

        $prova = $DB->get_record_sql("
            SELECT prova.*, obj.name as objname, obj.grade as grade, obj.sumgrades as sumgrades
            FROM {prova} prova INNER JOIN {seox_prova} obj ON prova.provaid = obj.id
            WHERE prova.id = :id
        ", array('id' => $provaid));
        // $prova = $DB->get_records('prova', array('id' => $provaid));
        $course = $DB->get_record('course', array('id' => $prova->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('prova', $prova->id, $course->id, false, MUST_EXIST);

        $prova = self::get_provaobjid_from_variations($prova, $userid);

        return new prova($prova, $cm, $course);
    }


    /**
     * Create a {@link prova_attempt} for an attempt at this prova.
     * @param object $attemptdata row from the prova_attempts table.
     * @return prova_attempt the new prova_attempt object.
     */
    public function create_attempt_object($attemptdata) {
        return new prova_attempt($attemptdata, $this, $this->cm, $this->course);
    }

    /**
     * Get provaobj_id from all variations
     */
    public static function get_provaobjid_from_variations($prova, $userid) {
        global $USER, $DB;

        $userid = ($userid) ? $userid : $USER->id;

        $sql = "SELECT *
                FROM {prova_attempts}
                WHERE prova = :provaid AND userid = :userid";
      
        $params = array('provaid' => $prova->id, 'userid' => $userid);
        $attempt = $DB->get_record_sql($sql, $params);

        if ($attempt && $attempt->prova == $prova->id) {
            
            if (!isset($attempt->variation) || !$attempt->variation) {
                return $prova;
            }

            $sql = "SELECT *
                FROM {prova_variations} 
                WHERE id = :varid";
      
            $params = array('varid' => $attempt->variation);
            $variation = $DB->get_record_sql($sql, $params);
            $prova->provaid = $variation->provaobj_id;
            return $prova;          
        }


        $variations = $DB->get_records_sql("
            SELECT * FROM {prova_variations}
            WHERE prova_id = :id
        ", array('id' => $prova->id));

        if (!$variations || count($variations) <= 0) {
            return $prova;
        }

        $variation_index = array_rand($variations);
        $variation = $variations[$variation_index];
        $prova->provaid = $variation->provaobj_id;

        return $prova;
    } 


    // Simple getters ==========================================================
    /** @return object the row of the prova table. */
    public function get_prova() {
        return $this->prova;
    }

    public function get_provaid() {
        return $this->prova->id;
    }

    /** @return string the name of this prova. */
    public function get_prova_name() {
        return $this->prova->name;
    }

    /** @return object the row of the seox_prova table. */
    public function get_provaobj() {
        return $this->provaobj->get_data();
    }

    /** @return object the row of the seox_prova table. */
    public function get_provaobjid() {
        return $this->get_provaobj()->id;
    }

    /** @return int the course id. */
    public function get_courseid() {
        return $this->course->id;
    }

    /** @return object the row of the course table. */
    public function get_course() {
        return $this->course;
    }

    /** @return int the number of attempts allowed at this prova (0 = infinite). */
    public function get_num_attempts_allowed() {
        $attemps = $this->prova->attempts;
        return $attemps == 0 ? 1 : $attemps;
    }

    /** @return int the course_module id. */
    public function get_cmid() {
        return $this->cm->id;
    }

    /** @return object the course_module object. */
    public function get_cm() {
        return $this->cm;
    }

    /** @return object the module context for this prova. */
    public function get_context() {
        return $this->context;
    }

    /** @return int the prova navigation method. */
    public function get_navigation_method() {
        return $this->prova->navmethod;
    }

    /** @return bool whether the prova has provacontrol. */
    public function has_provacontrol() {
        return $this->prova->provacontrol == 1;
    }

    /**
     * @return whether any questions have been added to this prova.
     */
    public function has_questions() {
        if ($this->questions === null) {
            $this->preload_questions();
        }
        return !empty($this->questions);
    }

    public function get_questionids() {
        if ($this->has_questions()) {
            return array_keys($this->questions);
        }
        return null;
    }

    /**
     * @return bool wether the current user is someone who previews the prova,
     * rather than attempting it.
     */
    public function is_preview_user() {
        if (is_null($this->ispreviewuser)) {
            $this->ispreviewuser = has_capability('mod/prova:preview', $this->context);
        }
        return $this->ispreviewuser;
    }

    /**
     * Return prova_access_manager and instance of the prova_access_manager class
     * for this prova at this time.
     * @param int $timenow the current time as a unix timestamp.
     * @return prova_access_manager and instance of the prova_access_manager class
     *      for this prova at this time.
     */
    public function get_access_manager($timenow) {
        if (is_null($this->accessmanager)) {
            $this->accessmanager = new prova_access_manager($this, $timenow, has_capability('mod/prova:ignoretimelimits', $this->context, null, false));
        }
        return $this->accessmanager;
    }

    /**
     * @param array $questionids question ids of the questions to load. null for all.
     */
    public function get_questions($questionids = null) {
        if (is_null($questionids)) {
            $questionids = array_keys($this->questions);
        }
        $questions = array();
        foreach ($questionids as $id) {
            if (!array_key_exists($id, $this->questions)) {
                throw new moodle_exception('cannotstartmissingquestion', 'prova', $this->view_url());
            }
            $questions[$id] = $this->questions[$id];
            $this->ensure_question_loaded($id);
        }
        return $questions;
    }

    /**
     * Wrapper round the has_capability funciton that automatically passes in the prova context.
     */
    public function has_capability($capability, $userid = null, $doanything = true) {
        return has_capability($capability, $this->context, $userid, $doanything);
    }

    /**
     * Wrapper round the require_capability funciton that automatically passes in the prova context.
     */
    public function require_capability($capability, $userid = null, $doanything = true) {
        return require_capability($capability, $this->context, $userid, $doanything);
    }


    // URLs related to this attempt ============================================
    /**
     * @return string the URL of this prova's view page.
     */
    public function view_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/prova/view.php?id=' . $this->cm->id;
    }

    /**
     * @return string the URL of this prova_attempts's edit page. Needs to be POSTed to with a cmid parameter.
     */
    public function start_attempt_url($page = 0) {
        $params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
        if ($page) {
            $params['page'] = $page;
        }
        return new moodle_url('/mod/prova/screens/attempt/startattempt.php', $params);
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @param int $page optional page number to go to in the attempt.
     * @return string the URL of that attempt.
     */
    public function attempt_url($attemptid, $page = 0) {
        global $CFG;
        $url = $CFG->wwwroot . '/mod/prova/screens/attempt/attempt.php?attempt=' . $attemptid;
        if ($page) {
            $url .= '&page=' . $page;
        }
        $url .= '&cmid=' . $this->get_cmid();
        return $url;
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @return string the URL of the review of that attempt.
     */
    public function review_url($attemptid) {
        return new moodle_url('/mod/prova/screens/attempt/review.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid()));
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @return string the URL of the review of that attempt.
     */
    public function printter_url($attemptid) {
        return new moodle_url('/mod/prova/screens/attempt/review.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid(), 'printter' => true));
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @return string the URL of the review of that attempt.
     */
    public function summary_url($attemptid) {
        return new moodle_url('/mod/prova/screens/attempt/summary.php', array('attempt' => $attemptid, 'cmid' => $this->get_cmid()));
    }

    public function get_report_url() {
        return new moodle_url('/mod/prova/screens/report/report.php', array('q' => $this->get_provaid()));
    }


    // Functions for loading more data =========================================
    /**
     * Load just basic information about all the questions in this prova.
     */
    public function preload_questions() {
        $this->questions = question_preload_questions(null,
                'slot.maxmark, slot.id AS slotid, slot.slot, slot.page',
                '{seox_prova_slots} slot ON slot.provaid = :provaid AND q.id = slot.questionid',
                array('provaid' => $this->prova->provaid), 'slot.slot');
    }

    /**
     * Fully load some or all of the questions for this prova. You must call
     * {@link preload_questions()} first.
     *
     * @param array $questionids question ids of the questions to load. null for all.
     */
    public function load_questions($questionids = null) {
        if ($this->questions === null) {
            throw new coding_exception('You must call preload_questions before calling load_questions.');
        }
        if (is_null($questionids)) {
            $questionids = array_keys($this->questions);
        }
        $questionstoprocess = array();
        foreach ($questionids as $id) {
            if (array_key_exists($id, $this->questions)) {
                $questionstoprocess[$id] = $this->questions[$id];
            }
        }
        get_question_options($questionstoprocess);
    }

    /**
     * If $reviewoptions->attempt is false, meaning that students can't review this
     * attempt at the moment, return an appropriate string explaining why.
     *
     * @param int $when One of the mod_prova_display_options::DURING,
     *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
     * @param bool $short if true, return a shorter string.
     * @return string an appropraite message.
     */
    public function cannot_review_message($when, $short = false) {

        if ($short) {
            $langstrsuffix = 'short';
            $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        } else {
            $langstrsuffix = '';
            $dateformat = '';
        }

        if ($when == mod_prova_display_options::DURING ||
                $when == mod_prova_display_options::IMMEDIATELY_AFTER) {
            return '';
        } else if ($when == mod_prova_display_options::LATER_WHILE_OPEN && $this->prova->timeclose &&
                $this->prova->reviewattempt & mod_prova_display_options::AFTER_CLOSE) {
            return get_string('noreviewuntil' . $langstrsuffix, 'prova',
                    userdate($this->prova->timeclose, $dateformat));
        } else {
            return get_string('noreview' . $langstrsuffix, 'prova');
        }
    }

    /**
     * Atualiza aplicação da prova para corrected
     */ 
    public function set_corrected_status() {
        global $DB;
        
        $this->has_capability('mod/prova:grade');
        $DB->set_field('prova', 'corrected', 1, array('id' => $this->prova->id));
        prova_update_grades($this->prova);
    }


    // Private methods =========================================================
    /**
     * Check that the definition of a particular question is loaded, and if not throw an exception.
     * @param $id a questionid.
     */
    protected function ensure_question_loaded($id) {
        if (isset($this->questions[$id]->_partiallyloaded)) {
            throw new moodle_provaattempt_exception($this, 'questionnotloaded', $id);
        }
    }

   
}

class prova_attempt {

    /** @var string to identify the in progress state. */
    const IN_PROGRESS = 'inprogress';
    /** @var string to identify the overdue state. */
    const OVERDUE     = 'overdue';
    /** @var string to identify the finished state. */
    const FINISHED    = 'finished';
    /** @var string to identify the abandoned state. */
    const ABANDONED   = 'abandoned';

    /** @var int maximum number of slots in the prova for the review page to default to show all. */
    const MAX_SLOTS_FOR_DEFAULT_REVIEW_SHOW_ALL = 50;

    /** @var prova the prova aplication. */
    protected $prova;

    /** @var ProvaObje the prova settings from the database. */
    protected $provaobj;

    /** @var stdClass the prova_attempts row. */
    protected $attempt;

    /** @var question_usage_by_activity the question usage for this prova attempt. */
    protected $quba;

    /**
     * @var array of slot information. These objects contain ->slot (int),
     *      ->requireprevious (bool), ->questionids (int) the original question for random questions,
     *      ->firstinsection (bool), ->section (stdClass from $this->sections).
     *      This does not contain page - get that from {@link get_question_page()} -
     *      or maxmark - get that from $this->quba.
     */
    protected $slots;

    /** @var array slot => displayed question number for this slot. (E.g. 1, 2, 3 or 'i'.) */
    protected $questionnumbers;

    /** @var array slot => page number for this slot. */
    protected $questionpages;

    /** @var mod_prova_display_options cache for the appropriate review options. */
    protected $reviewoptions = null;

    
    // Constructor =============================================================
    /**
     * Constructor assuming we already have the necessary data loaded.
     *
     * @param object $attempt the row of the prova_attempts table.
     * @param object $prova the prova object for this attempt and user.
     * @param object $cm the course_module object for this prova.
     * @param object $course the row from the course table for the course we belong to.
     * @param bool $loadquestions (optional) if true, the default, load all the details
     *      of the state of each question. Else just set up the basic details of the attempt.
     */
    public function __construct($attempt, $prova, $cm, $course, $loadquestions = true) {
        global $DB, $CFG;

        // TODO: alterar após integração
        require_once($CFG->dirroot . '/mod/prova/components/addprovas/provalib.php');

        $this->attempt = $attempt;
        $this->prova = $prova;

        if (!$loadquestions) {
            return;
        }

        $this->quba = question_engine::load_questions_usage_by_activity($this->attempt->uniqueid);
        $this->slots = $DB->get_records('seox_prova_slots', array('provaid' => $this->prova->get_provaobjid()), 'slot', 'slot, id, questionid');

        $this->number_questions();
    }

    /**
     * Used by {create()} and {create_from_usage_id()}.
     * @param array $conditions passed to $DB->get_record('prova_attempts', $conditions).
     */
    protected static function create_helper($conditions) {
        global $DB;

        $attempt = $DB->get_record('prova_attempts', $conditions, '*', MUST_EXIST);
        $prova = prova::create($attempt->prova, $attempt->userid);
        $course = $DB->get_record('course', array('id' => $prova->get_courseid()), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('prova', $prova->get_provaid(), $course->id, false, MUST_EXIST);

        return new prova_attempt($attempt, $prova, $cm, $course);
    }

    /**
     * Static function to create a new prova_attempt object given an attemptid.
     *
     * @param int $attemptid the attempt id.
     * @return prova_attempt the new prova_attempt object
     */
    public static function create($attemptid) {
        return self::create_helper(array('id' => $attemptid));
    }

    /**
     * Static function to create a new prova_attempt object given a usage id.
     *
     * @param int $usageid the attempt usage id.
     * @return prova_attempt the new prova_attempt object
     */
    public static function create_from_usage_id($usageid) {
        return self::create_helper(array('uniqueid' => $usageid));
    }

    /**
     * @param string $state one of the state constants like IN_PROGRESS.
     * @return string the human-readable state name.
     */
    public static function state_name($state) {
        return prova_attempt_state_name($state);
    }

    /**
     * Work out the number to display for each question/slot.
     */
    protected function number_questions() {
        $page = 1;
        foreach ($this->slots as $slot) {
            $this->questionpages[$slot->slot] = $page++;
        }
    }

    /**
     * If the given page number is out of range (before the first page, or after
     * the last page, chnage it to be within range).
     * @param int $page the requested page number.
     * @return int a safe page number to use.
     */
    public function force_page_number_into_range($page) {
        return min(max($page, 0), count($this->questionpages));
    }


    // Simple getters ==========================================================
    /** @return prova the prova obj. */
    public function get_prova() {
        return $this->prova;
    }

    /** @return prova the prova obj. */
    public function get_provaid() {
        return $this->prova->get_provaid();
    }

    /** @return string the name of this prova. */
    public function get_prova_name() {
        return $this->prova->get_prova_name();
    }

    /**
     * @param int $timenow the current time as a unix timestamp.
     * @return prova_access_manager and instance of the prova_access_manager class
     *      for this prova at this time.
     */
    public function get_access_manager($timenow) {
        return $this->prova->get_access_manager($timenow);
    }

    /** @return object the course. */
    public function get_course() {
        return $this->prova->get_course();
    }

    /** @return int the course id. */
    public function get_courseid() {
        return $this->prova->get_courseid();
    }

    /** @return object the course_module object. */
    public function get_cmid() {
        return $this->prova->get_cmid();
    }

    /** @return object the course_module object. */
    public function get_cm() {
        return $this->prova->get_cm();
    }

    /** @return int the prova navigation method. */
    public function get_navigation_method() {
        return $this->prova->get_navigation_method();
    }

    /** @return int the attempt id. */
    public function get_attemptid() {
        return $this->attempt->id;
    }

    /** @return int the attempt unique id. */
    public function get_uniqueid() {
        return $this->attempt->uniqueid;
    }

    /** @return object the row from the prova_attempts table. */
    public function get_attempt() {
        return $this->attempt;
    }

    /** @return int the number of this attemp (is it this user's first, second, ... attempt). */
    public function get_attempt_number() {
        return $this->attempt->attempt;
    }

    /** @return string one of the prova_attempt::IN_PROGRESS, FINISHED, OVERDUE or ABANDONED constants. */
    public function get_state() {
        return $this->attempt->state;
    }

    /** @return int the id of the user this attempt belongs to. */
    public function get_userid() {
        return $this->attempt->userid;
    }

    /** @return int the attempt quba object. */
    public function get_quba() {
        return $this->quba;
    }

    /** @return object with user fullname. */
    public function get_userdata() {
        global $DB;
        return $DB->get_record('user', ['id' => $this->attempt->userid], 'firstname, lastname');
    }

    /** @return int the current page of the attempt. */
    public function get_currentpage() {
        return $this->attempt->currentpage;
    }

    /** @return int number fo pages in this prova. */
    public function get_num_pages() {
        return count($this->questionpages);
    }

    /**
     * Return the list of slot numbers for either a given page of the prova, or for the
     * whole prova.
     *
     * @param mixed $page string 'all' or integer page number.
     * @return array the requested list of slot numbers.
     */
    public function get_slots($page = 'all') {
        if ($page === 'all') {
            $numbers = array();
            foreach ($this->questionpages as $irrelevante) {
                $numbers[] = $irrelevante;
            }
            return $numbers;
        } else {
            return [$this->questionpages[$page]];
        }
    }

    /**
     * Return the page of the prova where this question appears.
     * @param int $slot the number used to identify this question within this attempt.
     * @return int the page of the prova this question appears on.
     */
    public function get_question_page($slot) {
        return $this->questionpages[$slot];
    }

    /**
     * Get the question_attempt object for a particular question in this attempt.
     * @param int $slot the number used to identify this question within this attempt.
     * @return question_attempt
     */
    public function get_question_attempt($slot) {
        return $this->quba->get_question_attempt($slot);
    }

    /**
     * Checks whether the question in this slot requires the previous question to have been completed.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return bool whether the previous question must have been completed before this one can be seen.
     */
    public function is_blocked_by_previous_question($slot) {
        return $slot > 1 && isset($this->slots[$slot]) && $this->slots[$slot]->requireprevious &&
                !$this->slots[$slot]->section->shufflequestions &&
                !$this->slots[$slot - 1]->section->shufflequestions &&
                $this->get_navigation_method() != PROVA_NAVMETHOD_SEQ &&
                !$this->get_question_state($slot - 1)->is_finished() &&
                $this->quba->can_question_finish_during_attempt($slot - 1);
    }


    /**
     * Return the {@link question_state} that this question is in.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return question_state the state this question is in.
     */
    public function get_question_state($slot) {
        return $this->quba->get_question_state($slot);
    }

    /**
     * Return the grade obtained on a particular question, if the user is permitted
     * to see it. You must previously have called load_question_states to load the
     * state data about this question.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @param bool $showcorrectness Whether right/partial/wrong states should
     * be distinguised.
     * @return string the formatted grade, to the number of decimal places specified
     *      by the prova.
     */
    public function get_question_status($slot, $showcorrectness) {
        return $this->quba->get_question_state_string($slot, $showcorrectness);
    }

    /**
     * Return the grade obtained on a particular question, if the user is permitted
     * to see it. You must previously have called load_question_states to load the
     * state data about this question.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @param bool $showcorrectness Whether right/partial/wrong states should
     * be distinguised.
     * @return string class name for this state.
     */
    public function get_question_state_class($slot, $showcorrectness) {
        return $this->quba->get_question_state_class($slot, $showcorrectness);
    }

    /**
     * Get the time of the most recent action performed on a question.
     * @param int $slot the number used to identify this question within this usage.
     * @return int timestamp.
     */
    public function get_question_action_time($slot) {
        return $this->quba->get_question_action_time($slot);
    }

    /**
     * Return the grade obtained on a particular question.
     * You must previously have called load_question_states to load the state
     * data about this question.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return string the formatted grade, to the number of decimal places specified by the prova.
     */
    public function get_question_mark($slot) {
        return modprova_format_question_grade($this->prova->get_prova(), $this->quba->get_question_mark($slot));
    }

    /**
     * Wrapper that the correct mod_prova_display_options for this prova at the
     * moment.
     *
     * @param bool $reviewing true for review page, else attempt page.
     * @param int $slot which question is being displayed.
     * @param moodle_url $thispageurl to return to after the editing form is
     *      submitted or cancelled. If null, no edit link will be generated.
     *
     * @return question_display_options the render options for this user on this
     *      attempt, with extra info to generate an edit link, if applicable.
     */
    public function get_display_options_with_edit_link($reviewing, $slot, $thispageurl) {
        return clone($this->get_display_options($reviewing));
    }

    public function get_sum_marks() {
        return $this->attempt->sumgrades;
    }

     /**
     * @param int $page page number
     * @return bool true if this is the last page of the prova.
     */
    public function is_last_page($page) {
        return $page == count($this->questionpages);
    }

    /**
     * @return bool whether this attempt has been finished (true) or is still
     *     in progress (false). Be warned that this is not just state == self::FINISHED,
     *     it also includes self::ABANDONED.
     */
    public function is_finished() {
        return $this->attempt->state == self::FINISHED || $this->attempt->state == self::ABANDONED;
    }

    /**
     * Is a particular question in this attempt a real question, or something like a description.
     * @param int $slot the number used to identify this question within this attempt.
     * @return int whether that question is a real question. Actually returns the
     *     question length, which could theoretically be greater than one.
     */
    public function is_real_question($slot) {
        return $this->quba->get_question($slot)->length;
    }

    /**
     * Is this someone dealing with their own attempt or preview?
     *
     * @return bool true => own attempt/preview. false => reviewing someone elses.
     */
    public function is_own_attempt() {
        global $USER;
        return $this->attempt->userid == $USER->id;
    }

    /**
     * @return bool whether this attempt is a preview belonging to the current user.
     */
    public function is_own_preview() {
        global $USER;
        return $this->is_own_attempt() && $this->is_preview_user() && $this->attempt->preview;
    }

    /** @return bool whether this attempt is a preview attempt. */
    public function is_preview() {
        return $this->attempt->preview;
    }

    /**
     * @return bool wether the current user is someone who previews the prova,
     * rather than attempting it.
     */
    public function is_preview_user() {
        return $this->prova->is_preview_user();
    }

    /**
     * Is the current user allowed to review this attempt. This applies when
     * {@link is_own_attempt()} returns false.
     * @return bool whether the review should be allowed.
     */
    public function is_review_allowed() {
        if (!$this->has_capability('mod/prova:viewreports')) {
            return false;
        }

        $cm = $this->get_cm();
        if ($this->has_capability('moodle/site:accessallgroups') ||
                groups_get_activity_groupmode($cm) != SEPARATEGROUPS) {
            return true;
        }

        // Check the users have at least one group in common.
        $teachersgroups = groups_get_activity_allowed_groups($cm);
        $studentsgroups = groups_get_all_groups($cm->course, $this->attempt->userid, $cm->groupingid);
        return $teachersgroups && $studentsgroups && array_intersect(array_keys($teachersgroups), array_keys($studentsgroups));
    }

    /**
     * Checks whether a user may navigate to a particular slot
     */
    public function can_navigate_to($slot) {
        switch ($this->get_navigation_method()) {
            case PROVA_NAVMETHOD_FREE:
                return true;
                break;
            case PROVA_NAVMETHOD_SEQ:
                return false;
                break;
        }
        return true;
    }

    /**
     * @return int one of the mod_prova_display_options::DURING,
     *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
     */
    public function get_attempt_state() {
        return prova_attempt_state($this->prova->get_prova(), $this->attempt);
    }

    /**
     * Get extra summary information about this attempt.
     *
     * Some behaviours may be able to provide interesting summary information
     * about the attempt as a whole, and this method provides access to that data.
     * To see how this works, try setting a prova to one of the CBM behaviours,
     * and then look at the extra information displayed at the top of the prova
     * review page once you have sumitted an attempt.
     *
     * In the return value, the array keys are identifiers of the form
     * qbehaviour_behaviourname_meaningfullkey. For qbehaviour_deferredcbm_highsummary.
     * The values are arrays with two items, title and content. Each of these
     * will be either a string, or a renderable.
     *
     * @param question_display_options $options the display options for this prova attempt at this time.
     * @return array as described above.
     */
    public function get_additional_summary_data(question_display_options $options) {
        return $this->quba->get_summary_information($options);
    }

    /**
     * Return the grade obtained on a particular question, if the user is permitted
     * to see it. You must previously have called load_question_states to load the
     * state data about this question.
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return string the formatted grade, to the number of decimal places specified
     *      by the prova.
     */
    public function get_question_name($slot) {
        return $this->quba->get_question($slot)->name;
    }

    /**
     * Is it possible for this question to be re-started within this attempt?
     *
     * @param int $slot the number used to identify this question within this attempt.
     * @return whether the student should be given the option to restart this question now.
     */
    public function can_question_be_redone_now($slot) {
        return $this->prova->get_prova()->canredoquestions && !$this->is_finished() && $this->get_question_state($slot)->is_finished();
    }
    
    /**
     * Given a slot in this attempt, which may or not be a redone question, return the original slot.
     *
     * @param int $slot identifies a particular question in this attempt.
     * @return int the slot where this question was originally.
     */
    public function get_original_slot($slot) {
        $originalslot = $this->quba->get_question_attempt_metadata($slot, 'originalslot');
        if ($originalslot) {
            return $originalslot;
        } else {
            return $slot;
        }
    }

    /**
     * Get the displayed question number for a slot.
     * @param int $slot the number used to identify this question within this attempt.
     * @return string the displayed question number for the question in this slot.
     *      For example '1', '2', '3' or 'i'.
     */
    public function get_question_number($slot) {
        return $this->questionpages[$slot];
    }

    /**
     * Wrapper that the correct mod_prova_display_options for this prova at the
     * moment.
     *
     * @return question_display_options the render options for this user on this attempt.
     */
    public function get_display_options($reviewing, $printter=false, $correcting=false) {
        if ($reviewing) {
            if (is_null($this->reviewoptions)) {
                $this->reviewoptions = prova_get_review_options($this->prova->get_prova(), $this->attempt, $this->prova->get_context(), $printter, $correcting);
                if ($this->is_own_preview()) {
                    // It should  always be possible for a teacher to review their
                    // own preview irrespective of the review options settings.
                    $this->reviewoptions->attempt = true;
                }
            }
            return $this->reviewoptions;

        } else {
            $options = mod_prova_display_options::make_from_prova($this->prova->get_prova(), mod_prova_display_options::DURING);
            return $options;
        }
    }

    /**
     * If the section heading, if any, that should come just before this slot.
     * @param int $slot identifies a particular question in this attempt.
     * @return string the required heading, or null if there is not one here.
     */
    public function get_heading_before_slot($slot) {
        return null;
    }

    /**
     * Get the time remaining for an in-progress attempt, if the time is short
     * enought that it would be worth showing a timer.
     * @param int $timenow the time to consider as 'now'.
     * @return int|false the number of seconds remaining for this attempt.
     *      False if there is no limit.
     */
    public function get_time_left_display($timenow) {
        if ($this->attempt->state != self::IN_PROGRESS) {
            return false;
        }
        return $this->get_access_manager($timenow)->get_time_left_display($this->attempt, $timenow);
    }

    /**
     * @return int the time when this attempt was submitted. 0 if it has not been
     * submitted yet.
     */
    public function get_submitted_date() {
        return $this->attempt->timefinish;
    }

    /**
     * If the attempt is in an applicable state, work out the time by which the
     * student should next do something.
     * @return int timestamp by which the student needs to do something.
     */
    public function get_due_date() {
        $deadlines = array();
        if ($this->prova->get_prova()->timelimit) {
            $deadlines[] = $this->attempt->timestart + $this->prova->get_prova()->timelimit;
        }
        if ($this->prova->get_prova()->timeclose) {
            $deadlines[] = $this->prova->get_prova()->timeclose;
        }
        if ($deadlines) {
            $duedate = min($deadlines);
        } else {
            return false;
        }

        switch ($this->attempt->state) {
            case self::IN_PROGRESS:
                return $duedate;

            case self::OVERDUE:
                return $duedate + $this->prova->get_prova()->graceperiod;

            default:
                throw new coding_exception('Unexpected state: ' . $this->attempt->state);
        }
    }

    /**
     * Wrapper round the has_capability funciton that automatically passes in the prova context.
     */
    public function has_capability($capability, $userid = null, $doanything = true) {
        return $this->prova->has_capability($capability, $userid, $doanything);
    }

    /**
     * Wrapper round the require_capability funciton that automatically passes in the prova context.
     */
    public function require_capability($capability, $userid = null, $doanything = true) {
        return $this->prova->require_capability($capability, $userid, $doanything);
    }

    /**
     * Check the appropriate capability to see whether this user may review their own attempt.
     * If not, prints an error.
     */
    public function check_review_capability() {
        if ($this->get_attempt_state() == mod_prova_display_options::IMMEDIATELY_AFTER) {
            $capability = 'mod/prova:attempt';
        } else {
            $capability = 'mod/prova:reviewmyattempts';
        }

        // These next tests are in a slighly funny order. The point is that the
        // common and most performance-critical case is students attempting a prova
        // so we want to check that permisison first.

        if ($this->has_capability($capability)) {
            // User has the permission that lets you do the prova as a student. Fine.
            return;
        }

        if ($this->has_capability('mod/prova:viewreports') ||
                $this->has_capability('mod/prova:preview')) {
            // User has the permission that lets teachers review. Fine.
            return;
        }

        // They should not be here. Trigger the standard no-permission error
        // but using the name of the student capability.
        // We know this will fail. We just want the stadard exception thown.
        $this->require_capability($capability);
    }


    // Methods for processing ==================================================
    /**
     * Check this attempt, to see if there are any state transitions that should
     * happen automatically.  This function will update the attempt checkstatetime.
     * @param int $timestamp the timestamp that should be stored as the modifed
     * @param bool $studentisonline is the student currently interacting with Moodle?
     */
    public function handle_if_time_expired($timestamp, $studentisonline, $unlocker=true) {
        global $DB;

        $timeclose = $this->get_access_manager($timestamp)->get_end_time($this->attempt);

        if ($timeclose === false || $this->is_preview()) {
            $this->update_timecheckstate(null);
            return; // No time limit
        }
        if ($timestamp < $timeclose) {
            $this->update_timecheckstate($timeclose);
            return; // Time has not yet expired.
        }

        // If the attempt is already overdue, look to see if it should be abandoned ...
        if ($this->attempt->state == self::OVERDUE) {
            $timeoverdue = $timestamp - $timeclose;
            $graceperiod = $this->prova->get_prova()->graceperiod;
            if ($timeoverdue >= $graceperiod) {
                $this->process_abandon($timestamp, $studentisonline);
            } else {
                // Overdue time has not yet expired
                $this->update_timecheckstate($timeclose + $graceperiod);
            }
            return; // ... and we are done.
        }

        if ($this->attempt->state != self::IN_PROGRESS) {
            $this->update_timecheckstate(null);
            return; // Attempt is already in a final state.
        }

        // Otherwise, we were in prova_attempt::IN_PROGRESS, and time has now expired.
        // Transition to the appropriate state.
        $this->process_finish($timestamp, false);
        return;

        // This is an overdue attempt with no overdue handling defined, so just abandon.
        $this->process_abandon($timestamp, $studentisonline);
        return;
    }

    /**
     * Update this attempt timecheckstate if necessary.
     * @param int|null the timecheckstate
     */
    public function update_timecheckstate($time) {
        global $DB;
        if ($this->attempt->timecheckstate !== $time) {
            $this->attempt->timecheckstate = $time;
            $DB->set_field('prova_attempts', 'timecheckstate', $time, array('id' => $this->attempt->id));
        }
    }

    /**
     * Check a page access to see if is an out of sequence access.
     *
     * @param  int $page page number
     * @return boolean false is is an out of sequence access, true otherwise.
     * @since Moodle 3.1
     */
    public function check_page_access($page) {
        global $DB;

        if ($this->get_currentpage() != $page) {
            if ($this->get_navigation_method() == PROVA_NAVMETHOD_SEQ && $this->get_currentpage() > $page) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update attempt page.
     *
     * @param  int $page page number
     * @return boolean true if everything was ok, false otherwise (out of sequence access).
     * @since Moodle 3.1
     */
    public function set_currentpage($page) {
        global $DB;

        if ($this->check_page_access($page)) {
            $DB->set_field('prova_attempts', 'currentpage', $page, array('id' => $this->get_attemptid()));
            return true;
        }
        return false;
    }

    /**
     * Process all the autosaved data that was part of the current request.
     *
     * @param int $timestamp the timestamp that should be stored as the modifed
     * time in the database for these actions. If null, will use the current time.
     */
 /*TIRAR   public function process_auto_save($timestamp) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $this->quba->process_all_autosaves($timestamp);
        question_engine::save_questions_usage_by_activity($this->quba);

        $transaction->allow_commit();
    }TIRAR*/


    /**
     * Finaliza um attempt de um estudante.
     * @param int $timestamp the timestamp that should be stored as the modifed
     * @param bool whether this is a manual submit or not
     */
    /*TIRAR  public function process_finish($timestamp, $processsubmitted) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($processsubmitted) {
            $this->quba->process_all_actions($timestamp);
        }
        $this->quba->finish_all_questions($timestamp);

        question_engine::save_questions_usage_by_activity($this->quba);

        $realtotalmark = update_sumgrades_question_canceled_at_attempt_process_finish($this->attempt, $this->prova->get_prova(), $this->quba);
  
        $this->attempt->timemodified = $timestamp;
        $this->attempt->timefinish = $timestamp;
        $this->attempt->sumgrades = $realtotalmark;
        $this->attempt->state = self::FINISHED;
        $this->attempt->timecheckstate = null;
        $DB->update_record('prova_attempts', $this->attempt);

        if (!$this->is_preview()) {
            prova_save_best_grade($this->prova->get_prova(), $this->attempt->userid);

            // Trigger event.
            $this->fire_state_transition_event('\mod_prova\event\attempt_submitted', $timestamp);

            // Tell any access rules that care that the attempt is over.
            $this->get_access_manager($timestamp)->current_attempt_finished();
        }

        $transaction->allow_commit();
    }TIRAR*/

    /**
     * Fire a state transition event.
     * the same event information.
     * @param string $eventclass the event class name.
     * @param int $timestamp the timestamp to include in the event.
     * @return void
     */
    protected function fire_state_transition_event($eventclass, $timestamp) {
        global $USER;
        $provarecord = $this->prova->get_prova();
        $params = array(
            'context' => $this->prova->get_context(),
            'courseid' => $this->get_courseid(),
            'objectid' => $this->attempt->id,
            'relateduserid' => $this->attempt->userid,
            'other' => array(
                'submitterid' => CLI_SCRIPT ? null : $USER->id,
                'provaid' => $provarecord->id
            )
        );

        $event = $eventclass::create($params);
        $event->add_record_snapshot('prova', $this->prova->get_prova());
        $event->add_record_snapshot('prova_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Trigger the attempt_viewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_viewed_event() {
        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'provaid' => $this->get_provaid()
            )
        );
        $event = \mod_prova\event\attempt_viewed::create($params);
        $event->add_record_snapshot('prova_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Trigger the attempt_summary_viewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_summary_viewed_event() {

        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'provaid' => $this->get_provaid()
            )
        );
        $event = \mod_prova\event\attempt_summary_viewed::create($params);
        $event->add_record_snapshot('prova_attempts', $this->get_attempt());
        $event->trigger();
    }

    /**
     * Mark this attempt as now overdue.
     * @param int $timestamp the time to deem as now.
     * @param bool $studentisonline is the student currently interacting with Moodle?
     */
    public function process_going_overdue($timestamp, $studentisonline) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->attempt->timemodified = $timestamp;
        $this->attempt->state = self::OVERDUE;
        // If we knew the attempt close time, we could compute when the graceperiod ends.
        // Instead we'll just fix it up through cron.
        $this->attempt->timecheckstate = $timestamp;
        $DB->update_record('prova_attempts', $this->attempt);

        $this->fire_state_transition_event('\mod_prova\event\attempt_becameoverdue', $timestamp);

        $transaction->allow_commit();

        prova_send_overdue_message($this);
    }

    /**
     * Process all the actions that were submitted as part of the current request.
     *
     * @param int  $timestamp  the timestamp that should be stored as the modifed
     *                         time in the database for these actions. If null, will use the current time.
     * @param bool $becomingoverdue
     * @param array|null $simulatedresponses If not null, then we are testing, and this is an array of simulated data.
     *      There are two formats supported here, for historical reasons. The newer approach is to pass an array created by
     *      {@link core_question_generator::get_simulated_post_data_for_questions_in_usage()}.
     *      the second is to pass an array slot no => contains arrays representing student
     *      responses which will be passed to {@link question_definition::prepare_simulated_post_data()}.
     *      This second method will probably get deprecated one day.
     */
    public function process_submitted_actions($timestamp, $becomingoverdue = false, $simulatedresponses = null) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($simulatedresponses !== null) {
            if (is_int(key($simulatedresponses))) {
                // Legacy approach. Should be removed one day.
                $simulatedpostdata = $this->quba->prepare_simulated_post_data($simulatedresponses);
            } else {
                $simulatedpostdata = $simulatedresponses;
            }
        } else {
            $simulatedpostdata = null;
        }

        $this->quba->process_all_actions($timestamp, $simulatedpostdata);
      /*tira  question_engine::save_questions_usage_by_activity($this->quba); tira*/

        $this->attempt->timemodified = $timestamp;
        if ($this->attempt->state == self::FINISHED) {
            $this->attempt->sumgrades = $this->quba->get_total_mark();
        }
        if ($becomingoverdue) {
            $this->process_going_overdue($timestamp, true);
        } else {
            $DB->update_record('prova_attempts', $this->attempt);
        }

        if (!$this->is_preview() && $this->attempt->state == self::FINISHED) {
            prova_save_best_grade($this->prova->get_prova(), $this->get_userid());
        }

        $transaction->allow_commit();
    }

    /**
     * Mark this attempt as abandoned.
     * @param int $timestamp the time to deem as now.
     * @param bool $studentisonline is the student currently interacting with Moodle?
     */
    public function process_abandon($timestamp, $studentisonline) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->attempt->timemodified = $timestamp;
        $this->attempt->state = self::ABANDONED;
        $this->attempt->timecheckstate = null;
        $DB->update_record('prova_attempts', $this->attempt);

        $this->fire_state_transition_event('\mod_prova\event\attempt_abandoned', $timestamp);

        $transaction->allow_commit();
    }

    /**
     * Process responses during an attempt at a prova.
     *
     * @param  int $timenow time when the processing started
     * @param  bool $finishattempt whether to finish the attempt or not
     * @param  bool $timeup true if form was submitted by timer
     * @param  int $thispage current page number
     * @return string the attempt state once the data has been processed
     * @since  Moodle 3.1
     * @throws  moodle_exception
     */
    public function process_attempt($timenow, $finishattempt, $timeup, $thispage) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // If there is only a very small amount of time left, there is no point trying
        // to show the student another page of the prova. Just finish now.
        $graceperiodmin = null;
        $accessmanager = $this->get_access_manager($timenow);
        $timeclose = $accessmanager->get_end_time($this->get_attempt());

        // Don't enforce timeclose for previews.
        if ($this->is_preview()) {
            $timeclose = false;
        }
        $toolate = false;
        if ($timeclose !== false && $timenow > $timeclose - PROVA_MIN_TIME_TO_CONTINUE) {
            $timeup = true;
            $graceperiodmin = $this->prova->get_prova()->graceperiod;
            if ($timenow > $timeclose + $graceperiodmin) {
                $toolate = true;
            }
        }

        // If time is running out, trigger the appropriate action.
        $becomingoverdue = false;
        $becomingabandoned = false;
        if ($timeup) {
            $finishattempt = true;
        }

        // Don't log - we will end with a redirect to a page that is logged.

        if (!$finishattempt) {
            // Just process the responses for this page and go to the next page.
            if (!$toolate) {
                try {
                    $this->process_submitted_actions($timenow, $becomingoverdue);

                } catch (question_out_of_sequence_exception $e) {
                    throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                            $this->attempt_url(null, $thispage));

                } catch (Exception $e) {
                    // This sucks, if we display our own custom error message, there is no way
                    // to display the original stack trace.
                    $debuginfo = '';
                    if (!empty($e->debuginfo)) {
                        $debuginfo = $e->debuginfo;
                    }
                    throw new moodle_exception('errorprocessingresponses', 'question',
                            $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
                }

                if (!$becomingoverdue) {
                    foreach ($this->get_slots() as $slot) {
                        if (optional_param('redoslot' . $slot, false, PARAM_BOOL)) {
                            $this->process_redo_question($slot, $timenow);
                        }
                    }
                }

            } else {
                // The student is too late.
                $this->process_going_overdue($timenow, true);
            }

            $transaction->allow_commit();

            return $becomingoverdue ? self::OVERDUE : self::IN_PROGRESS;
        }

        // Update the prova attempt record.
        try {
            if ($becomingabandoned) {
                $this->process_abandon($timenow, true);
            } else {
                $this->process_finish($timenow, !$toolate);
            }

        } catch (question_out_of_sequence_exception $e) {
            throw new moodle_exception('submissionoutofsequencefriendlymessage', 'question',
                    $this->attempt_url(null, $thispage));

        } catch (Exception $e) {
            // This sucks, if we display our own custom error message, there is no way
            // to display the original stack trace.
            $debuginfo = '';
            if (!empty($e->debuginfo)) {
                $debuginfo = $e->debuginfo;
            }
            throw new moodle_exception('errorprocessingresponses', 'question',
                    $this->attempt_url(null, $thispage), $e->getMessage(), $debuginfo);
        }

        // Send the user to the review page.
        $transaction->allow_commit();

        return $becomingabandoned ? self::ABANDONED : self::FINISHED;
    }

    /**
     * Trigger the attempt_reviewed event.
     *
     * @since Moodle 3.1
     */
    public function fire_attempt_reviewed_event() {

        $params = array(
            'objectid' => $this->get_attemptid(),
            'relateduserid' => $this->get_userid(),
            'courseid' => $this->get_courseid(),
            'context' => context_module::instance($this->get_cmid()),
            'other' => array(
                'provaid' => $this->get_provaid()
            )
        );
        $event = \mod_prova\event\attempt_reviewed::create($params);
        $event->add_record_snapshot('prova_attempts', $this->get_attempt());
        $event->trigger();
    }


    // Bits of content =========================================================
    /**
     * If $reviewoptions->attempt is false, meaning that students can't review this
     * attempt at the moment, return an appropriate string explaining why.
     *
     * @param bool $short if true, return a shorter string.
     * @return string an appropraite message.
     */
    public function cannot_review_message($short = false) {
        return $this->prova->cannot_review_message($this->get_attempt_state(), $short);
    }

    /**
     * Initialise the JS etc. required all the questions on a page.
     * @param mixed $page a page number, or 'all'.
     */
    public function get_html_head_contributions($page = 'all', $showall = false) {
        if ($showall) {
            $page = 'all';
        }
        $result = '';
        foreach ($this->get_slots($page) as $slot) {
            $result .= $this->quba->render_question_head_html($slot);
        }
        $result .= question_engine::initialise_js();
        return $result;
    }

    /**
     * Generate the HTML that displayes the question in its current state, with
     * the appropriate display options.
     *
     * @param int $slot identifies the question in the attempt.
     * @param bool $reviewing is the being printed on an attempt or a review page.
     * @param mod_prova_renderer $renderer the prova renderer.
     * @param moodle_url $thispageurl the URL of the page this question is being printed on.
     * @return string HTML for the question in its current state.
     */
    public function render_question($slot, $reviewing, mod_prova_renderer $renderer, $thispageurl = null) {
        return $this->render_question_helper($slot, $reviewing, $thispageurl, $renderer, null);
    }

    /**
     * Helper used by {@link render_question()} and {@link render_question_at_step()}.
     *
     * @param int $slot identifies the question in the attempt.
     * @param bool $reviewing is the being printed on an attempt or a review page.
     * @param moodle_url $thispageurl the URL of the page this question is being printed on.
     * @param mod_prova_renderer $renderer the prova renderer.
     * @param int|null $seq the seq number of the past state to display.
     * @return string HTML fragment.
     */
    protected function render_question_helper($slot, $reviewing, $thispageurl, mod_prova_renderer $renderer, $seq) {
        $originalslot = $this->get_original_slot($slot);
        $number = $this->get_question_number($originalslot);
        $displayoptions = $this->get_display_options_with_edit_link($reviewing, $slot, $thispageurl);

        if ($slot != $originalslot) {
            $originalmaxmark = $this->get_question_attempt($slot)->get_max_mark();
            $this->get_question_attempt($slot)->set_max_mark($this->get_question_attempt($originalslot)->get_max_mark());
        }

        if ($seq === null) {
            $output = $this->quba->render_question($slot, $displayoptions, $number);
        } else {
            $output = $this->quba->render_question_at_step($slot, $seq, $displayoptions, $number);
        }

        if ($slot != $originalslot) {
            $this->get_question_attempt($slot)->set_max_mark($originalmaxmark);
        }

        return $output;
    }

    /**
     * Like {@link render_question()} but displays the question at the past step
     * indicated by $seq, rather than showing the latest step.
     *
     * @param int $id the id of a question in this prova attempt.
     * @param int $seq the seq number of the past state to display.
     * @param bool $reviewing is the being printed on an attempt or a review page.
     * @param mod_prova_renderer $renderer the prova renderer.
     * @param string $thispageurl the URL of the page this question is being printed on.
     * @return string HTML for the question in its current state.
     */
    public function render_question_at_step($slot, $seq, $reviewing, mod_prova_renderer $renderer, $thispageurl = '') {
        return $this->render_question_helper($slot, $reviewing, $thispageurl, $renderer, $seq);
    }

    /**
     * Wrapper round print_question from lib/questionlib.php.
     *
     * @param int $id the id of a question in this prova attempt.
     */
    public function render_question_for_commenting($slot) {
        $options = $this->get_display_options(true);
        $options->hide_all_feedback();
        $options->manualcomment = question_display_options::EDITABLE;
        return $this->quba->render_question($slot, $options,
                $this->get_question_number($slot));
    }

    /**
     * Check wheter access should be allowed to a particular file.
     *
     * @param int $id the id of a question in this prova attempt.
     * @param bool $reviewing is the being printed on an attempt or a review page.
     * @param string $thispageurl the URL of the page this question is being printed on.
     * @return string HTML for the question in its current state.
     */
    public function check_file_access($slot, $reviewing, $contextid, $component,
            $filearea, $args, $forcedownload) {
        $options = $this->get_display_options($reviewing);

        // Check permissions - warning there is similar code in review.php and
        // reviewquestion.php. If you change on, change them all.
        if ($reviewing && $this->is_own_attempt() && !$options->attempt) {
            return false;
        }

        if ($reviewing && !$this->is_own_attempt() && !$this->is_review_allowed()) {
            return false;
        }

        return $this->quba->check_file_access($slot, $options,
                $component, $filearea, $args, $forcedownload);
    }


    // URLs related to this attempt ============================================
    /**
     * @return string prova view url.
     */
    public function view_url() {
        return $this->prova->view_url();
    }

    /**
     * @return string the URL of this prova's edit page. Needs to be POSTed to with a cmid parameter.
     */
    public function start_attempt_url($slot = null, $page = -1) {
        if ($page == -1 && !is_null($slot)) {
            $page = $this->get_question_page($slot);
        } else {
            $page = 0;
        }
        return $this->prova->start_attempt_url($page);
    }

    /**
     * Generates the title of the review page.
     *
     * @param int $page the page number (starting with 0) in the attempt.
     * @param bool $showall whether the review page contains the entire attempt on one page.
     * @return string
     */
    public function review_page_title(int $page, bool $showall = false) : string {
        if (!$showall && $this->get_num_pages() > 1) {
            $a = new stdClass();
            $a->name = $this->get_prova_name();
            $a->currentpage = $page + 1;
            $a->totalpages = $this->get_num_pages();
            $title = get_string('attemptreviewtitlepaged', 'prova', $a);
        } else {
            $title = get_string('attemptreviewtitle', 'prova', $this->get_prova_name());
        }

        return $title;
    }

    /**
     * Course title to print the prova review
     *
     * @return string
     */
    public function review_print_course_title() : string {
        global $COURSE;
        $a = $COURSE->fullname;
        return get_string('attemptprintcoursetitle', 'prova', $a);
    }

    /**
     * Custom title to print the prova review
     *
     * @return string
     */
    public function review_print_title() : string {      
        $a = $this->get_prova_name();
        return get_string('attemptprinttitle', 'prova', $a);
    }

    /**
     * Custom user title to print the prova review
     *
     * @return string
     */
    public function user_print_title() : string {
        $user = $this->get_userdata();
        $a = $user->firstname . ' ' . $user->lastname;

        return get_string('userprinttitle', 'prova', $a);
    }

    /**
     * Generates the title of the summary page.
     *
     * @return string
     */
    public function summary_page_title() : string {
        return get_string('attemptsummarytitle', 'prova', $this->get_prova_name());
    }

    /**
     * Generates the title of the attempt page.
     *
     * @param int $page the page number (starting with 0) in the attempt.
     * @return string
     */
    public function attempt_page_title(int $page) : string {
        if ($this->get_num_pages() > 1) {
            $a = new stdClass();
            $a->name = $this->get_prova_name();
            $a->currentpage = $page + 1;
            $a->totalpages = $this->get_num_pages();
            $title = get_string('attempttitlepaged', 'prova', $a);
        } else {
            $title = get_string('attempttitle', 'prova', $this->get_prova_name());
        }

        return $title;
    }

    /**
     * @param int $slot if speified, the slot number of a specific question to link to.
     * @param int $page if specified, a particular page to link to. If not givem deduced
     *      from $slot, or goes to the first page.
     * @param int $thispage if not -1, the current page. Will cause links to other things on
     * this page to be output as only a fragment.
     * @return string the URL to continue this attempt.
     */
    public function attempt_url($slot = null, $page = -1, $thispage = -1) {
        return $this->page_and_question_url('attempt', $slot, $page, false, $thispage);
    }

    /**
     * @return string the URL of this prova's summary page.
     */
    public function processattempt_url() {
        return new moodle_url('/mod/prova/screens/attempt/processattempt.php', array('attempt' => $this->get_attemptid()));
    }

    /**
     * @param int $slot indicates which question to link to.
     * @param int $page if specified, the URL of this particular page of the attempt, otherwise
     * the URL will go to the first page.  If -1, deduce $page from $slot.
     * @param bool|null $showall if true, the URL will be to review the entire attempt on one page,
     * and $page will be ignored. If null, a sensible default will be chosen.
     * @param int $thispage if not -1, the current page. Will cause links to other things on
     * this page to be output as only a fragment.
     * @return string the URL to review this attempt.
     */
    public function review_url($slot = null, $page = -1, $showall = null, $thispage = -1) {
        return $this->page_and_question_url('review', $slot, $page, $showall, $thispage);
    }

    /**
     * @param int $slot indicates which question to link to.
     * @param array $slots conjunto de slots de uma prova
     * @param int $page chaves do conjunto de slots da prova
     * @return string the URL to review prova questions.
     */
    public function reviewquestion_url($slot, $slots, $page) {
        return new moodle_url('/mod/prova/screens/report/reviewquestion.php', array(
            'attempt'   => $this->get_attemptid(),
            'slot'      => $slot,
            'slots'      => $slots,
            'page'      => $page
        ));
    }

    /**
     * @return string the URL to report the prova.
     */
    public function get_report_url() {
        return $this->prova->get_report_url();
    }

    /**
     * By default, should this script show all questions on one page for this attempt?
     * @param string $script the script name, e.g. 'attempt', 'summary', 'review'.
     * @return whether show all on one page should be on by default.
     */
    public function get_default_show_all($script) {
        return $script == 'review' && count($this->questionpages) < self::MAX_SLOTS_FOR_DEFAULT_REVIEW_SHOW_ALL;
    }

    /**
     * @return string the URL of this prova's summary page.
     */
    public function summary_url() {
        return new moodle_url('/mod/prova/screens/attempt/summary.php', array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
    }

    /**
     * Get a URL for a particular question on a particular page of the prova.
     * Used by {@link attempt_url()} and {@link review_url()}.
     *
     * @param string $script. Used in the URL like /mod/prova/$script.php
     * @param int $slot identifies the specific question on the page to jump to.
     *      0 to just use the $page parameter.
     * @param int $page -1 to look up the page number from the slot, otherwise
     *      the page number to go to.
     * @param bool|null $showall if true, return a URL with showall=1, and not page number.
     *      if null, then an intelligent default will be chosen.
     * @param int $thispage the page we are currently on. Links to questions on this
     *      page will just be a fragment #q123. -1 to disable this.
     * @return The requested URL.
     */
    protected function page_and_question_url($script, $slot, $page, $showall, $thispage) {

        $defaultshowall = $this->get_default_show_all($script);
        if ($showall === null && ($page == 0 || $page == -1)) {
            $showall = $defaultshowall;
        }

        // Fix up $page.
        if ($page == -1) {
            if ($slot !== null && !$showall) {
                $page = $this->get_question_page($slot);
            } else {
                $page = 1;
            }
        }

        if ($showall) {
            $page = 1;
        }

        // Add a fragment to scroll down to the question.
        $fragment = '';
        if ($slot !== null) {
            $pagelayout = array($this->questionpages[$page]);
            if ($slot == reset($pagelayout)) {
                // First question on page, go to top.
                $fragment = '#';
            } else {
                $qa = $this->get_question_attempt($slot);
                $fragment = '#' . $qa->get_outer_question_div_unique_id();
            }
        }

        // Work out the correct start to the URL.
        if ($thispage == $page) {
            return new moodle_url($fragment);

        } else {
            $url = new moodle_url('/mod/prova/screens/attempt/' . $script . '.php' . $fragment,
                    array('attempt' => $this->attempt->id, 'cmid' => $this->get_cmid()));
            if ($page == 1 && $showall != $defaultshowall) {
                $url->param('showall', (int) $showall);
            } else if ($page > 1) {
                $url->param('page', $page);
            }

            return $url;
        }
    }

    /**
     * Return an array of variant URLs to other attempts at this prova.
     *
     * The $url passed in must contain an attempt parameter.
     *
     * The {@link mod_prova_links_to_other_attempts} object returned contains an
     * array with keys that are the attempt number, 1, 2, 3.
     * The array values are either a {@link moodle_url} with the attmept parameter
     * updated to point to the attempt id of the other attempt, or null corresponding
     * to the current attempt number.
     *
     * @param moodle_url $url a URL.
     * @return mod_prova_links_to_other_attempts containing array int => null|moodle_url.
     */
    public function links_to_other_attempts(moodle_url $url) {
        $attempts = prova_get_user_attempts($this->prova->get_prova()->id, $this->attempt->userid, 'all');
        if (count($attempts) <= 1) {
            return false;
        }

        $links = new mod_prova_links_to_other_attempts();
        foreach ($attempts as $at) {
            if ($at->id == $this->attempt->id) {
                $links->links[$at->attempt] = null;
            } else {
                $links->links[$at->attempt] = new moodle_url($url, array('attempt' => $at->id));
            }
        }
        return $links;
    }


    // Custom Methods ==========================================================
    /**
     * @return \int prova aplication id
     */
    public function get_modprovaid() {
        return $this->prova->get_provaid();
    }

    // /**
    //  * Get the navigation panel object for this attempt.
    //  *
    //  * @param $panelclass The type of panel, prova_attempt_nav_panel or prova_review_nav_panel
    //  * @param $page the current page number.
    //  * @param $showall whether we are showing the whole prova on one page. (Used by review.php)
    //  * @return prova_nav_panel_base the requested object.
    //  */
    // public function get_navigation_panel(mod_prova_renderer $output,
    //          $panelclass, $page, $showall = false) {
    //     $panel = new $panelclass($this, $this->get_display_options(true), $page, $showall);

    //     $bc = new block_contents();
    //     $bc->attributes['id'] = 'mod_prova_navblock';
    //     $bc->attributes['role'] = 'navigation';
    //     $bc->attributes['aria-labelledby'] = 'mod_prova_navblock_title';
    //     $bc->title = html_writer::span(get_string('provanavigation', 'prova'), '', array('id' => 'mod_prova_navblock_title'));
    //     $bc->content = $output->navigation_panel($panel);
    //     return $bc;
    // }
}

/**
 * Represents the navigation panel, and builds a {@link block_contents} to allow
 * it to be output.
 */
abstract class prova_nav_panel_base {

    /** @var prova_attempt */
    protected $attemptobj;
    /** @var question_display_options */
    protected $options;
    /** @var integer */
    protected $page;
    /** @var boolean */
    protected $showall;

    public function __construct($attemptobj, question_display_options $options, $page, $showall) {
        $this->attemptobj = $attemptobj;
        $this->options = $options;
        $this->page = $page;
        $this->showall = $showall;
    }

    /**
     * Get the buttons and section headings to go in the prova navigation block.
     * @return renderable[] the buttons, possibly interleaved with section headings.
     */
    public function get_question_buttons() {
        $buttons = array();
        foreach ($this->attemptobj->get_slots() as $slot) {
            if ($heading = $this->attemptobj->get_heading_before_slot($slot)) {
                $buttons[] = new prova_nav_section_heading(format_string($heading));
            }

            $qa = $this->attemptobj->get_question_attempt($slot);
            $showcorrectness = $this->options->correctness && $qa->has_marks();

            $button = new prova_nav_question_button();
            $button->id          = 'quiznavbutton' . $slot;
            $button->number      = $slot;
            $button->stateclass  = $qa->get_state_class($showcorrectness);
            $button->navmethod   = 'free';
            if (!$showcorrectness && $button->stateclass == 'notanswered') {
                $button->stateclass = 'complete';
            }
            $button->statestring = $this->get_state_string($qa, $showcorrectness);
            $button->page        = $slot;
            $button->currentpage = $this->showall || $button->page == $this->page;
            $button->flagged     = $qa->is_flagged();
            $button->url         = $this->get_question_url($slot);

            $buttons[] = $button;
        }

        return $buttons;
    }

    protected function get_state_string(question_attempt $qa, $showcorrectness) {
        if ($qa->get_question()->length > 0) {
            return $qa->get_state_string($showcorrectness);
        }

        // Special case handling for 'information' items.
        if ($qa->get_state() == question_state::$todo) {
            return get_string('notyetviewed', 'prova');
        } else {
            return get_string('viewed', 'prova');
        }
    }

    public function render_before_button_bits(mod_prova_renderer $output) {
        return '';
    }

    abstract public function render_end_bits(mod_prova_renderer $output);

    protected function render_restart_preview_link($output) {
        if ($this->attemptobj->is_own_preview()) {
            return '';
        }
        return $output->restart_preview_button(new moodle_url(
                $this->attemptobj->start_attempt_url(), array('forcenew' => true)));
    }

    protected abstract function get_question_url($slot);

    public function user_picture() {
        return null;
    }

    /**
     * Return 'allquestionsononepage' as CSS class name when $showall is set,
     * otherwise, return 'multipages' as CSS class name.
     * @return string, CSS class name
     */
    public function get_button_container_class() {
        // Prova navigation is set on 'Show all questions on one page'.
        if ($this->showall) {
            return 'allquestionsononepage';
        }
        // Prova navigation is set on 'Show one page at a time'.
        return 'multipages';
    }
}

/**
 * Specialisation of {@link prova_nav_panel_base} for the attempt prova page.
 *
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */
class prova_attempt_nav_panel extends prova_nav_panel_base {
    public function get_question_url($slot) {
        // if ($this->attemptobj->can_navigate_to($slot)) {
        if ($this->attemptobj->can_navigate_to($slot)) {
            return $this->attemptobj->attempt_url($slot, -1, $this->page);
        } else {
            return null;
        }
    }

    public function render_before_button_bits(mod_prova_renderer $output) {
        return html_writer::tag('div', get_string('navnojswarning', 'prova'),
                array('id' => 'quiznojswarning'));
    }

    public function render_end_bits(mod_prova_renderer $output) {
        return html_writer::link($this->attemptobj->summary_url(),
                get_string('endtest', 'prova'), array('class' => 'endtestlink')) .
                $output->countdown_timer($this->attemptobj, time()) .
                $this->render_restart_preview_link($output);
    }
}

/**
 * Specialisation of {@link prova_nav_panel_base} for the review prova page.
 *
 * @copyright  2008 Tim Hunt
 */
class prova_review_nav_panel extends prova_nav_panel_base {
    public function get_question_url($slot) {
        return $this->attemptobj->review_url($slot, -1, $this->showall, $this->page);
    }

    public function render_end_bits(mod_prova_renderer $output) {
        $html = '';
        if ($this->attemptobj->get_num_pages() > 1) {
            if ($this->showall) {
                $html .= html_writer::link($this->attemptobj->review_url(null, 0, false),get_string('showeachpage', 'prova'));
            } else {
                $html .= html_writer::link($this->attemptobj->review_url(null, 0, true),get_string('showall', 'prova'));
            }
        }
        $html .= $output->finish_review_link($this->attemptobj);
        $html .= $this->render_restart_preview_link($output);
        return $html;
    }

    public function render_end_bits_reviewquestion(mod_prova_renderer $output, $returnurl) {
        $html = '';
        if($returnurl) {
            $html .= $output->finish_review_question_link($returnurl);
        } 
        return $html;
    }
}

/**
 * Specialisation of {@link prova_nav_panel_base} for the review prova page.
 *
 * @copyright  2008 Tim Hunt
 */
class prova_reviewquestion_nav_panel extends prova_nav_panel_base {
    protected $slots;
    protected $arrayslots;

    public function set_question_url_params($slots) {
        $this->slots = $slots;
        $this->arrayslots = explode('-', $slots);
    }

    public function get_question_url($slot) {
        $page = array_search($slot, $this->arrayslots);
        return $this->attemptobj->reviewquestion_url($slot, $this->slots, $page);
    }

    public function render_end_bits(mod_prova_renderer $output) {
        $html = $output->finish_review_question_link($this->attemptobj->get_report_url());
        return $html;
    }
}


/**
 * Represents a single link in the navigation panel.
 */
class prova_nav_question_button implements renderable {
    /** @var string id="..." to add to the HTML for this button. */
    public $id;
    /** @var string number to display in this button. Either the question number of 'i'. */
    public $number;
    /** @var string class to add to the class="" attribute to represnt the question state. */
    public $stateclass;
    /** @var string Textual description of the question state, e.g. to use as a tool tip. */
    public $statestring;
    /** @var int the page number this question is on. */
    public $page;
    /** @var bool true if this question is on the current page. */
    public $currentpage;
    /** @var bool true if this question has been flagged. */
    public $flagged;
    /** @var moodle_url the link this button goes to, or null if there should not be a link. */
    public $url;
}
