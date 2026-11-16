<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
function plugin_editcontrol_install() {
    global $DB;
    foreach (explode(';', file_get_contents(__DIR__ . '/install/schema.sql')) as $sql) {
        if (trim($sql) !== '') { $DB->doQuery($sql); }
    }
    // Idempotent schema v1. Future versions must apply ordered migrations here.
    Config::setConfigurationValues('plugin:editcontrol', ['schema_version'=>1] +
        Config::getConfigurationValues('plugin:editcontrol') + ['ttl'=>120, 'mode'=>'warning']);
    if (empty(Config::getConfigurationValues('plugin:editcontrol')['rights_initialized'])) {
        ProfileRight::addProfileRights(['plugin_editcontrol_admin']);
        Config::setConfigurationValues('plugin:editcontrol', ['rights_initialized'=>1]);
    }
    return true;
}
function plugin_editcontrol_uninstall() {
    global $DB;
    $DB->doQuery('DROP TABLE IF EXISTS glpi_plugin_editcontrol_events');
    $DB->doQuery('DROP TABLE IF EXISTS glpi_plugin_editcontrol_leases');
    Config::deleteConfigurationValues('plugin:editcontrol', array_keys(Config::getConfigurationValues('plugin:editcontrol')));
    ProfileRight::deleteProfileRights(['plugin_editcontrol_admin']);
    return true;
}
