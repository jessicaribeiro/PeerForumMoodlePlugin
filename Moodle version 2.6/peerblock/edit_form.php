<?php

class block_peerblock_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        global $CFG;
        global $COURSE, $DB;

    //    $mform =& $this->_form;

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block_peerblock'));

        // titulo
        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_peerblock'));
        $mform->setDefault('config_title', 'default value');
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addRule('config_title', null, 'required', null, 'client');

        //descricao bloco
        // A sample string variable with a default value.
        $mform->addElement('text', 'config_text', get_string('blockstring', 'block_peerblock'));
        $mform->setDefault('config_text', 'default value');
        $mform->setType('config_text', PARAM_RAW);


        // Peergrade configurations
        $mform->addElement('header', 'displayinfo', get_string('peergradingconfig', 'block_peerblock'));

        // Enable or disable the peergrade
        $mform->addElement('selectyesno', 'config_enablepeergrade', get_string('enablepeergrade', 'block_peerblock'));
        $mform->setDefault('config_enablepeergrade', 1);
        $mform->addHelpButton('config_enablepeergrade', 'enablepeergrade', 'block_peerblock');

        if(isset($this->block->config->enablepeergrade)){
            $enable = $this->block->config->enablepeergrade;

            if(empty($enable)){
                $enable = 0;
            } else {
                $enable = 1;
            }

            $records = $DB->get_records('peerforum', array('course' => $COURSE->id));

            foreach ($records as $key => $value) {
                $data = new stdClass();
                $id = $records[$key]->id;
                $data->id = $id;
                $data->peergradeassessed = $enable;
                $data->allowpeergrade = $enable;

                $DB->update_record('peerforum', $data);
            }
        }
    }
}
