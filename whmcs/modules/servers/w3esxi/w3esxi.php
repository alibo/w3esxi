<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
define('W3E_IMGDIR', 'modules/servers/w3esxi/images/');
define('W3E_VERSION', '0.7.5');
require_once 'vmware_class.php';
if (!class_exists('LibSSH2')) {
    die("Your module is invalid#0");
}
if (!class_exists('VMware')) {
    die("Your module is invalid#1");
}
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'w3esxi_admin' . DIRECTORY_SEPARATOR . 'render_class.php';
if (!class_exists('W3ERender')) {
    die("Your module is invalid#2");
}
/**
 * w3e_checkEnable()
 *
 * @return
 */
function w3e_checkEnable()
{
    if (!mysql_num_rows(mysql_query("SHOW TABLES LIKE 'mod_w3esxi'"))) {
        die("<h1 style=\"color:red;\">W3Esxi's tables are NOT exist in DB . Please Active W3Esxi Admin Module</h1>");
    }
    if (!mysql_num_rows(mysql_query("SHOW TABLES LIKE 'mod_w3esxi_config'"))) {
        die("<h1 style=\"color:red;\">W3Esxi's tables are  NOT exist in DB . Please Active W3Esxi Admin Module</h1>");
    }
    if (!mysql_num_rows(mysql_query("SHOW TABLES LIKE 'mod_w3esxi_logs'"))) {
        die("<h1 style=\"color:red;\">New W3Esxi's table is NOT exist in DB . Please Update Module to " . W3E_VERSION . "</h1>");
    }
    return true;
}
w3e_checkEnable();
/**
 * w3e_checkDebuggingMode()
 *
 * @return
 */
function w3e_checkDebuggingMode()
{
    if (w3e_getSettings('debug_mode')) {
        error_reporting(E_ALL);
    } else {
        error_reporting(0);
    }
}
w3e_checkDebuggingMode();

/**
 * w3e_getVMID()
 *
 * @param mixed $params
 * @param bool $return
 * @return
 */
function w3e_getVMID($params, $return = false)
{
    $dbresult = select_query("mod_w3esxi", "vmid", array("serviceid" => $params['serviceid']));

    if (!$dbresult || mysql_num_rows($dbresult) != 1) {
        if ($return) {
            return null;
        }

    } else {
        $data = mysql_fetch_array($dbresult);
        $vmid = $data['vmid'];
        return $vmid;
    }

}

/**
 * w3e_getSettings()
 *
 * @param string $setting
 * @return
 */
function w3e_getSettings($setting = '*')
{
    $query = 'SELECT name,value FROM mod_w3esxi_config';
    if ($setting != '*') {
        $query = "SELECT value FROM mod_w3esxi_config WHERE name = '$setting'";
    }
    $result = mysql_query($query);

    if ($result && mysql_num_rows($result) > 0) {
        if ($setting == '*') {
            $returnArr = array();
            while ($row = mysql_fetch_assoc($result)) {
                $returnArr[$row['name']] = $row['value'];
            }
            return $returnArr;
        } else {
            $row = mysql_fetch_assoc($result);
            return $row['value'];
        }
    } else {
        $this->setErrors('Cannot fetch Settings from Database', 'ERROR');
        return null;
    }

}

/**
 * w3e_insertLog()
 *
 * @param mixed $action
 * @param mixed $serviceid
 * @param mixed $vmid
 * @param mixed $commandBy
 * @return
 */
function w3e_insertLog($action, $serviceid, $vmid, $commandBy)
{
    $queryGetW3eId = "SELECT id FROM mod_w3esxi WHERE vmid = '$vmid' AND serviceid = '$serviceid'";
    $result = @mysql_query($queryGetW3eId);
    $w3e_id = 0;
    if ($result && mysql_num_rows($result) == 1) {
        $row = mysql_fetch_assoc($result);
        $w3e_id = $row['id'];
    }

    $userAgent = mysql_real_escape_string(trim($_SERVER["HTTP_USER_AGENT"]));
    $ip = mysql_real_escape_string(trim($_SERVER["REMOTE_ADDR"]));
    $loggedDate = strftime('%Y-%m-%d %H:%M:%S');
    $expireDate = $loggedDate;
    if ($action != 'unsuspend' && $action != 'suspend') {
        $lockTime = w3e_getSettings($action . '_lock_time');
        $lockTime = str_replace(array('h', 's', 'm'), array(' hour', ' sec', ' min'), $lockTime);
        $expireDate = strftime('%Y-%m-%d %H:%M:%S', strtotime('+' . $lockTime));
    }

    $insertArr = array(
        'w3e_id' => $w3e_id,
        'serviceid' => $serviceid,
        'date_logged' => $loggedDate,
        'locking_date_expired' => $expireDate,
        'client_ip' => $ip,
        'user_agent' => $userAgent,
        'command_by' => $commandBy,
        'action' => $action,
    );

    insert_query('mod_w3esxi_logs', $insertArr);

}

