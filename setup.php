<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
define('PLUGIN_EDITCONTROL_VERSION', '0.1.3');
require_once __DIR__ . '/inc/service.class.php';
require_once __DIR__ . '/inc/bridge.class.php';

function plugin_init_editcontrol() {
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['editcontrol'] = true;
    $PLUGIN_HOOKS['config_page']['editcontrol'] = 'front/config.php';
    $PLUGIN_HOOKS['add_javascript']['editcontrol'] = ['public/js/editcontrol.js'];
    $PLUGIN_HOOKS['post_item_form']['editcontrol'] = ['PluginEditcontrolBridge', 'form'];
    $PLUGIN_HOOKS['pre_item_form']['editcontrol'] = ['PluginEditcontrolBridge', 'capture'];
    foreach (['Ticket', 'ITILFollowup', 'TicketTask', 'ITILSolution'] as $type) {
        foreach (['pre_item_update', 'pre_item_delete', 'pre_item_purge'] as $hook) {
            $PLUGIN_HOOKS[$hook]['editcontrol'][$type] = ['PluginEditcontrolBridge', 'guard'];
        }
        if ($type !== 'Ticket') {
            $PLUGIN_HOOKS['pre_item_add']['editcontrol'][$type] = ['PluginEditcontrolBridge', 'guard'];
        }
        foreach (['item_add', 'item_update', 'item_delete', 'item_purge'] as $hook) {
            $PLUGIN_HOOKS[$hook]['editcontrol'][$type] = ['PluginEditcontrolBridge', 'saved'];
        }
    }
    $PLUGIN_HOOKS['item_add']['editcontrol']['Ticket'] = ['PluginEditcontrolBridge', 'created'];
    $PLUGIN_HOOKS['pre_item_add']['editcontrol']['Ticket'] = ['PluginEditcontrolBridge', 'beginCreation'];
}
function plugin_version_editcontrol() {
    return ['name'=>'Control de edición', 'version'=>PLUGIN_EDITCONTROL_VERSION,
        'author'=>'Juan Carlos Acosta Perabá', 'license'=>'GPLv3+',
        'requirements'=>['glpi'=>['min'=>'11.0.0', 'max'=>'12.0.99'], 'php'=>['min'=>'8.2']]];
}
function plugin_editcontrol_check_prerequisites() {
    global $CFG_GLPI;
    if (!empty($CFG_GLPI['lock_use_lock_item']) && in_array('Ticket', $CFG_GLPI['lock_item_list'] ?? [], true)) {
        echo 'Desactive ObjectLock para Ticket antes de activar Control de edición.';
        return false;
    }
    return version_compare(GLPI_VERSION, '11.0.0', '>=') && version_compare(GLPI_VERSION, '13.0.0', '<');
}
function plugin_editcontrol_check_config($verbose = false) { return plugin_editcontrol_check_prerequisites(); }
