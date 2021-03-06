<?php
#  Copyright 2003-2015 Opmantek Limited (www.opmantek.com)
#
#  ALL CODE MODIFICATIONS MUST BE SENT TO CODE@OPMANTEK.COM
#
#  This file is part of Open-AudIT.
#
#  Open-AudIT is free software: you can redistribute it and/or modify
#  it under the terms of the GNU Affero General Public License as published
#  by the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  Open-AudIT is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU Affero General Public License for more details.
#
#  You should have received a copy of the GNU Affero General Public License
#  along with Open-AudIT (most likely in a file named LICENSE).
#  If not, see <http://www.gnu.org/licenses/>
#
#  For further information on Open-AudIT or for a license other than AGPL please see
#  www.opmantek.com or email contact@opmantek.com
#
# *****************************************************************************

/**
* @category  Model
* @package   Open-AudIT
* @author    Mark Unwin <marku@opmantek.com>
* @copyright 2014 Opmantek
* @license   http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
* @version   3.2.2
* @link      http://www.open-audit.org
 */
class M_rules extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->log = new stdClass();
        $this->log->status = 'reading data';
        $this->log->type = 'system';
    }

    public function read($id = '')
    {
        $this->log->function = strtolower(__METHOD__);
        $this->log->summary = 'start';
        stdlog($this->log);
        $id = intval($id);
        if ($id === 0) {
            $CI = & get_instance();
            $id = intval($CI->response->meta->id);
        }
        $sql = "SELECT * FROM `rules` WHERE id = ?";
        $data = array($id);
        $result = $this->run_sql($sql, $data);
        if (!empty($result[0]->inputs)) {
            $result[0]->inputs = json_decode($result[0]->inputs);
        }
        if (!empty($result[0]->outputs)) {
            $result[0]->outputs = json_decode($result[0]->outputs);
        }
        $result = $this->format_data($result, 'rules');
        $this->log->summary = 'finish';
        stdlog($this->log);
        return ($result);
    }

    public function delete($id = '')
    {
        $this->log->function = strtolower(__METHOD__);
        $this->log->status = 'deleting data';
        $this->log->summary = 'start';
        stdlog($this->log);
        $id = intval($id);
        if ($id === 0) {
            $CI = & get_instance();
            $id = intval($CI->response->meta->id);
        }
        if ($id != 0) {
            $CI = & get_instance();
            $sql = "DELETE FROM `rules` WHERE id = ?";
            $data = array(intval($id));
            $this->run_sql($sql, $data);
            $this->log->summary = 'finish';
            stdlog($this->log);
            return true;
        }
        $this->log->summary = 'finish';
        stdlog($this->log);
        return false;
    }

    /*
    $parameters MUST contain either a device ID or a device object
                SHOULD contain an action, default is to update
                SHOULD contain a discovery ID for logging
    */
    public function execute($parameters = null)
    {
        $log = new stdClass();
        $log->discovery_id = @intval($parameters->discovery_id);
        $log->message = 'Running rules::match function.';
        $item_start = microtime(true);
        $log->severity = 7;
        $log->command_status = 'notice';
        $log->file = 'm_rules';
        $log->function = 'execute';

        # Device
        if (empty($parameters->id) and empty($parameters->device)) {
            return false;
        }
        $device_sub = array();
        if (!empty($parameters->device)) {
            $device = $parameters->device;
            $device->where = 'supplied';
            $log->command_output = json_encode($device);
            $log->command = 'Device Input ';
        }
        if (!empty($parameters->id)) {
            # Get our device
            $id = intval($parameters->id);
            $log->command_output = "Device ID supplied: " . $parameters->id;
            $log->command = 'Device ID Input ';
            $sql = "SELECT * FROM `system` WHERE id = ?";
            $data = array($id);
            $result = $this->run_sql($sql, $data);
            if (!empty($result[0])) {
                $device = $result[0];
                $device->where = 'database';
                # NOTE - Some of these are in the database and default to 0. Empty these.
                if ($device->snmp_enterprise_id == 0) {
                    $device->snmp_enterprise_id = '';
                }
                if ($device->os_bit == 0) {
                    $device->os_bit = '';
                }
                if ($device->memory_count == 0) {
                    $device->memory_count = '';
                }
                if ($device->processor_count == 0) {
                    $device->processor_count = '';
                }
                if ($device->storage_count == 0) {
                    $device->storage_count = '';
                }
                if ($device->switch_port == 0) {
                    $device->switch_port = '';
                }
            } else {
                $log->severity = 4;
                $log->command_status = 'fail';
                $log->message = 'Could not retrieve data from system table for ID: ' . $parameters->id . '. Not running Rules function.';
                discovery_log($log);
                return false;
            }
        }

        # Discovery ID for logging
        if (empty($parameters->discovery_id)) {
            if (!empty($device->discovery_id)) {
                $discovery_id = $device->discovery_id;
            } else {
                $discovery_id = false;
            }
        } else {
            $discovery_id = $parameters->discovery_id;
        }
        # Action - default of update
        $action = 'update';
        if (!empty($parameters->action) and $parameters->action == 'return') {
            $action = 'return';
            $log->command .= '(return).';
        } else {
            $log->command .= '(update).';
        }

        $log->ip = '';
        if (!empty($device->ip)) {
            $log->ip = ip_address_from_db($device->ip);
        }
        $log->system_id = '';
        if (!empty($device->id)) {
            $log->system_id = $device->id;
        }

        discovery_log($log);

        # NOTE - don't set the id or last_seen_by here as we test if empty after rules
        #        have been run and only update if not empty (after adding id and last_seen_by).
        $newdevice = new stdClass();

        # Details based on SNMP OID
        if (!empty($device->snmp_oid)) {
            $log_start = microtime(true);
            $newdevice = get_details_from_oid($device->snmp_oid);
            if (!empty($newdevice)) {
                $log->message = "Hit on \$device->snmp_oid " . $device->snmp_oid . " eq " . $device->snmp_oid;
                $log->command = 'Rules Match - SNMP OID for  ' . $newdevice->model;
                $log->command_output = json_encode($newdevice);
                $log->command_time_to_execute = (microtime(true) - $log_start);
                discovery_log($log);
                foreach ($newdevice as $key => $value) {
                    $device->{$key} = $value;
                }
            }
        }

        # Manufacturer based on MAC Address
        if (!empty($device->mac_address) and empty($device->manufacturer)) {
            $log_start = microtime(true);
            $newdevice->manufacturer = get_manufacturer_from_mac($device->mac_address);
            if (!empty($newdevice->manufacturer)) {
                $log->message = "Hit on \$device->mac_address " . $device->mac_address . " st " . substr(strtolower($device->mac_address ), 0, 8);
                $log->command = 'Rules Match - Mac Address for ' . $newdevice->manufacturer;
                $log->command_output = json_encode($newdevice);
                $log->command_time_to_execute = (microtime(true) - $log_start);
                discovery_log($log);
                $device->manufacturer = $newdevice->manufacturer;
            }
        }

        # Manufacturer based on SNMP Enterprise ID
        if (!empty($device->snmp_enterprise_id) and empty($newdevice->manufacturer)) {
            $log_start = microtime(true);
            $newdevice->manufacturer = get_manufacturer_from_oid($device->snmp_enterprise_id);
            if (!empty($newdevice->manufacturer)) {
                $log->message = "Hit on \$device->snmp_enterprise_id " . $device->snmp_enterprise_id . " eq " . $device->snmp_enterprise_id;
                $log->command = 'Rules Match - SNMP Enterprise Number for  ' . $newdevice->manufacturer;
                $log->command_output = json_encode($newdevice);
                $log->command_time_to_execute = (microtime(true) - $log_start);
                discovery_log($log);
                $device->manufacturer = $newdevice->manufacturer;
            }
        }

        # Mac Description based on Manufacturer Code (derived from Serial)
        if (!empty($device->manufacturer_code)) {
            $log_start = microtime(true);
            $newdevice->description = get_description_from_manufacturer_code($device->manufacturer_code);
            if (!empty($newdevice->description)) {
                $log->message .= " Hit on \$device->manufacturer_code " . $device->manufacturer_code . " eq " . $device->manufacturer_code;
                $log->command = 'Rules Match - Mac Model into description';
                $log->command_output = json_encode($newdevice->description);
                $log->command_time_to_execute = (microtime(true) - $log_start);
                discovery_log($log);
                $device->description = $newdevice->description;
            }
        }

        // $rule_iterator = 100;
        // $sql = "SELECT COUNT(id) AS `count` FROM rules";
        // $query = $this->db->query($sql);
        // $result = $query->result();
        // $rules_count = intval($result[0]->count + $rule_iterator);
        // for ($i=0; $i < ($rule_iterator); $i++) {
        //     $offset = intval(($rules_count / $rule_iterator) * $i);
        //     $limit = intval(($rules_count / $rule_iterator));
        //     $sql = "SELECT * FROM rules ORDER BY weight ASC, id LIMIT $limit OFFSET $offset";
        //     $rules = $this->run_sql($sql);

            # TODO - Orgs
            $sql = "SELECT * FROM `rules` ORDER BY weight ASC, id";
            $rules = $this->run_sql($sql);

            $other_tables = array();
            foreach ($rules as $rule) {
                $rule->inputs = json_decode($rule->inputs);
                $rule->outputs = json_decode($rule->outputs);
                foreach ($rule->inputs as $input) {
                    if (!$this->db->table_exists($input->table)) {
                        $l = new stdClass();
                        $l->command_status = 'error';
                        $l->discovery_id = $log->discovery_id;
                        $l->ip = $log->ip;
                        $l->message = 'Rule ' . $rule->id . ' specified a table that does not exist: ' . $input->table . '.';
                        $l->command = json_encode($rule);
                        $l->command_output = '';
                        discovery_log($l);
                        continue;
                    }
                    if ($input->table !== 'system' and !in_array($input->table, $other_tables)) {
                        $other_tables[] = $input->table;
                    }
                }
            }

            foreach ($other_tables as $table) {
                $sql = "SELECT * FROM `" . $table . "` WHERE system_id = ? AND current = 'y'";
                $data = array($id);
                $result = $this->run_sql($sql, $data);
                $device_sub[$table] = $result;
            }
            unset($other_tables);

            // $l = new stdClass();
            // $l->command_status = 'notice';
            // $l->discovery_id = $log->discovery_id;
            // $l->ip = $log->ip;
            // $l->message = 'Memory Use - ' . round((memory_get_peak_usage(false)/1024/1024), 3) . " MiB";
            // $l->command = '';
            // $l->command_output = '';
            // discovery_log($l);

            # Special case the MAC as we might have it in the device entry, but no network table yet
            if (!empty($device->mac_address) and empty($device_sub['network'])) {
                $item = new stdClass();
                $item->mac = $device->mac_address;
                $device_sub['network'] = array($item);
            }

            foreach ($rules as $rule) {
                if (is_array($rule->inputs)) {
                    $input_count = count($rule->inputs);
                } else {
                    # Log an error, but continue
                    $l = new stdClass();
                    $l->command_status = 'error';
                    $l->discovery_id = $log->discovery_id;
                    $l->ip = $log->ip;
                    $l->message = 'Rule ' . $rule->id . ' inputs is not an array.';
                    $l->command = $rule->inputs;
                    $l->command_output = '';
                    discovery_log($l);
                    continue;
                }
                $hit = 0;
                foreach ($rule->inputs as $input) {
                    if ($input->table == 'system') {
                        switch ($input->operator) {
                            case 'eq':
                                if ((string)$device->{$input->attribute} === (string)$input->value) {
                                    if ((string)$input->value !== '') {
                                        $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " eq " . $input->value;
                                    } else {
                                        $log->message .= " Hit on $input->attribute is empty";
                                    }
                                    $hit++;
                                }
                            break;

                            case 'ne':
                                if ((string)$device->{$input->attribute} !== (string)$input->value) {
                                    if ((string)$input->value !== '') {
                                        $log->message .= " Hit on $input->attribute " .$device->{$input->attribute} . " ne " . $input->value;
                                    } else {
                                        $log->message .= " Hit on $input->attribute is not empty";
                                    }
                                    $hit++;
                                }
                            break;

                            case 'gt':
                                if ((string)$device->{$input->attribute} > (string)$input->value) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " gt " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'ge':
                                if ((string)$device->{$input->attribute} >= (string)$input->value) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " ge " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'lt':
                                if ((string)$device->{$input->attribute} < (string)$input->value) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " lt " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'le':
                                if ((string)$device->{$input->attribute} <= (string)$input->value) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " le" . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'li':
                                if (stripos((string)$device->{$input->attribute}, $input->value) !== false) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " li " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'nl':
                                if (stripos((string)$device->{$input->attribute}, $input->value) === false) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " nl " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'in':
                                $values = explode(',', $input->value);
                                if (in_array((string)$device->{$input->attribute}, $values)) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " in " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'ni':
                                $values = explode(',', $input->value);
                                if (!in_array((string)$device->{$input->attribute}, $values)) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " ni " . $input->value;
                                    $hit++;
                                }
                            break;

                            case 'st':
                                if (stripos((string)$device->{$input->attribute},$input->value) === 0) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " st " . $input->value;
                                    $hit++;
                                }
                            break;
                            
                            default:
                                if ((string)$device->{$input->attribute} === (string)$input->value) {
                                    $log->message .= " Hit on $input->attribute " . $device->{$input->attribute} . " default " . $input->value;
                                    $hit++;
                                }
                            break;
                        }
                    } else {
                        if (!empty($input->table) and !empty($device_sub[$input->table])) {
                            switch ($input->operator) {
                                case 'eq':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} === (string)$input->value) {
                                            if ($input->value != '') {
                                                $log->message .= " Hit on $dsub $input->attribute " . $dsub->{$input->attribute} . " eq " . $input->value . " for " . $rule->name . ".";
                                            } else {
                                                $log->message .= " Hit on $dsub $input->attribute is empty";
                                            }
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'ne':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} !== (string)$input->value) {
                                            if ($input->value != '') {
                                                $log->message .= " Hit on $dsub $input->attribute " . $dsub->{$input->attribute} . " ne " . $input->value;
                                            } else {
                                                $log->message .= " Hit on $dsub $input->attribute is empty";
                                            }
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'gt':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} > (string)$input->value) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " gt " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'ge':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} >= (string)$input->value) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " ge " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'lt':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} < (string)$input->value) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " lt " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'le':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} <= (string)$input->value) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " le" . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'li':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if (stripos((string)$dsub->{$input->attribute}, $input->value) !== false) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " li " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'nl':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if (stripos((string)$dsub->{$input->attribute}, $input->value) === false) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " nl " . $input->value ;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'in':
                                    $values = explode(',', $input->value);
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if (in_array((string)$dsub->{$input->attribute}, $values)) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " in " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'ni':
                                    $values = explode(',', $input->value);
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if (!in_array((string)$dsub->{$input->attribute}, $values)) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " ni " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;

                                case 'st':
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if (stripos((string)$dsub->{$input->attribute},$input->value) === 0) {
                                            $log->message .= " Hit on " . $dsub->{$input->attribute} . " st " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;
                                
                                default:
                                    foreach ($device_sub[$input->table] as $dsub) {
                                        if ((string)$dsub->{$input->attribute} == (string)$input->value) {
                                            $log->message .= " Hit on " . $device->{$input->attribute} . " default " . $input->value;
                                            $hit++;
                                            break;
                                        }
                                    }
                                break;
                            }
                        }
                    }
                    if ($hit >= $input_count) {
                        $attributes = new stdClass();
                        foreach ($rule->outputs as $output) {
                            switch ($output->value_type) {
                                case 'string':
                                    $newdevice->{$output->attribute} = (string)$output->value;
                                break;
                                
                                case 'integer':
                                    $newdevice->{$output->attribute} = intval($output->value);
                                break;
                                
                                case 'timestamp':
                                    if ($output->value == '') {
                                        $newdevice->{$output->attribute} = $this->config->config['timestamp'];
                                    } else {
                                        $newdevice->{$output->attribute} = intval($output->value);
                                    }
                                break;
                                
                                default:
                                    $newdevice->{$output->attribute} = (string)$output->value;
                                break;
                            }
                            $attributes->{$output->attribute} = $newdevice->{$output->attribute};
                            $device->{$output->attribute} = $newdevice->{$output->attribute};
                        }
                        $log->message = trim($log->message);
                        $log->command = 'Rules Match - ' . $rule->name . ', ID: ' . $rule->id;
                        $log->command_output = json_encode($attributes);
                        $log->command_time_to_execute = (microtime(true) - $item_start);
                        discovery_log($log);
                    }
                }
                $log->message = '';
            }
            unset($rules);
        #}

        $log->message = 'Completed rules::match function.';
        $log->command = '';
        $log->command_output = '';
        $log->command_status = 'notice';
        discovery_log($log);

        if (count(get_object_vars($newdevice)) > 0) {
            $newdevice->id = $device->id;
            if ($action == 'update') {
                $newdevice->last_seen_by = 'rules';
                $this->load->model('m_devices');
                $this->m_devices->update($newdevice);
                return;
            } else {
                return $device;
            }
        } else {
            if ($action == 'update') {
                return;
            } else {
                return $device;
            }
        }
    }
}