/**
 * w3e_createInfo()
 *
 * @param mixed $params
 * @param mixed $vmid
 * @param mixed $template
 * @param mixed $foruser
 * @return
 */
function w3e_createInfo($params, $vmid, $template, $foruser)
{
    $user = $params['serverusername'];
    $pass = $params['serverpassword'];
    $host = $params['serverip'];
    $vmware = new VMware;
    $vmware->setServerConfig($host, $user, $pass);

    //SHOW ONLY POWER STATE
    $stateSettings = false;

    if ($foruser == 'client') {
        $stateSettings = w3e_getSettings('only_show_power_state_to_client');
    } else {
        $stateSettings = w3e_getSettings('only_show_power_state_to_admin');
    }

    if ($stateSettings) {
        $info = $vmware->getState($vmid);
        $info = str_replace(' ', '', $info);
        $state = 'white';
        $stateText = 'UnKnown';
        if ($info == 'poweredon') {
            $state = 'green';
            $stateText = 'Power On';
        } elseif ($info == 'poweredoff') {
            $state = 'red';
            $stateText = 'Power Off';
        } elseif ($info == 'suspended') {
            $state = 'blue';
            $stateText = 'Suspended';
        }

        $template = preg_replace('@({W3EELSE_STATUS}.*?{/W3EELSE_STATUS})@is', '', $template);

        $search = array(
            '{W3E_IMG_PATH}', //1
            '{W3E_POWER_STATE_TEXT}', //7
            '{W3E_POWER_STATE_COLOR}', //8
            '{W3EIF_STATUS}',
            '{/W3EIF_STATUS}',
            '{W3E_NOW}',
        );
        $replace = array(
            W3E_IMGDIR,
            $stateText,
            $state,
            '',
            '',
            strftime('%Y-%m-%d %H:%M:%S'),
        );

        return str_replace($search, $replace, $template);

    }

    //SHOW FULL INFO
    $hard_details = w3e_getSettings('show_hard_details');
    $info = $vmware->getImportantInfo($vmid, $hard_details);

    $state = 'white';
    $stateText = 'UnKnown';
    if ($info['powerState'] == 'poweredon') {
        $state = 'green';
        $stateText = 'Powered On';
    } elseif ($info['powerState'] == 'poweredoff') {
        $state = 'red';
        $stateText = 'Powered Off';
    } elseif ($info['powerState'] == 'suspended') {
        $state = 'blue';
        $stateText = 'Suspended';
    }
    $os = $params['configoption1'];
    if ($os == 'Other') {
        $os = $params['configoption2'];
    }
    $upt = $info['uptime'];
    $h = round($upt / (3600), 0);
    $upt %= 3600;
    $m = round(($upt) / 60, 0);
    $upt %= 60;
    $s = $upt;
    $uptime = "{$h}H {$m}Min {$s}Sec";

    $ramUsagePercent = 0;
    if ($info['memoryUsage'] != 0) {
        $ramUsagePercent = round(($info['memoryUsage'] * 100) / $info['memorySize'], 2);
    }
    $hardUsagePercent = 0;
    if ($info['hd']) {
        $hardUsagePercent = round(($info['hardUsage'] * 100) / $info['hd'], 2);
    }
    $cpuUsagePercent = 0;
    if ($info['cpuMax']) {
        $cpuUsagePercent = round(($info['cpuUsage'] * 100) / $info['cpuMax'], 2);
    }

    if ($hard_details) {
        $template = preg_replace('@({W3EELSE_HARD_DETAILS}.*?{/W3EELSE_HARD_DETAILS})@is', '', $template);
    } else {
        $template = preg_replace('@({W3EIF_HARD_DETAILS}.*?{/W3EIF_HARD_DETAILS})@is', '', $template);
    }

    $search = array(
        '{W3E_IMG_PATH}', //1
        '{W3E_POWER_STATE_TEXT}', //7
        '{W3E_POWER_STATE_COLOR}', //8
        '{W3E_IP}', //9
        '{W3E_HOST}', //10
        '{W3E_OS}', //11
        '{W3E_ONVMWARE_OS}', //11
        '{W3E_ONVMWARE_FULLNAME_OS}', //11
        '{W3E_BOOT_TIME}', //12
        '{W3E_UPTIME}', //13
        '{W3E_CPU}', //14
        '{W3E_CPU_USAGE}', //15
        '{W3E_CPU_USAGE_PERCENT}', //16
        '{W3E_RAM}', //17
        '{W3E_RAM_USAGE}', //18
        '{W3E_RAM_USAGE_PERCENT}', //19
        '{W3E_SPACE}', //20
        '{W3E_FREE_SPACE}', //21
        '{W3E_SPACE_USAGE}', //22
        '{W3E_SPACE_USAGE_PERCENT}', //23
        '{W3E_VMID}', //24
        '{W3E_HARD_NUMS}',

        '{W3EIF_HARD_DETAILS}',
        '{/W3EIF_HARD_DETAILS}',
        '{W3EELSE_HARD_DETAILS}',
        '{/W3EELSE_HARD_DETAILS}',
        '{W3EIF_STATUS}',
        '{/W3EIF_STATUS}',
        '{W3EELSE_STATUS}',
        '{/W3EELSE_STATUS}',
        '{W3E_NOW}',

    );

    $replace = array(
        W3E_IMGDIR,
        $stateText,
        $state,
        $info['ip'],
        $info['hostname'],
        //OS
        $os,
        $info['os'],
        $info['osFullName'],

        $info['bootTime'],
        $uptime,
        $info['cpuMax'],
        $info['cpuUsage'],
        $cpuUsagePercent,
        $info['memorySize'],
        $info['memoryUsage'],
        $ramUsagePercent,
        $info['hd'],
        $info['hardFree'],
        $info['hardUsage'],
        $hardUsagePercent,
        $vmid,
        $info['hardNums'],
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        strftime('%Y-%m-%d %H:%M:%S'),

    );

    return str_replace($search, $replace, $template);
}

