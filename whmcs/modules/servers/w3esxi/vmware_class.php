<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
if (!defined("W3E_ROOT")) {
    define('W3E_ROOT', dirname(__FILE__));
}
if (!defined("DS")) {
    define('DS', DIRECTORY_SEPARATOR);
}

require_once W3E_ROOT . DS . 'libs' . DS . 'Net' . DS . 'SSH2.php';

class LibSSH2
{

    private $host;

    private $user;

    private $port;

    private $password;

    private $con = null;

    private $lib;

    public function LibSSH2($library)
    {
        $this->lib = 'phpseclib';
        if ($library == 'phpext') {
            $this->lib = 'phpext';
        }

    }

    public function connect($host, $port, $skiperror = false)
    {

        $this->host = $host;
        $this->port = $port;
        if ($this->lib == 'phpext') {
            try {
                $this->con = @ssh2_connect($this->host, $this->port);
            } catch (Exception $e) {

            }

        } else {
            try {
                $this->con = @new Net_SSH2($this->host, $this->port);
            } catch (Exception $e) {

            }
        }
        if ($this->lib != 'phpext') {
            if (!$this->con || !$this->con->fsock) {
                if ($skiperror) {
                    return false;
                }

                die("Connection failed !");
            }
        } else {
            if (!$this->con) {
                if ($skiperror) {
                    return false;
                }

                die("Connection failed !");
            }
        }

        return true;
    }

    public function authPassword($user, $password, $skiperror = false)
    {

        $this->user = $user;
        $this->password = $password;
        if ($this->lib == 'phpext') {
            try {
                $auth = @ssh2_auth_password($this->con, $this->user, $this->password);
            } catch (Exception $e) {

            }
            if (!$auth) {
                if ($skiperror) {
                    return false;
                }

                die("Authorization failed !");
            }
        } else {
            if (!$this->con->login($this->user, $this->password)) {
                if ($skiperror) {
                    return false;
                }

                die("Authorization failed !");
            }
        }

        return true;

    }

    public function cmdExec($cmd)
    {
        try {
            if ($this->lib == 'phpext') {
                $response = '';

                $stream = @ssh2_exec($this->con, $cmd);
                @stream_set_blocking($stream, true);

                if ($stream) {

                    while (($buffer = fgets($stream)) !== false) {
                        $response .= $buffer;
                    }

                }
                return $response;
            } else {
                $response = @$this->con->exec($cmd);
                return $response;
            }

        } catch (Exception $e) {
            return false;
        }

    }

    public function isConnected()
    {
        if ($this->con == null) {
            return false;
        } else {
            return true;
        }
    }

} //end of class LibSSH2

class VMware
{

    private $ssh = null;

    private $config = null;

    private $vm_all_array = null;

    private $total_vm = null;

    private $ip = null;

    private $port = null;

    private $user = null;

    private $pass = null;

    private $setError = false;

    private $location = null;

    private $reConnect = false;

    public function __construct()
    {

        $sshlibrary = $this->getSSHLibrary();

        if (defined('W3E_SET_ERROR') && W3E_SET_ERROR) {
            $this->setError = true;
            if (defined('W3ELITE_MOD_LINK')) {
                $this->location = 'Location: ' . W3E_MOD_LINK . '&view=home';
            }
        }
        $this->ssh = new LibSSH2($sshlibrary);

    }

    public function setServerConfig($ip, $user, $pass, $port = 22)
    {
        $splitIP = explode(':', $ip);

        if (count($splitIP) == 2) {
            $port = $splitIP[1];
        }
        if ($splitIP[0] == $this->ip &&
            $this->user == $user &&
            $this->pass == $pass &&
            $this->port == $port
        ) {
            return;
        } else {
            $this->reConnect = true;
            $this->ip = $splitIP[0];
            $this->user = $user;
            $this->pass = $pass;
            $this->port = $port;
        }

    }

    protected function connect()
    {
        if ($this->ip == null) {
            if ($this->setError) {
                W3ERender::setErrors('Cannot connect to server: IP is null', 'ERROR');
                if ($this->location != null) {
                    header($this->location);
                    exit();
                }
            }
            die("Cannot connect to vmware server");
        }
        if ($this->setError) {
            $result = @$this->ssh->connect($this->ip, $this->port, true);
            if (!$result) {
                W3ERender::setErrors('Cannot connect to server: ' . $this->ip . ':' . $this->port, 'ERROR');
                if ($this->location != null) {
                    header($this->location);
                    exit();
                }
            }
            $result = @$this->ssh->authPassword($this->user, $this->pass, true);
            if (!$result) {
                W3ERender::setErrors('User/Pass is incorrect for server: ' . $this->ip . ':' . $this->port, 'ERROR');
                if ($this->location != null) {
                    header($this->location);
                    exit();
                }
            }
        } else {
            $this->ssh->connect($this->ip, $this->port);
            $this->ssh->authPassword($this->user, $this->pass);
        }

        $this->reConnect = false;
        return true;
    }

