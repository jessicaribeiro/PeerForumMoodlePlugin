<?php
//blocos normais
//class block_peerblock extends block_base {

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

class block_peerblock extends block_list {

    public function init() {
        $this->title = get_string('peerblock', 'block_peerblock');
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return false;
    }

    public function instance_allow_config() {
        return true;
    }

    public function applicable_formats() {
        return array(
                'admin' => true,
                'site-index' => true,
                'course-view' => true,
                'course-view-social' => true,
                'mod' => true,
                'my' => true
        );
    }

    public function specialization() {
        if (empty($this->config->title)) {
            $this->title = get_string('peerblock', 'block_peerblock');
        } else {
            $this->title = $this->config->title;
        }
    }

    public function get_content() {
        global $USER, $PAGE, $CFG, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        // Create empty content.
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->icons  = array();
        $this->content->items  = array();


        if (!empty($CFG->enablepeergrade)) {
            $this->content->text .= get_string('enablepeergrade', 'block_peerblock');
            return $this->content;
        }

        $courseid = $this->page->course->id;
        if ($courseid == SITEID) {
            $courseid = null;
        }

        require_once($CFG->dirroot.'/blocks/peerblock/lib.php');


        $num_posts = get_num_posts_to_grade($USER->id, $COURSE->id);
        $time_old_post = get_time_old_post($USER->id, $COURSE->id);

        //$time_old_post = date("H:i", strtotime("$time_old_post->h:$time_old_post->i"));
        if(!empty($time_old_post)){
            $time_old_post = $time_old_post->h.'h:'.$time_old_post->i.'m';
        } else {
            $time_old_post = '00h00m';
        }


        $contextid = context_course::instance($COURSE->id);
        $peerforumid = $contextid->instanceid;

        $this->content->items[] = html_writer::link(new moodle_url($CFG->wwwroot.'/peergrading/index.php', array('courseid'=>$this->page->course->id, 'userid'=> $USER->id, 'display' => 1, 'peerforum' => $peerforumid)), get_string('viewpanel', 'block_peerblock'), array('title'=>get_string('viewpanel', 'block_peerblock')));
        $this->content->icons[] = html_writer::empty_tag('img', array('src' => new moodle_url('/blocks/peerblock/pix/icon.png'), 'class' => 'icon'));
        $this->content->items[] = html_writer::empty_tag('br');


        if(!has_capability('mod/peerforum:viewpanelpeergrades', $PAGE->context)){
            $this->content->items[] = html_writer::tag('span', 'Number of posts to grade: '.$num_posts, array('style'=>'color:black'));
        }

        if($num_posts > 0 && !has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
            $this->content->items[] = html_writer::tag('span', 'Time to expire: '.$time_old_post , array('style'=>'color:black'));
        }

        return $this->content;
    }


    public function instance_config_save($data,$nolongerused = false) {
      if(get_config('peerblock', 'Allow_HTML') == '1') {
        $data->text = strip_tags($data->text);
      }

      // And now forward to the default implementation defined in the parent class
      return parent::instance_config_save($data,$nolongerused);
    }

//esconde o titulo
/*    public function hide_header() {
        return true;
    }
*/

//para alterar o estilo do bloco
    public function html_attributes() {
        $attributes = parent::html_attributes(); // Get default values
        $attributes['class'] .= ' block_'. $this->name(); // Append our class to class attribute
        return $attributes;
    }


//definir
    public function cron() {
        global $DB; // Global database object
        // Get the instances of the block
        $instances = $DB->get_records( 'block_instances', array('blockname'=>'peerblock') );
        // Iterate over the instances
        foreach ($instances as $instance) {
            // Recreate block object
            $block = block_instance('peerblock', $instance);
            // $block is now the equivalent of $this in 'normal' block
            // usage, e.g.
            $someconfigitem = $block->config->item2;
        }
 }

}   // Here's the closing bracket for the class definition