/**
 * w3e_getLastCommandExpireDate()
 *
 * @param mixed $serviceid
 * @return
 */
function w3e_getLastCommandExpireDate($serviceid)
{
    $query = "
    SELECT locking_date_expired
    FROM mod_w3esxi_logs
    WHERE serviceid = '$serviceid'
    ORDER BY locking_date_expired DESC
    LIMIT 1";
    $result = @mysql_query($query);
    if ($result) {
        if (mysql_num_rows($result) == 1) {
            $row = mysql_fetch_assoc($result);
            return strtotime($row['locking_date_expired']);
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * w3e_getNewVmidList()
 *
 * @param mixed $params
 * @param bool $vmid
 * @return
 */
function w3e_getNewVmidList($params, $vmid = false)
{

    $user = $params['serverusername'];
    $pass = $params['serverpassword'];
    $host = $params['serverip'];
    if (!defined('W3E_SET_ERROR')) {
        define('W3E_SET_ERROR', 1);
    }
    $vmware = new VMware;
    $vmware->setServerConfig($host, $user, $pass);
    $list = $vmware->getAllVmInfo();

    if (!$list) {
        return 'FAIL';
    }

    $allvm = array();
    foreach ($list as $vm) {
        $allvm[$vm['vmid']] = $vm['name'] . '  (' . $vm['os'] . ')';
    }

    if ($vmid && array_key_exists($vmid, $allvm)) {
        $vmidName = $allvm[$vmid];
    } else {
        $vmidName = false;
    }

    $dbresult = select_query("mod_w3esxi", "vmid", array("serverid" => $params['serverid']));

    $vmInDB = array();

    while ($fetch = mysql_fetch_array($dbresult)) {
        $vmInDB[$fetch['vmid']] = 1;
    }

    $response = array();
    $response['vmidname'] = $vmidName;
    $response['vmlist'] = array_diff_key($allvm, $vmInDB);
    asort($response['vmlist']);
    return $response;

}

/**
 * w3esxi_ConfigOptions()
 *
 * @return
 */
function w3esxi_ConfigOptions()
{
    # Prodcut Extra Fields
    $os = array(
        'CentOS 6.0 x64',
        'CentOS 5.5 x64',
        'CentOS 5.5 x86',
        'Ubuntu 11.04 x64',
        'Ubuntu 11.04 x86',
        'Ubuntu 11.10 x64',
        'Ubuntu 11.10 x86',
        'Ubuntu 12.04 x64',
        'Ubuntu 12.04 x86',
        'Debian 6.0 x64',
        'Debian 6.0 x86',
        'Arch 2010.05 x86-64',
        'Windows Server 2003',
        'Windows Server 2008 R2',
        'MikroTik 5.0',
        'MikroTik 3.3',
        'MikroTik 2.9',
    );
    $configarray = array(
        "Operating System" => array("Type" => "dropdown", "Options" => implode(',', $os) . ",Other"),
        "Other OS" => array("Type" => "text", "Size" => "35", "Description" => "If OS menu is selected to `Other`"),
    );

    return $configarray;

}

/**
 * w3esxi_UsageUpdate()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_UsageUpdate($params)
{
    static $w3e_called = 1;

    if ($w3e_called == 1) {

        //VMID
        if (w3e_getSettings('auto_remove_vmid_of_deleted_services')) {
            $alsoCancelled = w3e_getSettings('auto_remove_also_cancelled_services');
            $query = "DELETE FROM mod_w3esxi WHERE serviceid NOT IN (SELECT id FROM tblhosting)";
            if ($alsoCancelled) {
                $query .= " OR serviceid IN (SELECT id FROM tblhosting WHERE domainstatus = 'Cancelled')";
            }
            @mysql_query($query);

            //LOGS
            $query = "DELETE FROM mod_w3esxi_logs WHERE serviceid NOT IN (SELECT id FROM tblhosting)";
            if ($alsoCancelled) {
                $query .= " OR serviceid IN (SELECT id FROM tblhosting WHERE domainstatus = 'Cancelled')";
            }
            @mysql_query($query);

            //LOGS WITHOUT VMID
            $query = "DELETE FROM mod_w3esxi_logs WHERE w3e_id NOT IN (SELECT id FROM mod_w3esxi)";
            $result = @mysql_query($query);
        }

    }

    $w3e_called++;
}

/**
 * w3esxi_UnsuspendAccount()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_UnsuspendAccount($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $dbresult = select_query("mod_w3esxi", "vmid", array("serviceid" => $params['serviceid']));

        if (mysql_num_rows($dbresult) != 1) {
            return "Cannot Suspend VPS. Problem in select VMID from DB";
        }

        $data = mysql_fetch_array($dbresult);
        $vmid = $data['vmid'];
        if ($vmid != null) {
            $vmware = new VMware;
            $vmware->setServerConfig($host, $user, $pass);

            $response = $vmware->unSuspend($vmid);
        } else {
            $response = false;
        }

        if ($response) {
            update_query("tblhosting", array(
                "domainstatus" => "Active",
            ), array("id" => $params['serviceid']));
            //insert logs
            w3e_insertLog('unsuspend', $params['serviceid'], $vmid, 'admin');

            $result = 'success';
        } else {
            $result = "Cannot UnSuspend VPS - VMID: $vmid";
            if (isset($_SESSION['W3E_MESSAGES'])) {
                $_SESSION['W3E_MESSAGES'] = array();
                unset($_SESSION['W3E_MESSAGES']);
            }
        }
    }

    return $result;
}

/**
 * w3esxi_SuspendAccount()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_SuspendAccount($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $dbresult = select_query("mod_w3esxi", "vmid", array("serviceid" => $params['serviceid']));

        if (mysql_num_rows($dbresult) != 1) {
            return "Cannot Suspend VPS. Problem in select VMID from DB";
        }

        $data = mysql_fetch_array($dbresult);
        $vmid = $data['vmid'];
        if ($vmid != null) {
            $vmware = new VMware;
            $vmware->setServerConfig($host, $user, $pass);

            $response = $vmware->suspend($vmid);
        } else {

            $response = false;
        }

        if ($response) {
            update_query("tblhosting", array(
                "domainstatus" => "Suspended",
            ), array("id" => $params['serviceid']));
            //insert logs
            w3e_insertLog('suspend', $params['serviceid'], $vmid, 'admin');

            $result = 'success';
        } else {
            $result = "Cannot Suspend VPS - VMID: $vmid";
            if (isset($_SESSION['W3E_MESSAGES'])) {
                $_SESSION['W3E_MESSAGES'] = array();
                unset($_SESSION['W3E_MESSAGES']);
            }
        }
    }

    return $result;

}

/**
 * w3esxi_ClientArea()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_ClientArea($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>W3ESXi: Server is disabled.<strong>";
    }
    $html = @file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'clientarea_power.tpl');
    if (!$html) {
        return "<strong>W3ESXi: Cannot LOAD W3ESXI ClientArea TPL file<strong>";
    }
    $vmid = w3e_getVMID($params);
    if (!$vmid) {
        return "<strong>W3ESXi: Your control panel is not enabled.<strong>";
    }
    $search = array(
        '{W3E_IMG_PATH}', //1
        '{W3E_POWER_ON_URL}', //2
        '{W3E_POWER_OFF_URL}', //3
        '{W3E_RESET_URL}', //4
        '{W3E_SHUTDOWN_URL}', //5
        '{W3E_REBOOT_URL}', //6

    );

    $replace = array(
        W3E_IMGDIR,
        'clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a=poweron',
        'clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a=poweroff',
        'clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a=reset',
        'clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a=shutdown',
        'clientarea.php?action=productdetails&id=' . $params['serviceid'] . '&modop=custom&a=reboot',
    );

    $html = str_replace($search, $replace, $html);
    $vminfo = w3e_getSettings('show_info_client');

    if ($vminfo && !(isset($_GET['showvminfo']) && $_GET['showvminfo'] == 0)) {
        $infotpl = @file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'clientarea_info.tpl');

        $html .= w3e_createInfo($params, $vmid, $infotpl, 'client');
    }

    //Logs
    $html .= '<h3 style="text-align:left;">Logs</h3>
<p></p>
<table class="data" width="100%" border="0" align="center" cellpadding="10" cellspacing="0" style="text-align:center;">
  <tbody>
	  <tr>
		<th style="width: 10%">#</th>
		<th style="width: 30%">Action</th>
		<th style="width: 40%">Date</th>
		<th style="width: 20%">By</th>
	  </tr>
';

    $limit = w3e_getSettings('client_logs_items');
    $showAdminCommand = w3e_getSettings('show_admin_commands_client_logs');
    $serviceid = $params['serviceid'];
    $query = "SELECT action,date_logged,command_by FROM mod_w3esxi_logs WHERE serviceid = '$serviceid'";
    if (!$showAdminCommand) {
        $query .= " AND command_by = 'client' ";
    }
    $query .= " ORDER BY date_logged DESC LIMIT $limit";
    $result = mysql_query($query);

    if ($result && mysql_num_rows($result)) {
        $counter = 1;
        $actionArr = array(
            'poweroff' => 'Power OFF',
            'poweron' => 'Power ON',
            'reset' => 'Reset',
            'rebootos' => 'Reboot OS',
            'shutdownos' => 'Shutdown OS',
            'suspend' => 'Suspend',
            'unsuspend' => 'Unsuspend',
        );
        $commandByArr = array(
            'client' => 'You',
            'admin' => 'Admin',
        );

        while ($row = mysql_fetch_assoc($result)) {
            $html .= "
        <tr>
            <td>$counter</td>
    		<td>{$actionArr[$row['action']]}</td>
    		<td>{$row['date_logged']}</td>
    		<td>{$commandByArr[$row['command_by']]}</td>
        </tr>
            ";

            $counter++;
        }
    }

    $html .= '  </tbody></table>';
    return $html;

}
/**
 * w3esxi_poweron()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_poweron($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        if (!$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->powerON($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('poweron', $params['serviceid'], $vmid, 'client');
                $result = 'success';
            } else {
                $result = "Cannot Power ON VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;
}

/**
 * w3esxi_adminpoweron()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_adminpoweron($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        $force = w3e_getSettings('force_admin_commands');

        if ($force || !$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->powerON($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('poweron', $params['serviceid'], $vmid, 'admin');
                $result = 'success';
            } else {
                $result = "Cannot Power ON VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}

/**
 * w3esxi_poweroff()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_poweroff($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        if (!$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->powerOFF($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('poweroff', $params['serviceid'], $vmid, 'client');
                $result = 'success';
            } else {
                $result = "Cannot Power OFF VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_adminpoweroff()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_adminpoweroff($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);
        $force = w3e_getSettings('force_admin_commands');
        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);

        if ($force || !$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->powerOFF($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('poweroff', $params['serviceid'], $vmid, 'admin');
                $result = 'success';
            } else {
                $result = "Cannot Power OFF VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_reset()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_reset($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        if (!$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->reset($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('reset', $params['serviceid'], $vmid, 'client');
                $result = 'success';
            } else {
                $result = "Cannot Reset VPS";
            }

        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_adminreset()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_adminreset($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);
        $force = w3e_getSettings('force_admin_commands');
        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);

        if ($force || !$expireDate || $expireDate < time()) {

            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->reset($vmid);
            } else {
                $response = false;
            }

            if ($response) {

                w3e_insertLog('reset', $params['serviceid'], $vmid, 'admin');
                $result = 'success';
            } else {

                $result = "Cannot Reset VPS";
            }

        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_reboot()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_reboot($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        if (!$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->rebootOS($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('rebootos', $params['serviceid'], $vmid, 'client');
                $result = 'success';
            } else {
                $result = "Cannot Reboot OS of VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_adminreboot()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_adminreboot($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);
        $force = w3e_getSettings('force_admin_commands');
        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);

        if ($force || !$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->rebootOS($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('rebootos', $params['serviceid'], $vmid, 'admin');
                $result = 'success';
            } else {
                $result = "Cannot Reboot OS of VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;
}
/**
 * w3esxi_shutdown()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_shutdown($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);

        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);
        if (!$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->shutdownOS($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('shutdownos', $params['serviceid'], $vmid, 'client');
                $result = 'success';
            } else {
                $result = "Cannot Shutdown OS of VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;

}
/**
 * w3esxi_adminshutdown()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_adminshutdown($params)
{
    //Check Server is disabled?
    $serverid = $params['serverid'];
    $result = mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");
    $row = mysql_fetch_assoc($result);
    $disabled = $row['disabled'];
    if ($disabled) {
        return "<strong>Server is disabled.<strong>";
    }
    $result = null;
    $response = null;
    if ($params['moduletype'] == 'w3esxi') {

        $user = $params['serverusername'];
        $pass = $params['serverpassword'];
        $host = $params['serverip'];

        $vmid = w3e_getVMID($params);
        $force = w3e_getSettings('force_admin_commands');
        $expireDate = w3e_getLastCommandExpireDate($params['serviceid']);

        if ($force || !$expireDate || $expireDate < time()) {
            if ($vmid != null) {
                $vmware = new VMware;
                $vmware->setServerConfig($host, $user, $pass);

                $response = $vmware->shutdownOS($vmid);
            } else {
                $response = false;
            }

            if ($response) {
                w3e_insertLog('shutdwonos', $params['serviceid'], $vmid, 'admin');
                $result = 'success';
            } else {
                $result = "Cannot Shutdown OS of VPS";
            }
        } else {
            $expireDate = abs(time() - $expireDate);
            $result = "Your request is already sent. You should wait $expireDate seconds";
        }

    }

    return $result;
}

/**
 * w3esxi_ClientAreaCustomButtonArray()
 *
 * @return
 */
function w3esxi_ClientAreaCustomButtonArray()
{
    $buttonarray = array(
        "Power On VPS" => "poweron",
        "Power Off VPS" => "poweroff",
        "Reset VPS" => "reset",
        "ShutDown OS" => "shutdown",
        "Reboot OS" => "reboot",
    );
    return $buttonarray;
}

/**
 * w3esxi_AdminCustomButtonArray()
 *
 * @return
 */
function w3esxi_AdminCustomButtonArray()
{
    $buttonarray = array(
        "Power On VPS" => "adminpoweron",
        "Power Off VPS" => "adminpoweroff",
        "Reset VPS" => "adminreset",
        "ShutDown OS" => "adminshutdown",
        "Reboot OS" => "adminreboot",
    );
    return $buttonarray;
}

/**
 * w3esxi_AdminServicesTabFields()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_AdminServicesTabFields($params)
{

    $vmid = w3e_getVMID($params, true);
    $options = false;
    $disabled = false;
    $list = null;
    $connect = true;
    if ($params['serverid']) {

        $serverid = $params['serverid'];
        $result = @mysql_query("SELECT disabled FROM tblservers WHERE id = '$serverid'");

        $disabled = true;
        if ($result) {
            $row = mysql_fetch_assoc($result);
            $disabled = $row['disabled'];
        }
        if (!$disabled) {
            $list = w3e_getNewVmidList($params, $vmid);
            if ($list == 'FAIL') {
                $options = false;
                $connect = false;
            } else {
                $options =
                    '
                <option value="0">Select VMID or Remove Current</option>
                <option value="-1">REMOVE</option>
                ';

                foreach ($list['vmlist'] as $id => $name) {
                    $options .= "<option value=\"$id\">$id - $name</option>\n";
                }
            }
        }

    }
    $current = ' NOT SELECTED ';
    if ($vmid) {
        $current = $vmid . ' - ' . $list['vmidname'];
    }

    $data = w3e_getSettings('show_info_admin');

    if (!$options) {
        $options = '
            <option value="0">None</option>
            <option value="-1">REMOVE</option>';
        if ($disabled) {
            $info = ' Server is Disabled ';
        } else if (!$connect) {
            $info = ' Cannot Connect To Server ';

        } else {
            $info = ' Cannot Connect To Server Because Server Is Not Selected ';
        }

    } elseif ($data && !(isset($_GET['showinfo']) && $_GET['showinfo'] == '0')) {
        $info = ' SERVICE NOT SELECTED ';
        if ($vmid) {
            $html = @file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'server_clientmanager.tpl');
            if (!$html) {
                echo "<strong>Cannot LOAD W3ESXI ClientArea TPL<strong>";
            }
            $info = w3e_createInfo($params, $vmid, $html, 'admin');

        }
    } else {
        $info = ' INFO IS DISABLED FOR ADMIN ';
    }
    $script = '
    <script type="text/javascript">
        function w3e_alertForRemove(w3e_vmidmenu){
            if(w3e_vmidmenu.value == -1){
                alert("W3ESXi Warning: This option also deletes all logs of this service from database!");
            }
        }
    </script>
    ';
    $fieldsarray = array(
        'Current VMID' => $current,
        'VMID' => $script . '<select name="VMID_LIST" onchange="w3e_alertForRemove(this)">' . $options . '</select>',
        'Service Overview' => $info,
    );

    return $fieldsarray;

}

/**
 * w3esxi_AdminServicesTabFieldsSave()
 *
 * @param mixed $params
 * @return
 */
function w3esxi_AdminServicesTabFieldsSave($params)
{

    $selectVal = intval($_POST['VMID_LIST']);
    if ($selectVal == -1) {
        $query = "
        DELETE FROM `mod_w3esxi`
        WHERE `serviceid`='{$params['serviceid']}'
        ";
        @mysql_query($query);
        $query = "
        DELETE FROM `mod_w3esxi_logs`
        WHERE `serviceid`='{$params['serviceid']}'
        ";
        @mysql_query($query);

    } elseif ($selectVal > 0) {
        if (w3e_getVMID($params, true)) {
            $query = "
            UPDATE `mod_w3esxi`
            SET
                `vmid`='$selectVal',
                `clientid`='{$params['clientsdetails']['userid']}',
                `serverid`='{$params['serverid']}',
                `pid`='{$params['serverid']}'
            WHERE `serviceid`='{$params['serviceid']}'
            ";
            @mysql_query($query);
        } else {
            $query = "
            INSERT INTO `mod_w3esxi` (`serviceid`, `vmid`, `clientid`, `serverid`, `pid`)
            VALUES (
                '{$params['serviceid']}',
                '$selectVal',
                '{$params['clientsdetails']['userid']}',
                '{$params['serverid']}',
                '{$params['pid']}'
            )
            ";
            @mysql_query($query);
        }
    }
}