    protected function exec($cmd)
    {
        if (!$this->ssh->isConnected() || $this->reConnect) {
            $this->connect();
        }
        $response = $this->ssh->cmdExec($cmd);
        return $response;
    }
    private function getSSHLibrary()
    {
        $sshlib = 'phpseclib';
        $result = @mysql_query("SELECT `value` FROM `mod_w3esxi_config` WHERE `name` = 'ssh_library'");
        $numRow = @mysql_num_rows($result);
        if (!$result || $numRow != 1) {
            return $sshlib;
        }
        $row = mysql_fetch_array($result);

        if ($row['value'] == 'phpext') {
            $sshlib = 'phpext';
        }
        return $sshlib;
    }

    public function getAllVmInfo()
    {
        if ($this->vm_all_array == null) {
            $response = $this->exec("vim-cmd vmsvc/getallvms");
            $this->vm_all_array = $this->parsAllVmInfo($response);
        }

        return $this->vm_all_array;
    }

    public function getSummaryInfo($vmid)
    {
        $this->vm_info_array = null;
        $response = $this->exec("vim-cmd vmsvc/get.summary $vmid");
        return $this->parsInfo($response);
    }

    public function getGuestInfo($vmid)
    {
        $this->vm_info_array = null;
        $response = $this->exec("vim-cmd vmsvc/get.guest $vmid");
        return $this->parsInfo($response);
    }

    public function getDataStoreInfo($vmid)
    {
        $this->vm_info_array = null;
        $response = $this->exec("vim-cmd vmsvc/get.datastore $vmid");
        return $this->parsDataStoreInfo($response);
    }

    protected function parsDataStoreInfo($response)
    {
        $storageArr = array();
        preg_match('/freespace *?(\d{1,})/i', $response, $freespace);
        preg_match('/capacity *?(\d{1,})/i', $response, $capacity);

        $storageArr['freespace'] = $freespace[1];
        $storageArr['capacity'] = $capacity[1];
        return $storageArr;
    }

    protected function parsInfo($response)
    {
        $response = preg_replace('@\(vim.*\)@', '', $response);
        $response = preg_replace('@([^ ]*) =@', '"$1" =>', $response);
        $search = array(
            '<unset>',
            '(string)',
            '{',
            '}',
            '[',
            ']',
            'Listsummary:',
            'Guest information:',
        );
        $replace = array(
            'null',
            '',
            'array(',
            ')',
            'array(',
            ')',
            '',
            '',
        );
        $response = str_replace($search, $replace, $response);
        $response = addcslashes($response, '\\');
        $arr = null;
        @eval('$arr = ' . $response . ';');

        if ($arr == null) {
            return false;
        }

        return $arr;

    }

    protected function parsAllVmInfo($response)
    {
        if (preg_match_all('@^(\d+) {2,}([^\[\]]+)\[(.*)\].*/(.*\.vmx) {2,}([a-zA-Z]+)@m', $response, $matches)) {
            $total = count($matches[1]);
            $parsed_array = array();

            for ($i = 0; $i < $total; $i++) {
                $vm_arr = array();
                $vm_arr['vmid'] = trim($matches[1][$i]);
                $vm_arr['name'] = trim($matches[2][$i]);
                $vm_arr['datastore'] = trim($matches[3][$i]);
                $vm_arr['filename'] = trim($matches[4][$i]);

                $os = strtolower(trim($matches[5][$i]));
                if (stripos($os, 'win') !== false) {
                    $os = 'WIN';
                } elseif (stripos($os, 'linux') !== false) {
                    $os = 'LINUX';
                } else {
                    $os = "OTHER";
                }
                $vm_arr['os'] = $os;

                $parsed_array[] = $vm_arr;

            }
            $this->total_vm = $total;
            return $parsed_array;
        } else {
            return false;
        }
    }

