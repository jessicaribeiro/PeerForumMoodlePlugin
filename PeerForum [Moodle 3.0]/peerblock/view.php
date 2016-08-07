<?php

/**
 * @package    block
 * @subpackage peerblock
 * @copyright  2016 Jessica Ribeiro
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
global $CFG, $USER;

require_once('peerblock_form.php');

$blockid = required_param('blockid', PARAM_INT);
$courseid = required_param('courseid',PARAM_INT);

if (! $course = get_record('course', 'id', $courseid) ) {
error(get_string('invalidcourse', 'block_peerblock'). $courseid);
}

require_login($course);

$peerblock = new peerblock_form();

if ($peerblock->is_cancelled()) {
    // cancelled forms redirect to course main page
    redirect("$CFG->wwwroot/course/view.php?id=$id");

} else if ($fromform = $peerblock->get_data()) {
    // we need to add code to appropriately act on and store the submitted data
    if(!insert_record('block_peerblock',$fromform)){
        error(get_string('inserterror' , 'block_peerblock'));

    redirect("$CFG->wwwroot/course/view.php? id=$courseid");
}

} else {
    //form didn't validate or this is the first display
    $site = get_site();

    print_header(strip_tags($site->fullname), $site->fullname,
                       '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courseid.'">'.$course->shortname.
                       '</a> ->'.get_string('formtitle', 'block_peerblock'), '',
                       '<meta name="description" content="'. s(strip_tags($site->summary))    .'">',
                       true, '', '');

//These elements will need to be populated by displaying code because these will be passed in via the URL initially
   $toform['blockid'] = $blockid;
   $toform['courseid'] = $courseid;
   $peerblock->set_data($toform);


    $peerblock->display();

    print_footer();
}
?>
