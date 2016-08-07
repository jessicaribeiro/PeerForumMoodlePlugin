<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Form for editing peerblock block settings
 *
 * @package    block
 * @subpackage peerblock
 * @copyright  2016 Jessica Ribeiro
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_peerblock_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        global $CFG;
        global $COURSE, $DB;

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block_peerblock'));

        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_peerblock'));
        $mform->setDefault('config_title', 'default value');
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addRule('config_title', null, 'required', null, 'client');

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

        // Enable or disable the outliers
        $mform->addElement('selectyesno', 'config_seeoutliers', get_string('seeoutliers', 'block_peerblock'));
        $mform->setDefault('config_seeoutliers', 1);
        $mform->addHelpButton('config_seeoutliers', 'seeoutliers', 'block_peerblock');
        $mform->disabledIf('config_seeoutliers', 'config_enablepeergrade', 'eq', 0);

        // Outliers detection
        $detection = array(
            'grade points' => get_string('gradepoints', 'peerforum'),
            'standard deviation' => get_string('standarddeviation', 'peerforum'),
        );

         $mform->addElement('select', 'config_outlierdetection', get_string('outlierdetection', 'block_peerblock') , $detection);
         $mform->setDefault('config_outlierdetection', 'standard deviation');
         $mform->addHelpButton('config_outlierdetection', 'outlierdetection', 'block_peerblock');
         $mform->disabledIf('config_outlierdetection', 'config_seeoutliers', 'eq', 0);
         $mform->disabledIf('config_outlierdetection', 'config_enablepeergrade', 'eq', 0);



         // Outliers method value
         $mform->addElement('text', 'config_outdetectvalue', get_string('outdetectvalue', 'block_peerblock'));
         $mform->setType('config_outdetectvalue', PARAM_INT);
         $mform->setDefault('config_outdetectvalue', 1);
         $mform->addRule('config_outdetectvalue', null, 'numeric', null, 'client');
         $mform->addHelpButton('config_outdetectvalue', 'outdetectvalue', 'block_peerblock');
         $mform->disabledIf('config_outdetectvalue', 'config_seeoutliers', 'eq', 0);
         $mform->disabledIf('config_outdetectvalue', 'config_enablepeergrade', 'eq', 0);

         // Block red outliers
         $mform->addElement('selectyesno', 'config_blockoutliers', get_string('blockoutliers', 'block_peerblock'));
         $mform->setDefault('config_blockoutliers', 0);
         $mform->addHelpButton('config_blockoutliers', 'blockoutliers', 'block_peerblock');
         $mform->disabledIf('config_outdetectvalue', 'config_seeoutliers', 'eq', 0);
         $mform->disabledIf('config_blockoutliers', 'config_enablepeergrade', 'eq', 0);

         // Threshold for warning outliers
         $mform->addElement('text', 'config_warningoutliers', get_string('warningoutliers', 'block_peerblock'));
         $mform->setType('config_warningoutliers', PARAM_INT);
         $mform->setDefault('config_warningoutliers', 0);
         $mform->addRule('config_warningoutliers', null, 'numeric', null, 'client');
         $mform->addHelpButton('config_warningoutliers', 'warningoutliers', 'block_peerblock');
         $mform->disabledIf('config_outdetectvalue', 'config_seeoutliers', 'eq', 0);
         $mform->disabledIf('config_warningoutliers', 'config_enablepeergrade', 'eq', 0);
    }

    function validation($data, $files) {
        global $DB, $COURSE, $CFG;

        $config_enablepeergrade = $data['config_enablepeergrade'];
        $config_seeoutliers = $data['config_seeoutliers'];
        $config_outlierdetection = $data['config_outlierdetection'];
        $config_outdetectvalue = $data['config_outdetectvalue'];
        $config_blockoutliers = $data['config_blockoutliers'];
        $config_warningoutliers = $data['config_warningoutliers'];

        $data = new stdClass();

        if(isset($config_enablepeergrade)){
            $enable = $config_enablepeergrade;

            if(empty($enable)){
                $enable = 0;
            }
            $data->peergradeassessed = $enable;
            $data->allowpeergrade = $enable;
        }

        if(isset($config_seeoutliers)){
            $see = $config_seeoutliers;

            if(empty($see)){
                $see = 0;
            }
            $data->seeoutliers = $see;
        }

        if(isset($config_outlierdetection)){
            $detection = $config_outlierdetection;

            if(empty($detection)){
                $detection = 'standard deviation';
            }
            $data->outlierdetection = $detection;
        }

        if(isset($config_outdetectvalue)){
            $detectionvalue = $config_outdetectvalue;

            if(empty($detectionvalue)){
                $detectionvalue = 0;
            }
            $data->outdetectvalue = $detectionvalue;
        }

        if(isset($config_blockoutliers)){
            $blockoutliers = $config_blockoutliers;

            if(empty($blockoutliers)){
                $blockoutliers = 0;
            }
            $data->blockoutliers = $blockoutliers;
        }

        if(isset($config_warningoutliers)){
            $warningoutliers = $config_warningoutliers;

            if(empty($warningoutliers)){
                $warningoutliers = 0;
            }
            $data->warningoutliers = $warningoutliers;
        }

        // Update database
        $records = $DB->get_records('peerforum', array('course' => $COURSE->id));

        foreach ($records as $key => $value) {
            $id = $records[$key]->id;
            $data->id = $id;

            $DB->update_record('peerforum', $data);
        }
    }
}