    public function powerOFF($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.off $vmid");
        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function powerON($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.on $vmid");
        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }
    public function unSuspend($vmid)
    {
        if (!defined('W3E_SET_ERROR')) {
            $this->setError = true;
            $this->location = null;
        }
        $response = $this->exec("vim-cmd vmsvc/power.on $vmid");

        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function reset($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.reset $vmid");
        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function rebootOS($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.reboot $vmid");
        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function shutdownOS($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.shutdown $vmid");
        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function suspend($vmid)
    {
        if ($this->suspendVPS($vmid)) {
            return true;
        } else {
            return false;
        }
    }

    private function suspendVPS($vmid)
    {
        if (!defined('W3E_SET_ERROR')) {
            $this->setError = true;
            $this->location = null;
        }
        $response = $this->exec("vim-cmd vmsvc/power.suspend $vmid");

        if (stripos($response, 'failed') === false) {
            return true;
        } else {
            return false;
        }
    }

    public function getState($vmid)
    {
        $response = $this->exec("vim-cmd vmsvc/power.getstate $vmid");
        return $this->parsStateInfo($response);
    }

    protected function parsStateInfo($response)
    {
        if (stripos($response, "notfound") !== false || trim($response) == '') {
            return "NOTFOUND";
        }

        return strtolower(trim(str_replace(array("Retrieved runtime info", ''), array('', ''), $response)));
    }

    public function getServerInfo()
    {
        $response = $this->exec("vim-cmd hostsvc/hostsummary");
        return $this->parsInfo($response);
    }

    public function isVmIdExist($vmid)
    {
        if ($this->getState($vmid) == "NOTFOUND") {
            return false;
        } else {
            return true;
        }
    }

    public function getTotalVm()
    {
        if ($this->total_vm == null && $this->vm_all_array == null) {
            $this->getAllVmInfo();
            if ($this->total_vm == null) {
                return false;
            }
        }
        return $this->total_vm;
    }

    public function getImportantInfo($vmid, $getStorage = false)
    {
        $vminfoArr = $this->getSummaryInfo($vmid);
        if ($vminfoArr === false) {
            return false;
        }
        $info = array();
        $info['vmid'] = $vmid;
        $info['memorySize'] = intval($vminfoArr['config']['memorySizeMB']);
        $info['memoryUsage'] = intval($vminfoArr['quickStats']['guestMemoryUsage']);
        $info['cpuMax'] = intval($vminfoArr['runtime']['maxCpuUsage']);

        $info['bootTime'] = str_replace('T', ' ', $vminfoArr['runtime']['bootTime']);
        $os = strtolower(trim($vminfoArr['guest']['guestId']));
        if (stripos($os, 'win') !== false) {
            $os = 'WIN';
        } elseif (stripos($os, 'centos') !== false) {
            $os = 'CentOS';
        } elseif (stripos($os, 'ubuntu') !== false) {
            $os = 'Ubuntu';
        } elseif (stripos($os, 'debian') !== false) {
            $os = 'Debian';
        } elseif (stripos($os, 'linux') !== false) {
            $os = 'LINUX';
        } else {
            $os = "OTHER";
        }
        $info['os'] = $os;
        $info['osFullName'] = trim($vminfoArr['guest']['guestFullName']);
        $info['hostname'] = $vminfoArr['guest']['hostName'];
        $info['ip'] = $vminfoArr['guest']['ipAddress'];
        $info['powerState'] = strtolower(trim($vminfoArr['runtime']['powerState']));
        $info['cpuUsage'] = intval($vminfoArr['quickStats']['overallCpuUsage']);
        $info['uptime'] = intval($vminfoArr['quickStats']['uptimeSeconds']);

        $info['hd'] = round((abs($vminfoArr['storage']['committed'])) / (1024 * 1024 * 1024), 2);
        $info['hardUsage'] = 0;
        $info['hardFree'] = 0;
        $info['hardNums'] = 0;
        if ($getStorage) {
            $guest = $this->getGuestInfo($vmid);
            //ADD
            if (isset($guest['disk'])) {
                $hdd = 0;
                echo '<pre>';
                print_r($guest['disk']);
                exit();
                foreach ($guest['disk'] as $hd) {
                    $hdd += round((abs($hd['capacity'])) / (1024 * 1024 * 1024), 2);
                    $info['hardUsage'] += round((abs($hd['capacity']) - abs($hd['freeSpace'])) / (1024 * 1024 * 1024), 2);
                    $info['hardFree'] += round((abs($hd['freeSpace'])) / (1024 * 1024 * 1024), 2);
                }
                if ($hdd > 0) {
                    $info['hd'] = $hdd;
                }

                $info['hardNums'] = count($guest['disk']);
            }
        }
        return $info;

    }

} //end of class VMware
