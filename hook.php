<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
function plugin_editcontrol_install() {
    global $DB;
    foreach (explode(';', file_get_contents(__DIR__ . '/install/schema.sql')) as $sql) {
        if (trim($sql) !== '') { $DB->doQuery($sql); }
    }
    // v2 binds requests to a tab; v3 acknowledges successful writes by that owner.
    $migration = new Migration(PLUGIN_EDITCONTROL_VERSION);
    foreach (['requester_token_hash', 'requester_session_hash', 'write_from', 'write_to'] as $field) {
        $migration->addField('glpi_plugin_editcontrol_leases', $field, "CHAR(64) NOT NULL DEFAULT ''");
    }
    $migration->executeMigration();
    foreach (['requester_token_hash', 'requester_session_hash', 'write_from', 'write_to'] as $field) {
        if (!$DB->fieldExists('glpi_plugin_editcontrol_leases', $field)) {
            throw new RuntimeException('No se pudo completar la actualización de las solicitudes de control.');
        }
    }
    Config::setConfigurationValues('plugin:editcontrol', ['schema_version'=>3] +
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
