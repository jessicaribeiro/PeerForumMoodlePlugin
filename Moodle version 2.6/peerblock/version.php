<?php
$plugin->component = 'block_peerblock';  // Recommended since 2.0.2 (MDL-26035). Required since 3.0 (MDL-48494)
$plugin->version = 2016051007;  // YYYYMMDDHH (year, month, day, 24-hr time)
$plugin->requires = 2013110500; // YYYYMMDDHH (This is the release version for Moodle 2.0)

$plugin->dependencies = array(
    'mod_peerforum' => 2013110500,
);
