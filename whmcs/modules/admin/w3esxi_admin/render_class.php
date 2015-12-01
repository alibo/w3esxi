<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
class W3ERender
{
    private $output = '';
    private $vmware;

    public function W3ERender()
    {

        $this->checkPHPRequirment();
        if ($this->check()) {
            $this->checkDebuggingMode();
        }

    }

    public function render()
    {
        $this->output .= $this->loadCSS();
        $this->output .= $this->renderMenu();
        $this->output .= $this->renderErrors();
        if (isset($_GET['view'])) {
            if ($_GET['view'] == 'allvm') {
                $this->output .= $this->renderAllVm();

            } else if ($_GET['view'] == 'support') {
                $this->output .= $this->renderSupport();
            } else if ($_GET['view'] == 'settings') {
                if (isset($_GET['action']) && $_GET['action'] == 'save') {
                    $this->processSettings();
                } else {
                    $this->output .= $this->renderSettings();
                }
            } else if ($_GET['view'] == 'quick') {
                $this->output .= $this->renderQuickInsert();
            } else if ($_GET['view'] == 'log') {

                $this->output .= $this->renderLogs();
            } else if ($_GET['view'] == 'home') {
                $this->output .= $this->renderHome();
            } else if ($_GET['view'] == 'phpinfo') {
                $this->output .= $this->renderPHPinfo();
            } else if ($_GET['view'] == 'servers') {
                if (isset($_POST['action']) || isset($_GET['action'])) {
                    $this->output .= $this->processServers();
                } else {
                    $this->output .= $this->renderServers();
                }
            }

        } else if (isset($_POST['action']) && $_POST['action'] == 'update') {
            $this->update();

        } else {
            $this->output .= $this->renderHome();
        }
        $this->output .= $this->renderFooter();
        $this->addNewErrors();
        echo $this->output;

    }

    //RENDER EACH PARTs
    private function renderQuickInsert()
    {
        $output = '';

        if (isset($_POST['filter']) || isset($_GET['filter'])) {
            if (!isset($_REQUEST['page'])) {
                $output .= $this->processQuickInsert();
            } else {
                if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'save') {
                    $this->processQuickInsertSave();
                } else {
                    $output .= $this->renderQuickInsertTable();
                    $output .= $this->renderPagination('quick');
                }

            }

        } else {
            $output .= $this->renderFilter('quickinsert');
        }
        return $output;
    }
    private function renderQuickInsertTable()
    {

        $html = null;
        if (!isset($_SESSION['W3EQUICK'])) {
            $this->setErrors('Filter form is not filled.', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        }
        $offset = 0;
        $limit = $_SESSION['W3EQUICK']['items'];
        if (isset($_REQUEST['page'])) {
            $page = intval($_REQUEST['page']);
            if ($page > 0) {
                $offset = ($page - 1) * $limit;
            }

        }
        $query = $_SESSION['W3EQUICK']['query'];
        $query .= " LIMIT $limit OFFSET $offset ";

        $result = @mysql_query($query);
        if (!$result) {
            $this->setErrors('Cannot get items from DB: ' . mysql_error(), 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        }

        if (!mysql_num_rows($result)) {
            $this->setErrors('There is no item to see.', 'WARNING');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        } else {
            $page = 1;
            if (isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0) {
                $page = intval($_REQUEST['page']);
            }
            $html .= '       <div id="W3E_quicktable">
            <script type="text/javascript">
                w3e_select = true;
                function w3e_de_select(){
                    var w3e_checkbox = document.querySelectorAll("#w3eCheckbox");
                    for(i = 0; i < w3e_checkbox.length; i++){
                         w3e_checkbox[i].checked = w3e_select;
                    }
                    if(w3e_select){
                        w3e_select = false;
                    }else{
                        w3e_select = true;
                    }
                }

            </script>
            	<h3 style="color: #FFAA00">Quick Insert</h3>
                <form action="' . W3E_MOD_LINK . '&view=quick&filter=1&action=save&page=' . $page . '" method="POST">
                    <table id="w3e_quickinsert_table"">
                    	<thead>
                            <tr>
                                <th style="width:3%" onclick="w3e_de_select()">*</th>
                                <th style="width:3%">#</th>
            					<th style="width:15%">Client</th>
                                <th style="width:15%">Server</th>
            					<th style="width:15%">Product</th>
                                <th style="width:6%">VMID</th>
                                <th style="width:25%">Select VMID</th>
                                <th style="width:17%">IP</th>

                            </tr>
                    </thead>
                    <tbody>';

            $counter = $offset + 1;
            while ($row = mysql_fetch_assoc($result)) {

                $clientLink = "clientssummary.php?userid={$row['clientid']}";
                $productLink = "configproducts.php?action=edit&id={$row['pid']}";
                $serverLink = "configservers.php?action=manage&id={$row['serverid']}";
                $serviceLink = "clientshosting.php?userid={$row['clientid']}&id={$row['id']}";

                $pOS = $row['os'];
                if ($pOS == 'Other') {
                    $pOS = $row['otheros'];
                }
                $vmlist = $_SESSION['W3EQUICK']['vmidlist'];
                $vmlist = str_replace("value=\"{$row['serverid']}_{$row['vmid']}\"", "value=\"{$row['serverid']}_{$row['vmid']}\" selected=\"selected\"", $vmlist);

                $html .= '
                <tr>
                    <td><input id="w3eCheckbox" type="checkbox" value="' . $row['id'] . '" name="W3EServices[]" /></td>
                    <td>' . $counter . '</td>
                    <td><a href="' . $clientLink . '" alt="' . $row['firstname'] . ' - ' . $row['lastname'] . '" title="' . $row['firstname'] . ' - ' . $row['lastname'] . '">' . $row['lastname'] . '</a></td>
                    <td><a href="' . $serverLink . '" alt="' . $row['ipaddress'] . '" title="' . $row['ipaddress'] . '">' . $row['servername'] . '</a></td>
                    <td><a href="' . $productLink . '" alt="' . $pOS . '" title="' . $pOS . '">' . $row['productname'] . '</a></td>
                                        <td><a href="' . $serviceLink . '">' . $row['vmid'] . '</a></td>
                    <td><select name="W3EVMID_' . $row['id'] . '" id="w3e_vmid_menu">

                    <optgroup label="W3ESXi">
                    <option value="0" selected="selected">None</option>
                    <option value="-1">Remove</option>
                    </optgroup>
                    ' . $vmlist . '
                    </select> </td>
                    <td><input type="text" id="w3e_ip" name="W3EIP_' . $row['id'] . '" value="' . $row['dedicatedip'] . '" /></a></td>
                </tr>';

                $counter++;
            }

            $html .= '
             </tbody>
               </table>
               <div id="w3e_save">
                  <select id="w3e_save_manu" name="back" >
                  	<option value="1" selected="selected">Back to this page</option>
                    <option value="0">Back to quick insert page</option>
                  </select>
                  <input type="hidden" name="filter" value="1" />
                   <input type="hidden" name="page" value="' . $page . '" />
                    <input type="hidden" name="view" value="quick" />
                     <input type="hidden" name="action" value="save" />
                  <input id="w3e_save_submit" type="submit" value="Save" />

                  </div>
    		</form>
          </div>  ';
        }
        return $html;
    }

    private function renderServers()
    {
        $html = '<h3 style="color: #FFAA00">Servers</h3>';
        //Connectivity
        if (!isset($_GET['noserverstates']) || $_GET['noserverstates'] != '1') {
            $html .= $this->renderConnectivity();
        }
        $html .= $this->renderRemoveNotUsedVmid();
        $html .= $this->renderFilter('removeVMID');
        $html .= $this->renderSetModuleToProducts();
        return $html;
    }
    private function renderSetModuleToProducts()
    {
        $html = '<h4 class="w3e">Set W3ESXi Module to products</h4>
<div id="setProducts">

  <form action="' . W3E_MOD_LINK . '&view=servers&action=setProducts" method="POST">
    <div id="product">
        <fieldset>
            <legend>Products</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items" onClick="deselect(\'W3EProductsSET[]\')" value="X" />
             <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EProductsSET[]\')" value="R" />
            </p>
              <select name="W3EProductsSET[]" multiple="multiple">
        	  <!-- Product Items -->
        	  ' . $this->getAllProductsForSetting(true) . '
              </select>
        </fieldset>
    </div>


    <input id="submitbutton" type="submit" value="Set" />
    <input type="hidden" name="action" value="setProducts" />
    <input type="hidden" name="view" value="servers" />
  </form>
</div>';
        return $html;
    }

    private function renderRemoveNotUsedVmid()
    {
        $formAction = W3E_MOD_LINK . '&view=servers&action=removenotusedvmid';
        $input = null;
        if (isset($_REQUEST['noserverstates']) && $_REQUEST['noserverstates'] == '1') {
            $formAction .= '&noserverstates=1';
            $input = '<input type="hidden" name="noserverstates" value="1" />';
        }
        $html = '
        <h4 class="w3e">Remove VMIDs with deleted products </h4>
        <div id="W3E_RemoveNotUsed">


                <form action=" ' . $formAction . '" method="POST">

<input id="w3e_cancelled" type="checkbox" name="w3e_cancelled" />
<label for="w3e_cancelled"> Also cancelled products </label>

<input type="hidden" name="action" value="removenotusedvmid" />';

        $html .= $input;
        $html .= '<input id="W3E_RemoveNotUsed_Submit" type="submit" value="Remove" />
                </form>
</div>';
        return $html;

    }
    private function renderServerInfo()
    {
        $html = null;

        if (!isset($_REQUEST['sid']) || !intval($_REQUEST['sid'])) {

            header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
            exit();
        }

        $sid = intval($_REQUEST['sid']);
        $servers = $this->getAllServers(false, array($sid));

        if (count($servers) != 1) {
            $this->setErrors('server id is incorrect: ' . $sid, 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
            exit();
        }

        $s = $servers[0];
        $host = $s['ipaddress'];
        $user = $s['username'];
        $pass = decrypt($s['password']);

        $vmware = new VMware;
        $vmware->setServerConfig($host, $user, $pass);

        if (!isset($_REQUEST['showserveroverall']) || $_REQUEST['showserveroverall'] != '0') {
            $sinfo = $vmware->getServerInfo();
            if (!$sinfo || empty($sinfo)) {
                $this->setErrors('Cannot fetch info', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
                exit();
            }
            $memorySize = round((abs($sinfo['hardware']['memorySize'])) / (1024 * 1024 * 1024), 2);
            $upt = $sinfo['quickStats']['uptime'];
            $h = round($upt / (3600), 0);
            $upt %= 3600;
            $m = round(($upt) / 60, 0);
            $upt %= 60;
            $sec = $upt;
            $uptime = "{$h}H {$m}Min {$sec}Sec";

            $status = ucfirst($sinfo['overallStatus']);
            $reboot = ($sinfo['rebootRequired']) ? 'Yes' : 'No';

            $html .= "
                <h3 style=\"color: #FA0;\">{$s['servername']} [{$s['ipaddress']}]</h3>
                <h4 class=\"w3e\">Server Overall</h4>
    <div id=\"W3E_Serverinfo\">

                <table id=\"w3e_serveroverallinfo_table\">

                    <tr>
    					<td>Cpu Model</td>
                        <td>{$sinfo['hardware']['cpuModel']}</td>
                    </tr>
                    <tr>
    					<td>Number of CPUs</td>
                        <td>{$sinfo['hardware']['numCpuPkgs']}</td>

                    </tr>
                    <tr>
    					<td>Number of CPU Cores</td>
                        <td>{$sinfo['hardware']['numCpuCores']}</td>
                    </tr>
                     <tr>
    					<td>Number of CPU Threads</td>
                        <td>{$sinfo['hardware']['numCpuThreads']}</td>

                    </tr>
                     <tr>
    					<td>Number of Nics</td>
                        <td>{$sinfo['hardware']['numNics']}</td>
                    </tr>
                    <tr>
    					<td>Number of HBAs</td>
                        <td>{$sinfo['hardware']['numHBAs']}</td>
                    </tr>
                    <tr>
    					<td>Memory Size</td>
                        <td>$memorySize GB</td>
                    </tr>
                    <tr>
    					<td>Boot Time</td>
                        <td>{$sinfo['runtime']['bootTime']}</td>
                    </tr>
                    <tr>
    					<td>UpTime</td>
                        <td>$uptime</td>
                    </tr>                    <tr>
    					<td>Vmware Fullname</td>
                        <td>{$sinfo['config']['product']['fullName']}</td>
                    </tr>
                    <tr>
    					<td>Overall CPU Usage</td>
                        <td>{$sinfo['quickStats']['overallCpuUsage']} MHz</td>
                    </tr>
                    <tr>
    					<td>Overall Memory Usage</td>
                        <td>{$sinfo['quickStats']['overallMemoryUsage']} MB</td>
                    </tr>
                    <tr>
    					<td>Overall Status</td>
                        <td>$status</td>
                    </tr>
                     <tr>
    					<td>Reboot Required</td>
                        <td>$reboot</td>
                    </tr>

                    </table>

                </div> ";
        }
        if (!isset($_REQUEST['showvmidlist']) || $_REQUEST['showvmidlist'] != '0') {
            $html .= '
                <h4 class="w3e">VMs</h4>
                <div id="W3E_servervmlist">
                    <table id="w3e_servervmlist_table"">
                	   <thead>
                        <tr>
                            <th style="width:5%">#</th>
        					<th style="width:22%">Name</th>
                            <th style="width:11%">VMID</th>
                            <th style="width:20%">DataStore</th>

        					<th style="width:32%">Filename</th>
                            <th style="width:10%">OS</th>

                        </tr>
                    </thead>
                    <tbody>';
            $vmlist = $vmware->getAllVmInfo();
            $counter = 1;
            foreach ($vmlist as $vm) {
                $html .= "
                    <tr>
                        <td>$counter</td>
                        <td>{$vm['name']}</td>
                        <td>{$vm['vmid']}</td>
                        <td>{$vm['datastore']}</td>
                        <td>{$vm['filename']}</td>
                        <td>{$vm['os']}</td>
                    </tr>
                    ";
                $counter++;
            }
            $html .= '
                        </tbody>
                    </table>
                 </div>
                ';

        }
        if (!$html) {
            $this->setErrors('There is no information to show', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
            exit();
        }
        return $html;
    }

    private function renderCommand()
    {

        $response = $this->processCommand();
        $sid = intval($_REQUEST['sid']);
        $servers = $this->getAllServers(false, array($sid));

        if (count($servers) != 1) {

            $this->setErrors('server id is incorrect: ' . $sid, 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=servers');
            exit();
        }

        $s = $servers[0];

        $ip_port = explode(':', $s['ipaddress']);
        $ip = $ip_port[0];
        $servername = $s['servername'];

        $html = ' <div id="W3E_Command">
 <h3>Command</h3>
 <h4 class="w3e">' . $servername . '</h4>
 <div id="w3e_commons">

 <input id="w3e_common_btn" type="button" value="ping" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="traceroute" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="' . $ip . '" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="vmware -v" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="uname -a" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="ps" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="grep" onclick="addToRequest(this)" />

 <input id="w3e_common_btn"type="button" value="/etc/ssh/sshd_config" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="kill" onclick="addToRequest(this)" />
  <input id="w3e_common_btn"type="button" value="vim-cmd /vmsvc/getallvms" onclick="addToRequest(this)" title="Get the list of virtual machines on the host" alt="Get the list of virtual machines on the host" />
 <input id="w3e_common_btn"type="button" value="wget" onclick="addToRequest(this)" />
 <input id="w3e_common_btn"type="button" value="uptime" onclick="addToRequest(this)" />

  <input id="w3e_common_btn"type="button" value="cat /etc/resolv.conf" onclick="addToRequest(this)" title="Find DNS servers" alt="Find DNS servers" />
 <input id="w3e_common_btn"type="button" value="vim-cmd vmsvc/power.getstate <vmid>" onclick="addToRequest(this)" title="Retrieves the power state of the specified virtual machine" alt="Retrieves the power state of the specified virtual machine"/>

 <input id="w3e_common_btn"type="button" value="ifconfig" onclick="addToRequest(this)" title="" alt=""/>
 <input id="w3e_common_btn"type="button" value="vdf -h" onclick="addToRequest(this)" title="view partion info (disk space etc)" alt="view partion info (disk space etc)"/>


 <input id="w3e_common_btn"type="button" value="ls /vmfs/volumes" onclick="addToRequest(this)" title="Datastores" alt="Datastores"/>
 <input id="w3e_common_btn"type="button" value="/etc/vmware/firewall/service.xml" onclick="addToRequest(this)" title="" alt=""/>

 <input id="w3e_common_btn"type="button" value="vim-cmd vmsvc/power.off <vmid>" onclick="addToRequest(this)" title="Power off the specified virtual machine" alt="Power off the specified virtual machine"/>
 <input id="w3e_common_btn"type="button" value="esxcli network firewall get" onclick="addToRequest(this)" title="the enabled or disabled status of the firewall and lists default actions." alt="the enabled or disabled status of the firewall and lists default actions."/>
 <input id="w3e_common_btn"type="button" value="esxcli network firewall set --enabled <true|false>" onclick="addToRequest(this)" title="Set to true to enable the firewall, set to false to disable the firewall" alt="Set to true to enable the firewall, set to false to disable the firewall"/>
 <input id="w3e_common_btn"type="button" value="esxcli network firewall ruleset rule list" onclick="addToRequest(this)" title="List rule sets information" alt="List rule sets information"/>
  <input id="w3e_common_btn"type="button" value="vim-cmd vmsvc/get.summary <vmid>" onclick="addToRequest(this)" title="Retrieves and displays the listsummary status from the vm" alt="Retrieves and displays the listsummary status from the vm" />
 <input id="w3e_common_btn"type="button" value="esxcli network ip connection list" onclick="addToRequest(this)" title="netstat" alt="netstat" />
 <input id="w3e_common_btn"type="button" value="/etc/init.d/sshd restart" onclick="addToRequest(this)" title="Restart the sshd process" alt="Restart the sshd process" />


 </div>

    <form action="' . W3E_MOD_LINK . '&view=servers&action=command&sid=' . $_REQUEST['sid'] . '" method="POST">

    	<textarea id="w3e_request" name="W3E_Command" >';
        if (isset($_POST['W3E_Command'])) {
            $html .= htmlentities($_POST['W3E_Command']);
        }

        $html .= '</textarea>
        <input type="hidden" name="action" value="command" />
         <input type="hidden" name="sid" value="' . intval($_REQUEST['sid']) . '" />
        <input type="submit" id="w3e_command_submit" value="Execute" />

        <textarea disabled="disabled" id="w3e_response">';
        $html .= htmlentities($response);
        $html .= '</textarea>
    </form>
    <script type="text/javascript">
	function addToRequest(btn){
		var request = document.getElementById(\'w3e_request\');

		request.value+= btn.value;


		focusTextBox();
	}

        function focusTextBox(){
            var txt = document.getElementById(\'w3e_request\');
            txt.focus(); //sets focus to element
            var val = txt.value; //store the value of the element
            txt.value = \'\'; //clear the value of the element
            txt.value = val;
        }



	</script>
 </div>';
        return $html;

    }
    private function renderConnectivity()
    {
        $this->checkSSH($this->getSettings('ssh_library'));
        $html = '
            <h4 class="w3e">Server states</h4>
            <div id="W3E_serverstates">

                    <table id="w3e_servers_table">
                    	<thead>
                            <tr>
                                <th style="width:5%">#</th>
            					<th style="width:25%">Server</th>
                                <th style="width:20%">IP</th>
            					<th style="width:15%">Status</th>
                                <th style="width:35%">Action</th>

                            </tr>
                        </thead>
                        <tbody>
        ';

        $servers = $this->getAllServers();

        if (count($servers)) {
            $counter = 1;
            foreach ($servers as $s) {
                $ip_port = explode(':', $s['ipaddress']);
                $ip = $ip_port[0];
                $port = null;
                if (!count($ip_port) != 2) {
                    $port = 22;
                } else {
                    $port = $ip_port[1];
                }
                $serverIcon = $this->checkConnectivity($ip, $port, $s['username'], decrypt($s['password']));
                if ($serverIcon == 'ORANGE') {
                    $this->setErrors('User/Pass is incorrect for server: ' . $s['servername'], 'ERROR');
                } else if ($serverIcon == 'RED') {
                    $this->setErrors('Cannot connect to server: ' . $s['servername'], 'ERROR');
                } else if ($serverIcon == 'YELLOW') {
                    $this->setErrors('server `' . $s['servername'] . '` doesn\t respond', 'ERROR');
                }
                $img = '<img height="25px" width="25px" src="' . W3E_IMGDIR . 'circle_' . strtolower($serverIcon) . '.png" alt="' . $serverIcon . '" title="' . $serverIcon . '" />';
                $html .= '                    <tr>
                    <td>' . $counter . '</td>
                    <td><a href="configservers.php?action=manage&id=' . $s['id'] . '">' . $s['servername'] . '</a></td>
                    <td>' . $s['ipaddress'] . '</td>
                    <td>' . $img . '</td>
                    <td>
                    <form action="' . W3E_MOD_LINK . '&view=servers&sid=' . $s['id'] . '" method="GET">
                        <input type="hidden" name="module" value="w3esxi_admin" />
                        <input type="hidden" name="view" value="servers" />
                        <input type="hidden" name="sid" value="' . $s['id'] . '" />
                    	<select id="w3e_server_menu" name="action">';

                if ($serverIcon == 'GREEN') {
                    $html .= '
                                <option value="showinfo">View info</option>
                                <option value="command">Command</option>';
                }
                $html .= '<option value="showsettings">Show server</option>
                        </select>
                        <input type="submit" id="w3e_submit_servers" value="GO"/>
                    </form>

                    </td>
                    </tr>';

                $counter++;
            }

        } else {
            $this->setErrors('No active server is exist', 'WARNING');
        }

        $html .= '
                    </tbody>
           </table>
      </div> ';
        return $html;
    }
    private function renderAllVm()
    {
        $output = '';
        if (isset($_POST['filter']) || isset($_GET['filter'])) {
            if (!isset($_REQUEST['page'])) {
                $output .= $this->processAllVM();
            } else {
                if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'command') {
                    $this->processAllVmCommand();
                } else {
                    $output .= $this->renderAllVmTable();
                    $output .= $this->renderPagination('allvm');
                }

            }

        } else {
            $output .= $this->renderFilter('allvm');
        }
        return $output;
    }
    private function renderAllVmTable()
    {
        /*
        $_SESSION['W3EALLVM'] = array();
        $_SESSION['W3EALLVM']['query']
        $_SESSION['W3EALLVM']['pagination_query']
        $_SESSION['W3EALLVM']['items']
         */
        $html = null;
        if (!isset($_SESSION['W3EALLVM'])) {
            $this->setErrors('Filter form is not filled.', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        }
        $offset = 0;
        $limit = $_SESSION['W3EALLVM']['items'];
        if (isset($_REQUEST['page'])) {
            $page = intval($_REQUEST['page']);
            if ($page > 0) {
                $offset = ($page - 1) * $limit;
            }

        }
        $query = $_SESSION['W3EALLVM']['query'];
        $query .= " LIMIT $limit OFFSET $offset ";

        $result = @mysql_query($query);
        if (!$result) {
            $this->setErrors('Cannot get items from DB: ' . mysql_error(), 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        }

        if (!mysql_num_rows($result)) {
            $this->setErrors('There is no item to see.', 'WARNING');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        } else {
            $page = 1;
            if (isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0) {
                $page = intval($_REQUEST['page']);
            }
            $html .= '       <div id="W3E_allvmtable">
            <script type="text/javascript">
                w3e_select = true;
                function w3e_de_select(){
                    var w3e_checkbox = document.querySelectorAll("#w3eCheckbox");
                    for(i = 0; i < w3e_checkbox.length; i++){
                         w3e_checkbox[i].checked = w3e_select;
                    }
                    if(w3e_select){
                        w3e_select = false;
                    }else{
                        w3e_select = true;
                    }
                }

            </script>
            ';
            $html .= '
        	<h3 style="color: #FFAA00">AllVM</h3>
            <form action="' . W3E_MOD_LINK . '&view=allvm&filter=1&action=save&page=' . $page . '" method="POST">
                <table id="w3e_allvm_table"">
                	<thead>
                        <tr>
                            <th style="width:3%" onclick="w3e_de_select()">*</th>
                            <th style="width:3%">#</th>
        					<th style="width:16%">Client</th>
                            <th style="width:16%">Server</th>
        					<th style="width:16%">Product</th>
                            <th style="width:6%">VMID</th>

                            <th style="width:12%">CPU</th>
                            <th style="width:12%">RAM</th>
                            <th style="width:12%">Space</th>
                            <th style="width:12%">UpTime</th>
                            <th style="width:9%">Status</th>



                        </tr>
                </thead>
                <tbody>
            ';
            $counter = $offset + 1;
            $vmware = new VMware;

            while ($row = mysql_fetch_assoc($result)) {

                //Connect to vmware server
                $host = $row['ipaddress'];
                $user = $row['username'];
                $pass = decrypt($row['password']);
                $vmware->setServerConfig($host, $user, $pass);

                $hard_details = $this->getSettings('show_hard_details');
                $info = $vmware->getImportantInfo($row['vmid'], $hard_details);
                if (!$info) {
                    $this->setErrors("Cannot get info of vmid: {$row['vmid']}", 'ERROR');
                    continue;
                }
                $state = 'white';
                if ($info['powerState'] == 'poweredon') {
                    $state = 'green';
                    $stateText = 'Powered On';
                } elseif ($info['powerState'] == 'poweredoff') {
                    $state = 'red';
                    $stateText = 'Powered Off';
                } elseif ($info['powerState'] == 'suspended') {
                    $state = 'blue';
                    $stateText = 'Suspended';
                } else {
                    $stateText = $info['powerState'];
                }

                $hard = $info['hd'] . ' GB';
                if ($hard_details) {
                    $hard = '<span title="Free Space: ' . $info['hardFree'] . ' GB / ' . $info['hardNums'] . '" alt="Free Space: ' . $info['hardFree'] . ' GB / ' . $info['hardNums'] . '">' . $info['hardUsage'] . '/' . $info['hd'] . ' GB</span>';
                }

                //WHMCS LINKS
                $clientLink = "clientssummary.php?userid={$row['clientid']}";
                $productLink = "configproducts.php?action=edit&id={$row['pid']}";
                $serverLink = "configservers.php?action=manage&id={$row['serverid']}";
                $serviceLink = "clientshosting.php?userid={$row['clientid']}&id={$row['serviceid']}";

                $pOS = $row['os'];
                if ($pOS == 'Other') {
                    $pOS = $row['otheros'];
                }
                $upt = $info['uptime'];
                $h = round($upt / (3600), 0);
                $upt %= 3600;
                $m = round(($upt) / 60, 0);
                $upt %= 60;
                $s = $upt;
                $uptime = "{$h}H {$m}Min {$s}Sec";

                $html .= '
                <tr>
                    <td><input id="w3eCheckbox" type="checkbox" value="' . $row['serviceid'] . '" name="W3EServices[]" /></td>
                    <td>' . $counter . '</td>
                    <td><a href="' . $clientLink . '" alt="' . $row['firstname'] . ' - ' . $row['lastname'] . '" title="' . $row['firstname'] . ' - ' . $row['lastname'] . '">' . $row['lastname'] . '</a></td>
                    <td><a href="' . $serverLink . '" alt="' . $row['ipaddress'] . '" title="' . $row['ipaddress'] . '">' . $row['servername'] . '</a></td>
                    <td><a href="' . $productLink . '" alt="' . $pOS . '" title="' . $pOS . '">' . $row['productname'] . '</a></td>
                    <td><a href="' . $serviceLink . '" alt="' . $row['dedicatedip'] . '" title="' . $row['dedicatedip'] . '">' . $row['vmid'] . '</a></td>
                    <td>' . $info['cpuUsage'] . '/' . $info['cpuMax'] . ' MHZ</td>
                    <td>' . $info['memoryUsage'] . '/' . $info['memorySize'] . ' MB</td>
                    <td>' . $hard . '</td>
                    <td><span title="Boot Time: ' . $info['bootTime'] . '" alt="Boot Time: ' . $info['bootTime'] . '">' . $uptime . '</span></td>
                    <td><img src="' . W3E_IMGDIR . 'circle_' . $state . '.png" alt="' . $stateText . '" title="' . $stateText . '" style="width:25px; height:25px;" /></td>
                </tr>';

                $counter++;
            }

            $html .= '
                </tbody>
           </table>
         <div id="w3e_command">
                         Action:
              <select name="W3E_ACTION" id="w3e_action_menu">

              <option value="poweron">Power ON</option>
              <option value="poweroff">Power OFF</option>
              <option value="reset">Reset</option>
              <option value="rebootos">Reboot OS</option>
              <option value="shutdownos">Shutdown OS</option>
              <option value="suspend">Suspend</option>
              <option value="unsuspend">Unsuspend</option>
                    </select>
              <select id="w3e_allvm_back_manu" name="back" >
              	<option value="1" selected="selected">Back to this page</option>
                <option value="0">Back to allvm page</option>
              </select>

              <input type="hidden" name="filter" value="1" />
               <input type="hidden" name="page" value="1" />
                <input type="hidden" name="view" value="allvm" />
                 <input type="hidden" name="action" value="command" />
              <input id="w3e_allvm_submit" type="submit" value="Go" />

              </div>
		</form>
      </div>    ';
        }
        return $html;

    }
    private function renderSettings()
    {
        $latestVersion = '0.7.5';

        $settings = $this->getSettings();

        $domainName = $_SERVER['SERVER_NAME'];
        if (substr($domainName, 0, 4) == 'www.') {
            $domainName = substr($domainName, 4);
        }

        $lockMenuArr = array(
            "10 Sec" => "10s",
            "20 Sec" => "20s",
            "30 Sec" => "30s",
            "45 Sec" => "45s",
            "1 Min" => "1m",
            "2 Min" => "2m",
            "3 Min" => "3m",
            "5 Min" => "5m",
            "7 Min" => "7m",
            "10 Min" => "10m",
            "15 Min" => "15m",
            "20 Min" => "20m",
            "30 Min" => "30m",
            "45 Min" => "45m",
            "1 Hour" => "1h",
            "2 Hours" => "2h",
            "3 Hours" => "3h",
            "4 Hours" => "4h",
            "5 Hours" => "5h",
        );
        $allvmItemsArr = array(1, 3, 5, 7, 10, 12, 15);
        $quickInsertItemsArr = array(1, 3, 5, 7, 10, 15, 20, 25, 30, 35, 40, 45, 50);
        $logsAdminArr = array(1, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100);
        $logsClientArr = array(1, 3, 5, 7, 10, 15, 20);

        $html = '

    <div id="W3E_Settings">
    <form action="' . W3E_MOD_LINK . '&view=settings&action=save" method="POST">
    	<h3>Settings</h3>
            <table id="w3e_showsettings_table">

                    <tr>
    					<td>Current Version</td>
                        <td>' . W3E_VERSION . '</td>
                    </tr>
                    <tr>
    					<td>Latest Version</td>
                        <td>' . $latestVersion . '</td>
                    </tr>
                    <tr>
    					<td>Your Domain</td>
                        <td>' . $domainName . '</td>
                    </tr>
                    <tr>
    					<td>SSH Library</td>
                        <td>
                        	<select id="W3E_SSH_MENU" name="ssh_library">
                            	<option value="phpseclib"';
        if ($settings['ssh_library'] == 'phpseclib') {
            $this->checkSSH('phpseclib');
            $html .= 'selected="selected"';
        }

        $html .= '>PHPSecLib</option>
                            	<option value="phpext"  ';
        if ($settings['ssh_library'] == 'phpext') {
            $this->checkSSH('phpext');
            $html .= 'selected="selected"';
        }

        $html .= '>PHP Extension</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="subtr_root">
                    	<td>VM Info</td>
                        <td>
                        </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Show VM info for client</td>
                        <td>
                        	<input type="checkbox" value="" name="show_info_client" ';
        if ($settings['show_info_client'] == '1') {
            $html .= 'checked="checked"';
        }

        $html .= ' />
                    </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Only show power state to client</td>
                        <td>
                        	<input type="checkbox" value="" name="only_show_power_state_to_client" ';
        if ($settings['only_show_power_state_to_client'] == '1') {
            $html .= 'checked="checked"';
        }

        $html .= ' />
                    </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Show VM info for admin</td>
                        <td>
                        	<input type="checkbox" value="" name="show_info_admin" ';
        if ($settings['show_info_admin'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Only show power state to admin</td>
                        <td>
                        	<input type="checkbox" value="" name="only_show_power_state_to_admin" ';
        if ($settings['only_show_power_state_to_admin'] == '1') {
            $html .= 'checked="checked"';
        }

        $html .= ' />
                    </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Show details of hard disks</td>
                        <td>
                        	<input type="checkbox" value="" name="show_hard_details" ';
        if ($settings['show_hard_details'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>



                    <tr id="subtr_root">
                    	<td>Lock Commands</td>
                        <td>
                        </td>
                    </tr>
                   <tr id="subtr">
                    	<td>&bull; Power ON</td>
                        <td><select id="W3E_LOCK_MENU" name="poweron_lock_time">';

        foreach ($lockMenuArr as $name => $value) {
            $html .= "<option value=\"$value\" ";
            if ($value == $settings['poweron_lock_time']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$name</option>";

        }

        $html .= '

                            </select>
                            </td>
                    </tr>

                  <tr id="subtr">
                    	<td>&bull; Power OFF</td>
                        <td><select id="W3E_LOCK_MENU" name="poweroff_lock_time">';
        foreach ($lockMenuArr as $name => $value) {
            $html .= "<option value=\"$value\" ";
            if ($value == $settings['poweroff_lock_time']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$name</option>";

        }
        $html .= '</select>
                            </td>
                    </tr>
                  <tr id="subtr">
                    	<td>&bull; Reset</td>
                        <td><select id="W3E_LOCK_MENU" name="reset_lock_time">';
        foreach ($lockMenuArr as $name => $value) {
            $html .= "<option value=\"$value\" ";
            if ($value == $settings['reset_lock_time']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$name</option>";

        }
        $html .= '</select>
                            </td>
                    </tr>
                  <tr id="subtr">
                    	<td>&bull; ShutDown OS</td>
                        <td><select id="W3E_LOCK_MENU" name="shutdownos_lock_time">';
        foreach ($lockMenuArr as $name => $value) {
            $html .= "<option value=\"$value\" ";
            if ($value == $settings['shutdownos_lock_time']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$name</option>";

        }
        $html .= '</select>
                            </td>
                    </tr>
                  <tr id="subtr">
                    	<td>&bull; Reboot OS</td>
                        <td><select id="W3E_LOCK_MENU" name="rebootos_lock_time">';
        foreach ($lockMenuArr as $name => $value) {
            $html .= "<option value=\"$value\" ";
            if ($value == $settings['rebootos_lock_time']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$name</option>";

        }
        $html .= '</select>
                            </td>
                    </tr>


               		<tr id="subtr_root">
                    	<td>Default number of items</td>
                        <td></td>
                    </tr>
                   <tr id="subtr">
                    	<td>&bull; AllVM</td>
                        <td><select id="W3E_ITEM_MENU" name="allvm_items_per_page">';

        foreach ($allvmItemsArr as $item) {
            $html .= "<option value=\"$item\" ";
            if ($item == $settings['allvm_items_per_page']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$item</option>";
        }

        $html .= '</select></td>
                    </tr>
                    <tr id="subtr">
                    	<td>&bull; Quick Insert</td>
                        <td><select id="W3E_ITEM_MENU" name="quick_insert_items_per_page">';
        foreach ($quickInsertItemsArr as $item) {
            $html .= "<option value=\"$item\" ";
            if ($item == $settings['quick_insert_items_per_page']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$item</option>";
        }

        $html .= '</select></td>
                    </tr>
                                        <tr id="subtr">
                    	<td>&bull; Logs for Admin</td>
                        <td><select id="W3E_ITEM_MENU" name="admin_logs_items_per_page">';

        foreach ($logsAdminArr as $item) {
            $html .= "<option value=\"$item\" ";
            if ($item == $settings['admin_logs_items_per_page']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$item</option>";
        }

        $html .= '</select></td>
                    </tr>
                                        <tr id="subtr">
                    	<td>&bull; Last logs for client</td>
                        <td><select id="W3E_ITEM_MENU" name="client_logs_items">';

        foreach ($logsClientArr as $item) {
            $html .= "<option value=\"$item\" ";
            if ($item == $settings['client_logs_items']) {
                $html .= 'selected="selected"';
            }
            $html .= ">$item</option>";
        }

        $html .= '</select></td>
                    </tr>
                    <tr id="subtr_root">
                    	<td>Auto remove VMIDs and Logs with deleted services</td>
                        <td></td>
                    </tr>
                    <tr>
                    <td id="subtr">&bull; Enable</td>
                        <td>
                        	<input type="checkbox" value="" name="auto_remove_vmid_of_deleted_services" ';
        if ($settings['auto_remove_vmid_of_deleted_services'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>
                    <tr id="subtr">
                    <td>&bull; Also Cancelled services</td>
                        <td>
                        	<input type="checkbox" value="" name="auto_remove_also_cancelled_services" ';
        if ($settings['auto_remove_also_cancelled_services'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>

                    <tr id="subtr_root">
                    	<td>Other</td>
                        <td></td>
                    </tr>

                    <tr id="subtr">
                    <td>&bull; Show Admin Commands in Client Logs</td>
                        <td>
                        	<input type="checkbox" value="" name="show_admin_commands_client_logs" ';
        if ($settings['show_admin_commands_client_logs'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>

                    <tr id="subtr">
                    <td>&bull; Force Command for Admin</td>
                        <td>
                        	<input type="checkbox" value="" name="force_admin_commands" ';
        if ($settings['force_admin_commands'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>

                    <tr id="subtr">
                    <td>&bull; Debugging Mode</td>
                        <td>
                        	<input type="checkbox" value="" name="debug_mode" ';
        if ($settings['debug_mode'] == '1') {
            $html .= 'checked="checked"';
        }
        $html .= ' />
                    </td>
                    </tr>



        </table>
        <div id="W3E_Settings_Submit">
            <input type="hidden" name="view" value="settings" />
            <input type="hidden" name="action" value="save" />';
        if (isset($_REQUEST['dontcheckversion']) && $_REQUEST['dontcheckversion'] == '1') {
            $html .= '<input type="hidden" name="dontcheckversion" value="1" />';
        }

        $html .= '<input type="submit" value="Save" />
        </div>
    </form>
    </div>';
        return $html;
    }

    private function renderLogs()
    {
        $output = '';

        if (isset($_POST['filter']) || isset($_GET['filter'])) {
            if (!isset($_REQUEST['page'])) {
                $output .= $this->processLogs();
            } else {
                $output .= $this->renderLogsTable();
                $output .= $this->renderPagination('log');
            }

        } else {
            $output .= $this->renderFilter('logs');
        }
        return $output;

    }
    private function renderLogsTable()
    {
        /*
        $_SESSION['W3ELOGS']['query']
        $_SESSION['W3ELOGS']['pagination_query']
        $_SESSION['W3ELOGS']['items']
         */

        if (!isset($_SESSION['W3ELOGS'])) {
            $this->setErrors('Filter form is not filled.', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=log');
            exit();
        }
        $offset = 0;
        $limit = $_SESSION['W3ELOGS']['items'];
        if (isset($_REQUEST['page'])) {
            $page = intval($_REQUEST['page']);
            if ($page > 0) {
                $offset = ($page - 1) * $limit;
            }

        }
        $query = $_SESSION['W3ELOGS']['query'];
        $query .= " LIMIT $limit OFFSET $offset ";

        $result = @mysql_query($query);
        if (!$result) {
            $this->setErrors('Cannot get items from DB: ' . mysql_error(), 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=log');
            exit();
        }

        if (!mysql_num_rows($result)) {
            $this->setErrors('There is no item to see.', 'WARNING');
            header('Location: ' . W3E_MOD_LINK . '&view=log');
            exit();
        } else {
            $counter = $offset + 1;
            $html = '
        <div id="W3E_logtable">
        	<h3 style="color: #FFAA00">Logs</h3>

                <table id="w3e_servers_table"">
                	<thead>
                        <tr>
                            <th style="width:3%">#</th>
        					<th style="width:12%">Client</th>
                            <th style="width:12%">Server</th>
        					<th style="width:12%">Product</th>
                            <th style="width:7%">VMID</th>
                            <th style="width:9%">Client IP</th>
                            <th style="width:9%">Action</th>
                            <th style="width:12%">Date</th>
                            <th style="width:7%">By</th>
                            <th>User-Agent</th>

                        </tr>
                </thead>
                <tbody>';
            while ($row = mysql_fetch_assoc($result)) {
                $clientLink = "clientssummary.php?userid={$row['clientid']}";
                $productLink = "configproducts.php?action=edit&id={$row['pid']}";
                $serverLink = "configservers.php?action=manage&id={$row['serverid']}";
                $serviceLink = "clientshosting.php?userid={$row['clientid']}&id={$row['serviceid']}";
                $ipFinderLink = "http://www.iplocationfinder.com/{$row['client_ip']}";
                $pOS = $row['os'];
                if ($pOS == 'Other') {
                    $pOS = $row['otheros'];
                }
                $userAgent = htmlentities($row['user_agent']);
                $html .= "                    <tr>
                    <td>$counter</td>
                    <td><a href=\"$clientLink\" alt=\"{$row['firstname']} - {$row['lastname']}\" title=\"{$row['firstname']} - {$row['lastname']}\">{$row['lastname']}</a></td>
                    <td><a href=\"$serverLink\" alt=\"{$row['ipaddress']}\" title=\"{$row['ipaddress']}\">{$row['servername']}</a></td>
                    <td><a href=\"$productLink\" alt=\"{$pOS}\" title=\"{$pOS}\">{$row['productname']}</a></td>
                    <td><a href=\"$serviceLink\" alt=\"{$row['dedicatedip']}\" title=\"{$row['dedicatedip']}\">{$row['vmid']}</a></td>
                    <td><a href=\"$ipFinderLink\">{$row['client_ip']}</a></td>
					<td>{$row['action']}</td>
                    <td>{$row['date_logged']}</td>
                    <td>{$row['command_by']}</td>
                    <td>
                    	<textarea>$userAgent</textarea>
                    </td>
                </tr>
                   ";
                $counter++;

            }
            $html .= '
                   </tbody>
           </table>
      </div>
            ';
            return $html;
        }
    }
    private function renderSupport()
    {
        return 'There is no support!';
    }
    private function renderPHPinfo()
    {
        $html = '';
        if (!function_exists('phpinfo')) {
            $html .= '<strong>phpinfo() is not available</strong>';
        } else {
            $html .= '
            <h4 class="w3e">PHP INFO</h4>
            <div id="w3e_phpinfo">';
            ob_start();
            phpinfo();
            $html .= ob_get_contents();
            ob_end_clean();
            $css = "
<style type=\"text/css\">
#w3e_phpinfo body {background-color: #ffffff; color: #000000;}
#w3e_phpinfo body, #w3e_phpinfo td, #w3e_phpinfo th, #w3e_phpinfo h1, #w3e_phpinfo h2 {font-family: sans-serif;}
#w3e_phpinfo pre {margin: 0px; font-family: monospace;}
#w3e_phpinfo a:link {color: #000099; text-decoration: none; background-color: #ffffff;}
#w3e_phpinfo a:hover {text-decoration: underline;}
#w3e_phpinfo table {border-collapse: collapse;}
#w3e_phpinfo .center {text-align: center;}
#w3e_phpinfo .center table { margin-left: auto; margin-right: auto; text-align: left;}
#w3e_phpinfo .center th { text-align: center !important; }
#w3e_phpinfo td, #w3e_phpinfo th { border: 1px solid #000000; font-size: 75%; vertical-align: baseline;}
#w3e_phpinfo h1 {font-size: 150%;}
#w3e_phpinfo h2 {font-size: 125%;}
#w3e_phpinfo .p {text-align: left;}
#w3e_phpinfo .e {background-color: #ccccff; font-weight: bold; color: #000000;}
#w3e_phpinfo .h {background-color: #9999cc; font-weight: bold; color: #000000;}
#w3e_phpinfo .v {background-color: #cccccc; color: #000000;}
#w3e_phpinfo .vr {background-color: #cccccc; text-align: right; color: #000000;}
#w3e_phpinfo img {float: right; border: 0px;}
#w3e_phpinfo hr {width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}
</style>";
            $html = preg_replace('@(<style type="text/css">.*?</style>)@is', $css, $html);
            $html .= '</div>';
        }
        return $html;
    }
    private function renderHome()
    {
        $html = '<div id="W3E_Home">';
        if (version_compare($this->getSettings('version'), W3E_VERSION, '<')) {
            $html .= '
        <div id="W3E_Home_Update">
        	<form action="' . W3E_MOD_LINK . '&action=update" method="POST">
        		<input type="submit" value="Update" alt="Update to ' . W3E_VERSION . '" title="Update to ' . W3E_VERSION . '" />
                <input type="hidden" name="action" value="update" />
            </form>
        </div>';
        }
        $html .= '</div>';
        return $html;
    }
    private function renderMenu()
    {
        $menu = '
<div id="w3e_logo" style="text-align:right;margin-bottom:-15px;margin-top: -20px;">
<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAPUAAACCCAMAAACpSH4uAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAMAUExURQAAABERESIiIjMzM0RERFVVVWZmZnd3dwwughc4iBk5iRo6iRs7ihw8iiNCjiVEjypIkSpIkixKkzJOljZSmDlVmTtXmzxYm0BbnURen0ZgoEdhoUhioU1mo1JrplNsp1dvqVhvqVtyq110rGZ8sWh9sm2CtG6DtW+DtnSIuHaKuX2PvH2QvfIAF/MRJvQiNvUzRfZEVfdVZPhmdPl3g4iIiJmZmaqqqru7u4WXwYubxIucxI6exZ6szaOx0Ke00q661rO+2L7I3vmIk/qZovuqsvy7wczMzN3d3cLL4MPM4c/W5tHY6N/k7/3M0f7d4O7u7uDl8O/y9//u8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAANSn0mIAAAEAdFJOU////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////wBT9wclAAAACXBIWXMAAA7DAAAOwwHHb6hkAAAAGXRFWHRTb2Z0d2FyZQBQYWludC5ORVQgdjMuNS44NzuAXQAACXxJREFUeF7tnH1f2zYQxxNIidnWbdBubbemW9eOboyE0oaQbWVlhQIrBbLw/l+LpwdLurMtnSQ7/izU/qdFlhP97nt3ks6OO+mneHQ+RdFpq/rTwd6yblnfbgu0Hn67+UJ1LeuW9e22QOvht5tvm81aD289/HZboPXw2823zeGthysfuHzzbDDYuQQuMRctF7DlkLe8cweF6HME+xxuDQZbqOWItxw2HFwlrA/vJuIwQznKWnb04C42ZZ8tx3AvHsg+g7nqNB/IlgfFFmjjxZugqPpIjgzIvtAtSvZlZoYkMYbIj3WeiWay1SndomVnZkiSTW2IxWtOSyrDGUWm9G42Ej3YJMmQ6MEmidXJd7StlNeAlsxYh6YPNt/VeC/2GI+PTynLFVi/MwPJQBrU2qNBHw0y901z7Q4KNmx5IHsbCyd30fWjYbXj5fGNS3lB9RugSMKGdpCwUYsFNgCbSI2wJREtAHUiW7LjpJpmfvVo7JBdUI3GJtwOtYj0hVSXwzaRz63IL4Kok0TYE6BOEjhD7FZXPRy+tssuqDbJTEU29HAZ2ZfAHyyRvQW7CMMg420WUEMPf1+HaJfsguo5UiRggwjNIhtBKoON7fKmgFq4DPqUZ4DMXj2qh+9ttIszF6IkIhtRErBxS0lkUx/CL0FRDeeCq5pED3f9VWNMHDaOSI4JtxRhU58hrrA6zOu6VA+vLLJL1mYUJw/Y6CMs3mFFndaSy4TlbC5eopoCRcPGn2DpT+YGx8wjTl2djin72Gavsj1XZdjVUFNq4XkiGE78PTw3MYVHdjOohaAb9yIuIK7TtCLs5lCnqXOSC8jhhVVIKOwGUafuzGddlJbXUirBbhK1e8F+7T9f855V0niTqN1hbd9/WOpmFWA3iPrqpWs9M7JvNi2q42E3iPo4LoEzX7bVSKNhN4T65nRMVB5sczWPYJvqWNgVUfsWjuhai0u0/YnKSNjVUNdQQ5GRPrKtT2RWt94FiINdETW1rvbdjL1yVs0cquMWaNVQn/qqcvcbOb3byTpqzq6IupYaymhMgHZ5eNRqvBrqemooex57NsfdvfDIfoRqbsH76ppqKKR/u+I6BvY6lF1ec7GXUK7riWp7tcw4getObjjsDaA6GPW13z0eOs+7Zy13NmNnw+dsADsUtUc4Zl2uiKx3TH6U8659FdjBqMmhgg5u2a57PcQqRZz2gQ1vjCWJhr041Gxc7mzvuNWTWc79hIYPbFzXVpG9UNRp6kx8VVl7wcZZOYO9UNSEanrqIp7GiYW9YNRuD7cWjnRmoJ5B8onsEtgLRj12efhLOi9SquNgLxi1u1xGT1z0LxajYC8Y9avIcpm3h3ttvfJpfMGo3bUHOpe51+HSNBGwF4vaLdpny0V7uBfs+Zdw2/GQ2arkDnflm5gCwo0zkw1HdAJ3769VFHjA3rmXf1Kl+DSDfbNF51zd4+aE2HyQj5qJj6JyOOtCp3EGFsEeBKK+PvU6TsbONMZTnE9Q+6mmI5uBzcEOQl3Xxtr5sBVyKA/WJOzn/CElBPsxemzJ/RRKmtZUQxkO6QW41+4j60RFNg9qDBsVVZwPHKVpbag93dvTw0nYQjGCDYsqFGp3UvYuK+3SJRT/VYrXnO2ETaAmHrLwVe1RDzah7RPXdBp3waZQ13KX57XXNB3ImkzjDtgE6hqeLtsdh2n2mq+5hag52w6bQl3xLs9obxwQzwq2n4fTq3ErbAp16rVAsXUKhRwyc1WATaEOWI3W2NWXdWxkk6hr03I+VceM/Exv1T6RvYW2VXzObhB1r6MOUrTP7sN/gXZZqI03hzrVont1qqZhb+W2WhtNop5q1f06VdORzUooeK+13iDqvlY9rVV1EfbHz2Ex4Qf2bf+ilm/599dTQiGVrAaEdUBcl8zZW9+gEgpnjVsY61pKKKTmNNUZ/Myjs3cOL5mzGXyEVsQ1amEpvCHUHlJBlxDV+chm2+4cbBbWOdhW1Psr2iWz//T653Jg0zuFc6xLugYuULE7kz17Jpd16madW42/4O6N0IqiCoZtQT0zYQjFCzGTvDnk32kKrsny9LlsYn/pa7o+1INYY9ifcdUYLW/5GsV6oagiBmUR3Vlh587LRbOBAqC8Y5oedHnfLrOVuehO/apxGhf6EFqx9UJCC0UVMSgzzXRWez3A8CAFjrzSyw7RgV12xxhkn5lOunyPL0AP9Bl2gj7CWGPYQl4AbPMbPx23XbFmPoMrDMGPH4jaGVdzrs91urMz+SHS140Zs9zgVh6ougps8xM/449rYnTm7z5w426vnx0TtZ+APiI0c+/mh3YX6frUEai6CmyD2uQr5tHsMH9PoPMbf+6qxJxL7sK72THTXaUZqSNUdTxs8GtOMwcdiMWFidfz1GydYFZTCE38Gu9mEk3zhBIszoeqjocNfrlbNh0LjUycJYOrCRoYRXk3U2HM6BXW4apjYQPUtrmp02H+aVGtGIJrZSYUhzajX1iHq46FDVDvW5R1OkybSdOol95IgYSmYzifHUkvD/bw3AKNz9nP0bqEz9n4PpcuqmSjMWGsJmT1L/NPuO4EujVXeF7luHx2XIDqIuxL/Fg4U/0ut8/GL1/QOMuqHuXLttVMyBlayarrjSXokllcNsvXxr9ibwXCsX6PlVDwowq8xRxoTVKCZdJX6I23Z+vuSc7/s2jXYa2MQ8EO9/A0HUCP3uA/pUew7/F3JCDY6+gNOmY5TU0zxnNFWGdL0L6RvlJc2lGCY1nj16Xc5w8cIdjrHKzjzQuG9eq+rgVMp5M+W3SurfUnuu0Ar7jkErSLFjLCB0x2lIse+ohhjWBviLflQNibIl0j2PgtG7bp2p7BuTipbZVlsBnwcz4/m1j3DOuImYt9DXw3zo/CshD2d6IFwv4eWx/sGPPrr/I5jYXrTCb+NaELLsfh3sUboXdHNHAQ2R/kCVFikMdb2fKbafkr53MHpbQZULOehvKZ1Km8IksEsNsanOto35Y94lTP7ytJf6gv0k9obKsW3een4mCmajsF/mXOel7SzBPZTLbr7LcPLwP/X6iHM/99LGR/8acWNH8qDfGLaclWLz/7EmiuXxxrNr6/tx8++RW9gO6fF4+e/P4RDv3D9sOn26ilOWHOb4pW/T8Zf9wwWtVxdlvGq1rWy0gtbswt6zi7LeNVLetlpBY35pZ1nN2W8aqW9TJSixtzyzrObst4Vct6GanFjbllHWe3ZbyqZb2M1OLG3LKOs9syXvUfsRAYAYU9M44AAAAASUVORK5CYII="
width="170" height="90"  />
</div>
<script type="text/javascript">
function w3e_showinfo(w3eLink){
    var p = confirm("Do you want to see state of servers?");

    if(!p){
        w3eLink.href += "&noserverstates=1";
    }
    return true;
}
function w3e_showlatestversion(w3eLink){
    var p = confirm("Do you want to check latest version?");

    if(!p){
        w3eLink.href += "&dontcheckversion=1";
    }
    return true;
}
</script>
<div id="navcontainer" style="margin-bottom: 30px;">
<ul id="navlist">
<li><a href="' . W3E_MOD_LINK . '&view=home">Home</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=allvm">ALL VM</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=quick">Quick Insert</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=log">Logs</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=servers" onclick="return w3e_showinfo(this);">Servers</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=settings" onclick="return w3e_showlatestversion(this);">Settings</a></li>
<li><a href="' . W3E_MOD_LINK . '&view=support">Support</a></li>
</ul>
</div>
';
        return $menu;
    }
    private function renderFooter()
    {
        $footer = '
<div style="float:left; background-color: #ECECEC; border-top: 1px solid #DDD; text-align: center; padding: 15px; width: 100%; margin:20px 0;">
Powered by W3ESXi
</div>
        ';
        return $footer;
    }
    private function renderErrors()
    {
        $errors = null;
        $warnings = null;
        $successes = null;
        if (isset($_SESSION['W3E_MESSAGES'])) {
            foreach ($_SESSION['W3E_MESSAGES']['error'] as $error) {

                $errors .= "<li><strong>ERROR:</strong> $error</li>\n";
            }
            foreach ($_SESSION['W3E_MESSAGES']['warning'] as $warning) {

                $warnings .= "<li><strong>WARNING:</strong> $warning</li>\n";
            }
            foreach ($_SESSION['W3E_MESSAGES']['success'] as $success) {

                $successes .= "<li>$success</li>\n";
            }
            $_SESSION['W3E_MESSAGES'] = array();
            unset($_SESSION['W3E_MESSAGES']);
        }
        $html = '<div id="W3EMessage">';
        if ($errors != null) {
            $html .= '<ul class="error">' . $errors . '</ul>';
        }
        if ($warnings != null) {
            $html .= '<ul class="warning">' . $warnings . '</ul>';
        }
        if ($successes != null) {
            $html .= '<ul class="success">' . $successes . '</ul>';
        }

        $html .= '
        {W3E_MESSAGES}
        </div>';
        return $html;
    }
    private function addNewErrors()
    {
        $errors = null;
        $warnings = null;
        $successes = null;
        if (isset($_SESSION['W3E_MESSAGES'])) {
            foreach ($_SESSION['W3E_MESSAGES']['error'] as $error) {
                $errors .= "<li><strong>ERROR:</strong> $error</li>\n";
            }
            foreach ($_SESSION['W3E_MESSAGES']['warning'] as $warning) {
                $warnings .= "<li><strong>WARNING:</strong> $warning</li>\n";
            }
            foreach ($_SESSION['W3E_MESSAGES']['success'] as $success) {
                $successes .= "<li>$success</li>\n";
            }
            $_SESSION['W3E_MESSAGES'] = array();
            unset($_SESSION['W3E_MESSAGES']);
        }
        $html = '';
        if ($errors != null) {
            $html .= '<ul class="error">' . $errors . '</ul>';
        }
        if ($warnings != null) {
            $html .= '<ul class="warning">' . $warnings . '</ul>';
        }
        if ($successes != null) {
            $html .= '<ul class="success">' . $successes . '</ul>';
        }
        $this->output = str_replace('{W3E_MESSAGES}', $html, $this->output);
    }

    private function renderPagination($view)
    {
        /*
        $_SESSION['W3ELOGS']['query']
        $_SESSION['W3ELOGS']['pagination_query']
        $_SESSION['W3ELOGS']['items']
         */

        $page = 1;
        if (isset($_REQUEST['page']) && intval($_REQUEST['page']) > 1) {
            $page = intval($_REQUEST['page']);
        }
        $formLink = W3E_MOD_LINK . "&view=$view&filter=1";
        $sessionKey = array(
            'log' => 'W3ELOGS',
            'allvm' => 'W3EALLVM',
            'quick' => 'W3EQUICK',
        );
        $query = $_SESSION[$sessionKey[$view]]['pagination_query'];
        $item = $_SESSION[$sessionKey[$view]]['items'];
        $result = @mysql_query($query);
        if (!$result) {
            $this->setErrors('Cannot get number of items from DB: ' . mysql_error(), 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=' . $view);
            exit();
        }
        $html = null;
        $fetch = mysql_fetch_assoc($result);
        $total = $fetch['total'];
        $pages = ceil($total / $item);

        $html .= ' <div id="w3e_pagination">
                 <form action="' . $formLink . '" method="GET">
              <input type="hidden" name="module" value="w3esxi_admin" />
              <input type="hidden" name="view" value="' . $view . '" />
              <input type="hidden" name="filter" value="1" />
              <select id="w3e_page_manu" name="page" >';
        for ($i = 1; $i <= $pages; $i++) {
            $html .= '<option value="' . $i . '"';
            if ($i == $page) {
                $html .= ' selected="selected"';
            }
            $html .= '>' . $i . '</option>';
        }

        $html .= '</select>
              <input id="w3e_page_submit" type="submit" value="GO" />
              </form>
           </div>     ';
        return $html;
    }
//RENDER FILTER
    private function renderFilter($view)
    {
        $html = null;
        $allvmItemsArr = array(1, 3, 5, 7, 10, 12, 15);
        $quickInsertItemsArr = array(1, 3, 5, 7, 10, 15, 20, 25, 30, 35, 40, 45, 50);
        $logsAdminArr = array(1, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100);
        //ALLVM
        if ($view == 'allvm') {
            $defaultItem = $this->getSettings('allvm_items_per_page');

            $html .= '
<div id="allVmFilter">
<h3>AllVM</h3>
  <form action="' . W3E_MOD_LINK . '&view=allvm&filter=1" method="POST">
    <h4 class="w3e">Filter</h4>
    <div id="server">
        <fieldset>
            <legend>By Server</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EServers[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EServers[]\')" value="R" />
            </p>
              <select name="W3EServers[]" multiple="multiple">
        	  <!-- Server Items -->
        	  ' . $this->getAllServers(true) . '
              </select>
        </fieldset>
    </div>
    <div id="product">
        <fieldset>
            <legend>By Product</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items" onClick="deselect(\'W3EProducts[]\')" value="X" />
             <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EProducts[]\')" value="R" />
            </p>
              <select name="W3EProducts[]" multiple="multiple">
        	  <!-- Product Items -->
        	  ' . $this->getAllProducts(true) . '
              </select>
        </fieldset>
    </div>
    <div id="client">
        <fieldset>
            <legend>By Client</legend>

            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EClients[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EClients[]\')" value="R" />
            </p>
              <select name="W3EClients[]" multiple="multiple">
                <!-- Client Items -->
        		' . $this->getAllClients(true) . '
              </select>
        </fieldset>
    </div>
    <div id="other">
        <fieldset>
          <legend>Other</legend>
          <p> IP:
            <input id="ip" name="W3EIP" />
          </p>
          <p> VMID:
            <input id="vmid" name="W3EVMID" />
          </p>
          <p> Items:
            <select name="W3EItems">';
            foreach ($allvmItemsArr as $item) {
                $html .= "<option value=\"$item\" ";
                if ($item == $defaultItem) {
                    $html .= 'selected="selected"';
                }
                $html .= ">$item</option>";
            }

            $html .= '</select>
          </p>
      </fieldset>
    </div>

    <div id="sort">
        <fieldset>
          <legend>Sort</legend>
          <p> sort #1:
            <select name="W3ESort1">
              <option value="serverASC" selected="selected">By Server ASC</option>
              <option value="serverDESC">By Server DESC</option>
              <option value="productASC">By Product ASC</option>
              <option value="productDESC">By Product DESC</option>
            </select>
          </p>
          <p> sort #2:
            <select name="W3ESort2">
              <option value="vmidASC">By VMID ASC</option>
              <option value="vmidDESC">By VMID DESC</option>
              <option value="clientLastNameASC" selected="selected">By Client Lastname ASC</option>
              <option value="clientLastNameDESC">By Client Lastname DESC</option>
              <option value="prdASC">By Product Registered Date ASC</option>
              <option value="prdDESC">By Product Registered Date DESC</option>
              <option value="crdASC">By Client Registered Date ASC</option>
              <option value="crdDESC">By Client Registered Date DESC</option>
            </select>
          </p>
        </fieldset>
    </div>
    <input id="submitbutton" type="submit" value="SHOW" />
    <input type="hidden" name="view" value="allvm" />
    <input type="hidden" name="filter" value="1" />
  </form>
</div>
<script type="text/javascript">
function deselect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		menu[i].selected = false;
	}
}
function reverseSelect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		if(menu[i].selected){
		   menu[i].selected = false;
        }else{
           menu[i].selected = true;
        }

	}
}
</script>
';

            /* Quick Insert */
        } else if ($view == 'quickinsert') {
            $defaultItem = $this->getSettings('quick_insert_items_per_page');
            $html .= '<div id="qInsertFilter">
<h3>Quick Insert</h3>
  <form action="' . W3E_MOD_LINK . '&view=quick&filter=1" method="POST">
    <h4 class="w3e">Filter</h4>
    <div id="server">
        <fieldset>
            <legend>By Server</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EServers[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EServers[]\')" value="R" />
            </p>
              <select name="W3EServers[]" multiple="multiple">
        	  <!-- Server Items -->
        	  ' . $this->getAllServers(true) . '
              </select>
        </fieldset>
    </div>
    <div id="product">
        <fieldset>
            <legend>By Product</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items" onClick="deselect(\'W3EProducts[]\')" value="X" />
             <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EProducts[]\')" value="R" />
            </p>
              <select name="W3EProducts[]" multiple="multiple">
        	  <!-- Product Items -->
        	  ' . $this->getAllProducts(true) . '
              </select>
        </fieldset>
    </div>
    <div id="client">
        <fieldset>
            <legend>By Client</legend>

            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EClients[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EClients[]\')" value="R" />
            </p>
              <select name="W3EClients[]" multiple="multiple">
                <!-- Client Items -->
        		' . $this->getAllClients(true, false) . '
              </select>
        </fieldset>
    </div>
    <div id="other">
        <fieldset>
          <legend>Other</legend>
          <p> Service IP:
            <input type="text" id="ip" name="W3EIP" />
          </p>
          <p> VMID:
            <input type="text" id="vmid" name="W3EVMID" />
          </p>
          <p> Items:
            <select name="W3EItems">';
            foreach ($quickInsertItemsArr as $item) {
                $html .= "<option value=\"$item\" ";
                if ($item == $defaultItem) {
                    $html .= 'selected="selected"';
                }
                $html .= ">$item</option>";
            }

            $html .= '</select>
          </p>
      </fieldset>
    </div>
      <div id="sort">
        <fieldset>
          <legend>Sort</legend>
          <p> sort #1:
            <select name="W3ESort1">
              <option value="serverASC" selected="selected">By Server ASC</option>
              <option value="serverDESC">By Server DESC</option>
              <option value="productASC">By Product ASC</option>
              <option value="productDESC">By Product DESC</option>
            </select>
          </p>
          <p> sort #2:
            <select name="W3ESort2">
              <option value="vmidASC">By VMID ASC</option>
              <option value="vmidDESC">By VMID DESC</option>
              <option value="clientLastNameASC" selected="selected">By Client Lastname ASC</option>
              <option value="clientLastNameDESC">By Client Lastname DESC</option>
              <option value="prdASC">By Product Registered Date ASC</option>
              <option value="prdDESC">By Product Registered Date DESC</option>
              <option value="crdASC">By Client Registered Date ASC</option>
              <option value="crdDESC">By Client Registered Date DESC</option>
            </select>
          </p>
        </fieldset>
    </div>
    <div id="qinsert">
        <fieldset>
          <legend>Quick Insert</legend>

          <p>

            <input type="checkbox" id="W3EIPSET" name="W3EIPSET" />
            <label for="W3EIPSET">Just without IP</label>
            <br />
            <input type="checkbox" id="W3EVMIDSET" name="W3EVMIDSET" />
            <label for="W3EVMIDSET">Just without VMID </label>
          </p>
      </fieldset>

    </div>
    <input id="submitbutton" type="submit" value="SHOW" />
    <input type="hidden" name="view" value="quick" />
    <input type="hidden" name="filter" value="1" />
  </form>
</div>
<script type="text/javascript">
function deselect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		menu[i].selected = false;
	}
}
function reverseSelect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		if(menu[i].selected){
		   menu[i].selected = false;
        }else{
           menu[i].selected = true;
        }

	}
}
</script>';

            /* LOGS */
        } else if ($view == 'logs') {
            $defaultItem = $this->getSettings('admin_logs_items_per_page');

            $html .= '<div id="logFilter">
<h3>Logs</h3>
  <form action="' . W3E_MOD_LINK . '&view=log&filter=1" method="POST">
    <h4 class="w3e">Filter</h4>
    <div id="server">
        <fieldset>
            <legend>By Server</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EServers[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EServers[]\')" value="R" />
            </p>
              <select name="W3EServers[]" multiple="multiple">
        	  <!-- Server Items -->
        	  ' . $this->getAllServersForLogs(true) . '
              </select>
        </fieldset>
    </div>
    <div id="product">
        <fieldset>
            <legend>By Product</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items" onClick="deselect(\'W3EProducts[]\')" value="X" />
             <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EProducts[]\')" value="R" />
            </p>
              <select name="W3EProducts[]" multiple="multiple">
        	  <!-- Product Items -->
        	  ' . $this->getAllProducts(true) . '
              </select>
        </fieldset>
    </div>
    <div id="client">
        <fieldset>
            <legend>By Client</legend>

            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EClients[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EClients[]\')" value="R" />
            </p>
              <select name="W3EClients[]" multiple="multiple">
                <!-- Client Items -->
        		' . $this->getAllClients(true, false) . '
              </select>
        </fieldset>
    </div>
    <div id="other">
        <fieldset>
          <legend>Other</legend>
          <p> Service IP:
            <input type="text" id="ip" name="W3EIP" />
          </p>
          <p> VMID:
            <input type="text" id="vmid" name="W3EVMID" />
          </p>
          <p> Items:
            <select name="W3EItems">';
            foreach ($logsAdminArr as $item) {
                $html .= "<option value=\"$item\" ";
                if ($item == $defaultItem) {
                    $html .= 'selected="selected"';
                }
                $html .= ">$item</option>";
            }

            $html .= '</select>
          </p>
      </fieldset>
    </div>

    <div id="logs">
        <fieldset>
          <legend>Logs</legend>
          <p> Client IP:
            <input type="text" id="clientip" name="W3ECLIENTIP" />
          </p>
          <p> By:

            <input type="checkbox" checked="checked" id="W3EBYA" name="W3EBYADMIN" />
            <label for="W3EBYA">Admin</label>
            <input type="checkbox" checked="checked" id="W3EBYC" name="W3EBYCLIENT" />
            <label for="W3EBYC">Client</label>
          </p>
          <p> Action:
            <select name="W3EAction">
            <option value="all" selected="selected">All</option>
              <option value="poweron">Power ON</option>
              <option value="poweroff">Power OFF</option>
              <option value="reset">Reset</option>
              <option value="rebootos">Reboot OS</option>
              <option value="shutdownos">Shutdown OS</option>
              <option value="suspend">Suspend</option>
              <option value="unsuspend">Unsuspend</option>
            </select>
          </p>
          <p> Date: <br />

          From <input id="w3e_date1" type="text" class="datepick" name="W3EDateFROM" value="00/00/0000" /> To
            <input id="w3e_date2" type="text" class="datepick" name="W3EDateTO" value="' . strftime('%d/%m/%Y') . '" />


          </p>
      </fieldset>


    </div>
    <input id="submitbutton" type="submit" value="SHOW" />
    <input type="hidden" name="view" value="log" />
    <input type="hidden" name="filter" value="1" />
  </form>
</div>
<script type="text/javascript">
function deselect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		menu[i].selected = false;
	}
}
function reverseSelect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		if(menu[i].selected){
		   menu[i].selected = false;
        }else{
           menu[i].selected = true;
        }

	}
}
</script>';

        } else if ($view == 'removeVMID') {
            $html .= '
            <h4 class="w3e">Remove VMID & Logs</h4>
            <div id="removeVMID">

  <form action="' . W3E_MOD_LINK . '&view=servers&action=removevmid&filter=1" method="POST">
    <div id="server">
        <fieldset>
            <legend>By Server</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EServers[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EServers[]\')" value="R" />
            </p>
              <select name="W3EServers[]" multiple="multiple">
        	  <!-- Server Items -->
        	  ' . $this->getAllServerForRemoving(true) . '
              </select>
        </fieldset>
    </div>
    <div id="product">
        <fieldset>
            <legend>By Product</legend>
            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items" onClick="deselect(\'W3EProducts[]\')" value="X" />
             <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EProducts[]\')" value="R" />
            </p>
              <select name="W3EProducts[]" multiple="multiple">
        	  <!-- Product Items -->
        	  ' . $this->getAllProducts(true) . '
              </select>
        </fieldset>
    </div>
    <div id="client">
        <fieldset>
            <legend>By Client</legend>

            <input type="button" id="clearButton" alt="Deselect all items" title="Deselect all items"  onClick="deselect(\'W3EClients[]\')" value="X" />
            <input type="button" id="reverseButton" alt="Reverse selections" title="Reverse selections" onClick="reverseSelect(\'W3EClients[]\')" value="R" />
            </p>
              <select name="W3EClients[]" multiple="multiple">
                <!-- Client Items -->
        		' . $this->getAllClientsForRemoving(true) . '
              </select>
        </fieldset>
    </div>

      <div id="other">
        <fieldset>
          <legend>Other</legend>
          <p> IP:
            <input id="ip" name="W3EIP" />
          </p>
          <p> VMID:
            <input id="vmid" name="W3EVMID" />
          </p>

      </fieldset>
    </div>
    <div id="logs">
        <fieldset>
          <legend>Remove VMID & Logs</legend>
          <p> Date (Only for logs): <br />

          From <input id="w3e_date1" type="text" class="datepick" name="W3EDateFROM" value="00/00/0000" /> To
            <input id="w3e_date2" type="text" class="datepick" name="W3EDateTO" value="' . strftime('%d/%m/%Y') . '" />


          </p>
          <p>

            <input type="radio" id="W3E_Remove_VMID_LOGS" name="W3E_Remove" value="W3E_Remove_VMID_LOGS" checked="checked" />
            <label for="W3E_Remove_VMID_LOGS">Remove VMID & Logs</label>
            <br />
            <input type="radio" id="W3E_Remove_Logs"
            name="W3E_Remove"
            value="W3E_Remove_Logs" />
            <label for="W3E_Remove_Logs">Remove Logs </label>
          </p>
      </fieldset>


    </div>
    <input id="submitbutton" type="submit" value="Remove" onclick="return confirm(\'Are you sure? It can remove all records in database.\');" />
    <input type="hidden" name="action" value="removevmid" />
    <input type="hidden" name="view" value="servers" />
    <input type="hidden" name="filter" value="1" />
  </form>
</div>
<script type="text/javascript">
function deselect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		menu[i].selected = false;
	}
}
function reverseSelect(menuName){

	var menu = document.getElementsByName(menuName)[0].options;

	for(i = 0;i < menu.length;i++){
		if(menu[i].selected){
		   menu[i].selected = false;
        }else{
           menu[i].selected = true;
        }

	}
}
</script>';
        }
        return $html;
    }

//PROCESS (Control Methods)
    private function processAllVM()
    {
        if (isset($_SESSION['W3EALLVM'])) {
            $_SESSION['W3EALLVM'] = array();
            unset($_SESSION['W3EALLVM']);
        }
        $formElementsArr = array(
            'W3ESort1',
            'W3ESort2',
            'W3EItems',
            'view',
            'filter',
            'W3EVMID',
            'W3EIP',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with filter form. Please go back to the AllVM page and try again.', 'ERROR');

            }
        }
        //SORT #1
        $sort1MapArr = array(
            'productASC' => 'tblproducts.name ASC',
            'productDESC' => 'tblproducts.name DESC',
        );

        $sort = 'ORDER BY tblservers.name ';
        if ($_POST['W3ESort1'] == 'serverDESC') {
            $sort .= 'DESC';
        } else {
            $sort .= 'ASC';
        }
        if (array_key_exists($_POST['W3ESort1'], $sort1MapArr)) {
            $sort .= ',' . $sort1MapArr[$_POST['W3ESort1']];
        }

        //SORT #2
        $sort2MapArr = array(
            'vmidASC' => 'mod_w3esxi.vmid ASC',
            'vmidDESC' => 'mod_w3esxi.vmid DESC',
            'clientLastNameASC' => 'tblclients.lastname ASC',
            'clientLastNameDESC' => 'tblclients.lastname DESC',
            'prdASC' => 'tblhosting.regdate ASC',
            'prdDESC' => 'tblhosting.regdate DESC',
            'crdASC' => 'tblclients.datecreated ASC',
            'crdDESC' => 'tblclients.datecreated DESC',
        );
        if (array_key_exists($_POST['W3ESort2'], $sort2MapArr)) {
            $sort .= ',' . $sort2MapArr[$_POST['W3ESort2']];
        }

        //Items
        $item = $this->getSettings('allvm_items_per_page');
        $itemArr = array(1, 3, 5, 7, 10, 12, 15);
        if (in_array($_POST['W3EItems'], $itemArr)) {
            $item = $_POST['W3EItems'];
        }

        //IP
        $ip = mysql_real_escape_String(trim($_POST['W3EIP']));

        //VMID
        $vmid = trim($_POST['W3EVMID']);
        if ($vmid != null) {
            $vmidArr = explode(',', $vmid);
            for ($i = 0; $i < count($vmidArr); $i++) {
                $vmidArr[$i] = intval($vmidArr[$i]);
            }
            $vmid = implode(',', $vmidArr);
        }

        //Clients
        if (isset($_POST['W3EClients'])) {
            for ($i = 0; $i < count($_POST['W3EClients']); $i++) {
                $_POST['W3EClients'][$i] = intval($_POST['W3EClients'][$i]);
            }
            $client = implode(',', $_POST['W3EClients']);
        } else {
            $client = null;
        }
        //Products
        if (isset($_POST['W3EProducts'])) {
            for ($i = 0; $i < count($_POST['W3EProducts']); $i++) {
                $_POST['W3EProducts'][$i] = intval($_POST['W3EProducts'][$i]);
            }
            $product = implode(',', $_POST['W3EProducts']);
        } else {
            $product = null;
        }
        //Servers
        if (isset($_POST['W3EServers'])) {
            for ($i = 0; $i < count($_POST['W3EServers']); $i++) {
                $_POST['W3EServers'][$i] = intval($_POST['W3EServers'][$i]);
            }
            $server = implode(',', $_POST['W3EServers']);
        } else {
            $server = null;
        }
        $fields = "mod_w3esxi.vmid,mod_w3esxi.id w3eid,
tblclients.firstname,tblclients.lastname,tblclients.id clientid,
tblhosting.regdate,tblhosting.dedicatedip,tblhosting.id serviceid,
tblproducts.name productname,tblproducts.configoption1 os,tblproducts.configoption2 otheros,tblproducts.id pid,
tblservers.id serverid,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password ";
        $query = "
SELECT
{W3E_FIELDS}
FROM mod_w3esxi,tblclients,tblservers,tblhosting,tblproducts
WHERE
tblclients.id = mod_w3esxi.clientid
AND
tblhosting.id = mod_w3esxi.serviceid
AND
tblproducts.id = mod_w3esxi.pid
AND
tblservers.id = mod_w3esxi.serverid
AND
tblservers.type = 'w3esxi'
AND
tblservers.disabled = 0
AND
tblclients.status = 'Active'
        ";
        if ($client != null) {
            $query .= "
AND
tblclients.id IN ($client) ";

        }

        if ($server != null) {
            $query .= "
AND
tblservers.id IN ($server) ";

        }
        if ($product != null) {
            $query .= "
AND
tblproducts.id IN ($product) ";

        }
        if ($vmid != null) {
            $query .= "
AND
mod_w3esxi.vmid IN ($vmid) ";

        }

        if ($ip != null) {
            $query .= "AND tblhosting.dedicatedip = '$ip' ";
        }

        $query .= $sort;

        $_SESSION['W3EALLVM'] = array();
        $_SESSION['W3EALLVM']['query'] = str_replace('{W3E_FIELDS}', $fields, $query);
        $_SESSION['W3EALLVM']['pagination_query'] = str_replace('{W3E_FIELDS}', 'count(*) total', $query);
        $_SESSION['W3EALLVM']['items'] = $item;

        $html = $this->renderAllVmTable();
        $html .= $this->renderPagination('allvm');
        return $html;

    }
    private function processAllVmCommand()
    {

        if (!isset($_SESSION['W3EALLVM'])) {
            $this->setErrors('Filter form is not filled.', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        }

        $formElementsArr = array(
            'page',
            'back',
            'filter',
            'view',
            'action',
            'W3E_ACTION',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with saving form. Please go back to the AllVM page and try again.', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=allvm');
                exit();
            }
        }

        $actionArr = array(
            'poweron',
            'poweroff',
            'reset',
            'rebootos',
            'shutdownos',
            'suspend',
            'unsuspend',
        );
        $_POST['W3E_ACTION'] = trim($_POST['W3E_ACTION']);
        if (!in_array($_POST['W3E_ACTION'], $actionArr)) {
            $this->setErrors('Your action is not exist', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        }

        $location = 'Location: ' . W3E_MOD_LINK . '&view=allvm';
        if ($_POST['back'] && intval($_POST['page']) > 0) {
            $location .= '&filter=1&page=' . intval($_POST['page']);
        }

        if (!isset($_POST['W3EServices']) || !count($_POST['W3EServices'])) {
            $this->setErrors('No item is selected.', 'WARNING');
            header($location);
            exit();
        }
        //Services
        for ($i = 0; $i < count($_POST['W3EServices']); $i++) {
            $_POST['W3EServices'][$i] = intval($_POST['W3EServices'][$i]);

        }
        $serviceIdArr = implode(',', $_POST['W3EServices']);

        $query = "
SELECT
mod_w3esxi.vmid,mod_w3esxi.id w3eid,mod_w3esxi.serviceid,
tblservers.id serverid,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password
FROM mod_w3esxi,tblservers
WHERE
mod_w3esxi.serviceid IN ($serviceIdArr)
AND
tblservers.id = mod_w3esxi.serverid
AND
tblservers.type = 'w3esxi'
AND
tblservers.disabled = 0
ORDER BY tblservers.name ASC
        ";

        $dbresult = @mysql_query($query);
        if (!$dbresult) {
            $this->setErrors("Cannot get VMIDs from DB: " . mysql_error(), 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=allvm');
            exit();
        }
        if (!mysql_num_rows($dbresult)) {
            $this->setErrors("There is no item to change power state", 'ERROR');
            header($location);
            exit();
        }

        $vmware = new VMware();
        while ($row = mysql_fetch_assoc($dbresult)) {

            //Connect to vmware server
            $host = $row['ipaddress'];
            $user = $row['username'];
            $pass = decrypt($row['password']);
            $vmware->setServerConfig($host, $user, $pass);
            $result = null;
            switch ($_POST['W3E_ACTION']) {
                case 'poweron':
                    $result = $vmware->powerON($row['vmid']);
                    break;
                case 'poweroff':
                    $result = $vmware->powerOFF($row['vmid']);
                    break;
                case 'reset':
                    $result = $vmware->reset($row['vmid']);
                    break;
                case 'rebootos':
                    $result = $vmware->rebootOS($row['vmid']);
                    break;
                case 'shutdownos':
                    $result = $vmware->shutdownOS($row['vmid']);
                    break;
                case 'suspend':
                    $result = $vmware->suspend($row['vmid']);
                    break;
                case 'unsuspend':
                    $result = $vmware->unSuspend($row['vmid']);
                    break;
            }

            if (!$result) {
                $this->setErrors("Cannot {$_POST['W3E_ACTION']} VMID {$row['vmid']} from server {$row['servername']}", 'WARNING');
            } else {
                //INSERT LOG:
                $userAgent = mysql_real_escape_string(trim($_SERVER["HTTP_USER_AGENT"]));
                $ip = mysql_real_escape_string(trim($_SERVER["REMOTE_ADDR"]));
                $loggedDate = strftime('%Y-%m-%d %H:%M:%S');
                $expireDate = $loggedDate;
                $action = $_POST['W3E_ACTION'];

                if ($action != 'unsuspend' && $action != 'suspend') {
                    $lockTime = $this->getSettings($action . '_lock_time');
                    $lockTime = str_replace(array('h', 's', 'm'), array(' hour', ' sec', ' min'), $lockTime);
                    $expireDate = strftime('%Y-%m-%d %H:%M:%S', strtotime('+' . $lockTime));
                }

                $insertArr = array(
                    'w3e_id' => $row['w3eid'],
                    'serviceid' => $row['serviceid'],
                    'date_logged' => $loggedDate,
                    'locking_date_expired' => $expireDate,
                    'client_ip' => $ip,
                    'user_agent' => $userAgent,
                    'command_by' => 'admin',
                    'action' => $action,
                );

                insert_query('mod_w3esxi_logs', $insertArr);
                //suspend product in DB
                if ($_POST['W3E_ACTION'] == 'suspend') {
                    update_query("tblhosting", array(
                        "domainstatus" => "Suspended",
                    ), array("id" => $row['serviceid']));
                }
                //active product in DB
                if ($_POST['W3E_ACTION'] == 'unsuspend') {
                    update_query("tblhosting", array(
                        "domainstatus" => "Active",
                    ), array("id" => $row['serviceid']));
                }

                $this->setErrors("Action '$action' is done for vmid: {$row['vmid']}", 'SUCCESS');
            }

        }

        header($location);
        exit();
    } // end of method

    private function processQuickInsert()
    {

        if (isset($_SESSION['W3EQUICK'])) {
            $_SESSION['W3EQUICK'] = array();
            unset($_SESSION['W3EQUICK']);
        }
        $formElementsArr = array(
            'W3ESort1',
            'W3ESort2',
            'W3EItems',
            'view',
            'filter',
            'W3EVMID',
            'W3EIP',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with filter form. Please go back to the Quick Insert page and try again.', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=quick');
                exit();
            }
        }
        //SORT #1
        $sort1MapArr = array(
            'productASC' => 'tblproducts.name ASC',
            'productDESC' => 'tblproducts.name DESC',
            'serverASC' => 'tblservers.name ASC',
            'serverDESC' => 'tblservers.name DESC',
        );
        $sort = ' ORDER BY ';
        if (array_key_exists($_POST['W3ESort1'], $sort1MapArr)) {
            $sort .= $sort1MapArr[$_POST['W3ESort1']];
        } else {
            $sort .= $sort1MapArr['serverASC'];
        }

        //SORT #2
        $sort2MapArr = array(
            'vmidASC' => 'mod_w3esxi.vmid ASC',
            'vmidDESC' => 'mod_w3esxi.vmid DESC',
            'clientLastNameASC' => 'tblclients.lastname ASC',
            'clientLastNameDESC' => 'tblclients.lastname DESC',
            'prdASC' => 'tblhosting.regdate ASC',
            'prdDESC' => 'tblhosting.regdate DESC',
            'crdASC' => 'tblclients.datecreated ASC',
            'crdDESC' => 'tblclients.datecreated DESC',
        );
        if (array_key_exists($_POST['W3ESort2'], $sort2MapArr)) {
            $sort .= ',' . $sort2MapArr[$_POST['W3ESort2']];
        }

        //Items
        $item = $this->getSettings('quick_insert_items_per_page');
        $quickInsertItemsArr = array(1, 3, 5, 7, 10, 15, 20, 25, 30, 35, 40, 45, 50);
        if (in_array($_POST['W3EItems'], $quickInsertItemsArr)) {
            $item = $_POST['W3EItems'];
        }

        //IP
        $ip = mysql_real_escape_String(trim($_POST['W3EIP']));

        //VMID
        $vmid = trim($_POST['W3EVMID']);
        if ($vmid != null) {
            $vmidArr = explode(',', $vmid);
            for ($i = 0; $i < count($vmidArr); $i++) {
                $vmidArr[$i] = intval($vmidArr[$i]);
            }
            $vmid = implode(',', $vmidArr);
        }

        //Clients
        if (isset($_POST['W3EClients'])) {
            for ($i = 0; $i < count($_POST['W3EClients']); $i++) {
                $_POST['W3EClients'][$i] = intval($_POST['W3EClients'][$i]);
            }
            $client = implode(',', $_POST['W3EClients']);
        } else {
            $client = null;
        }
        //Products
        if (isset($_POST['W3EProducts'])) {
            for ($i = 0; $i < count($_POST['W3EProducts']); $i++) {
                $_POST['W3EProducts'][$i] = intval($_POST['W3EProducts'][$i]);
            }
            $product = implode(',', $_POST['W3EProducts']);
        } else {
            $product = null;
        }
        //Servers
        if (isset($_POST['W3EServers'])) {
            for ($i = 0; $i < count($_POST['W3EServers']); $i++) {
                $_POST['W3EServers'][$i] = intval($_POST['W3EServers'][$i]);
            }
            $server = implode(',', $_POST['W3EServers']);
        } else {
            $server = null;
        }

        $fields = 'tblhosting.id, tblhosting.packageid,tblhosting.server,tblhosting.dedicatedip,
mod_w3esxi.vmid,mod_w3esxi.id w3eid,
tblclients.firstname,tblclients.lastname,tblclients.id clientid,
tblhosting.regdate,tblhosting.dedicatedip,tblhosting.id serviceid,
tblproducts.name productname,tblproducts.configoption1 os,configoption2 otheros,tblproducts.id pid,
tblservers.id serverid,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password ';
        $query = "
SELECT {W3E_FIELDS}
FROM tblhosting
LEFT JOIN mod_w3esxi
ON tblhosting.id = mod_w3esxi.serviceid
JOIN tblservers
ON tblhosting.server = tblservers.id
JOIN tblproducts
ON tblhosting.packageid = tblproducts.id
JOIN tblclients
ON tblhosting.userid = tblclients.id
WHERE tblservers.type = 'w3esxi' AND tblservers.disabled = 0
AND
tblproducts.servertype = 'w3esxi'
        ";
        if ($client != null) {
            $query .= "
AND
tblclients.id IN ($client) ";

        }

        if ($server != null) {
            $query .= "
AND
tblservers.id IN ($server) ";

        }
        if ($product != null) {
            $query .= "
AND
tblproducts.id IN ($product) ";

        }
        if (isset($_POST['W3EVMIDSET'])) {
            $query .= "AND mod_w3esxi.vmid IS NULL ";
        } else {
            if ($vmid != null) {
                $query .= "
    AND
    mod_w3esxi.vmid IN ($vmid) ";

            }
        }

        if (isset($_POST['W3EIPSET'])) {
            $query .= "AND tblhosting.dedicatedip = ''";
        } else {
            if ($ip != null) {
                $query .= "AND tblhosting.dedicatedip = '$ip' ";
            }
        }

        $query .= $sort;

        $vmidList = null;

        if (isset($_POST['W3EServers']) && count($_POST['W3EServers']) > 0) {
            $vmidList = $this->getVMIDlist($_POST['W3EServers']);
        } else {
            $vmidList = $this->getVMIDlist(false);
        }

        $_SESSION['W3EQUICK'] = array();
        $_SESSION['W3EQUICK']['vmidlist'] = $vmidList;
        $_SESSION['W3EQUICK']['query'] = str_replace('{W3E_FIELDS}', $fields, $query);
        $_SESSION['W3EQUICK']['pagination_query'] = str_replace('{W3E_FIELDS}', 'count(*) total', $query);
        $_SESSION['W3EQUICK']['items'] = $item;

        $html = $this->renderQuickInsertTable();
        $html .= $this->renderPagination('quick');
        return $html;
    }

    private function processQuickInsertSave()
    {

        if (!isset($_SESSION['W3EQUICK'])) {
            $this->setErrors('Filter form is not filled.', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        }

        $formElementsArr = array(
            'page',
            'back',
            'filter',
            'view',
            'action',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with saving form. Please go back to the Quick Insert page and try again.', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=quick');
                exit();
            }
        }

        $location = 'Location: ' . W3E_MOD_LINK . '&view=quick';
        if ($_POST['back'] && intval($_POST['page']) > 0) {
            $location .= '&filter=1&page=' . intval($_POST['page']);
        }

        if (!isset($_POST['W3EServices']) || !count($_POST['W3EServices'])) {
            $this->setErrors('No item is selected.', 'WARNING');
            header($location);
            exit();
        }

        foreach ($_POST['W3EServices'] as $hid) {
            $hid = intval(trim($hid));

            if (isset($_POST['W3EVMID_' . $hid])) {
                $value = trim($_POST['W3EVMID_' . $hid]);
                if ($value == '0') {
                    //DO Nothing!
                } else if ($value == '-1') {
                    //Remove
                    $query = "DELETE
                    FROM `mod_w3esxi`
                    WHERE `serviceid`='$hid'
                    ";
                    @mysql_query($query);
                    $query = "DELETE
                    FROM `mod_w3esxi_logs`
                    WHERE `serviceid`='$hid'
                    ";
                    @mysql_query($query);

                } else {

                    $value = explode('_', $value);
                    $server_id = intval($value[0]);
                    $vmid = $serverid = intval($value[1]);

                    $repeatQuery = "
                    SELECT count(id) total
                    FROM mod_w3esxi
                    WHERE mod_w3esxi.vmid = '$vmid'
                    AND
                    mod_w3esxi.serverid = '$server_id'
                    ";

                    $result = @mysql_query($repeatQuery);
                    if (!$result) {
                        $this->setErrors('Cannot check vmid is already exist - HID: ' . $hid, 'ERROR');
                    }
                    $row = mysql_fetch_assoc($result);
                    $total = $row['total'];
                    if (!$total) {
                        //query for get service (packageid+clientid)
                        $query = "SELECT
                        tblhosting.id,tblhosting.userid clientid,tblhosting.packageid pid,tblhosting.server serverid,
                        mod_w3esxi.vmid
                        FROM tblhosting
                        LEFT JOIN mod_w3esxi
                        ON mod_w3esxi.serviceid = tblhosting.id
                        WHERE
                        tblhosting.id = '$hid'
                        ";

                        $result = @mysql_query($query);
                        if (!$result || !mysql_num_rows($result)) {
                            $this->setErrors('Cannot get service info from database - HID: ' . $hid, 'ERROR');
                        } else {
                            $row = mysql_fetch_assoc($result);
                            if ($row['vmid'] == null) {
                                $insertQuery = "
                                INSERT INTO `mod_w3esxi` (`serviceid`, `vmid`, `clientid`, `serverid`, `pid`)
                                VALUES (
                                    '$hid',
                                    '$vmid',
                                    '{$row['clientid']}',
                                    '$server_id',
                                    '{$row['pid']}'
                                )
                                ";
                                @mysql_query($insertQuery);
                            } else {
                                $updateQuery = "
                                UPDATE `mod_w3esxi`
                                SET
                                    `vmid`='$vmid',
                                    `clientid`='{$row['clientid']}',
                                    `serverid`='$server_id',
                                    `pid`='{$row['pid']}'
                                WHERE `serviceid`='$hid'
                                ";

                                @mysql_query($updateQuery);
                            }
                            $updateQuery = "
                            UPDATE `tblhosting`
                            SET `server`='$server_id'
                            WHERE `id`='$hid'
                            ";
                            @mysql_query($updateQuery);
                        }
                    } else {
                        $this->setErrors("VMID: $vmid is already exist", 'WARNING');
                    }

                }

            }

            if (isset($_POST['W3EIP_' . $hid])) {
                $ip = trim($_POST['W3EIP_' . $hid]);
                $query = "UPDATE tblhosting SET tblhosting.dedicatedip = '$ip' WHERE tblhosting.id = '$hid'";
                @mysql_query($query);
            }
        }

        header($location);
        exit();

    }

    private function processServers()
    {

        $sid = intval($_REQUEST['sid']);
        if ($_REQUEST['action'] == 'command') {
            if (!isset($_REQUEST['sid'])) {
                header('Location: ' . W3E_MOD_LINK . '&view=servers');
                exit();
            }
            return $this->renderCommand();
        } else if ($_REQUEST['action'] == 'showinfo') {
            if (!isset($_REQUEST['sid'])) {
                header('Location: ' . W3E_MOD_LINK . '&view=servers');
                exit();
            }
            return $this->renderServerInfo();
        } else if ($_REQUEST['action'] == 'showsettings') {
            if (!isset($_REQUEST['sid'])) {
                header('Location: ' . W3E_MOD_LINK . '&view=servers');
                exit();
            }
            header("Location: configservers.php?action=manage&id=$sid");
            exit();
        } else if ($_REQUEST['action'] == 'removenotusedvmid') {
            $this->processRemoveNotUsedVmid();
        } else if ($_REQUEST['action'] == 'removevmid') {
            if (isset($_REQUEST['filter']) && $_REQUEST['filter'] == '1') {
                $this->processRemoveVMID();
            } else {
                $this->setErrors('Filter form is not filled', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=servers');
                exit();
            }
        } else if ($_REQUEST['action'] == 'setProducts') {
            $this->processSetModuleToProducts();
        } else {
            header('Location: ' . W3E_MOD_LINK . '&view=servers');
            exit();
        }
    }
    private function processRemoveNotUsedVmid()
    {
        //VMID
        $query = "DELETE FROM mod_w3esxi WHERE serviceid NOT IN (SELECT id FROM tblhosting)";
        if (isset($_POST['w3e_cancelled'])) {
            $query .= " OR serviceid IN (SELECT id FROM tblhosting WHERE domainstatus = 'Cancelled')";
        }
        $result = @mysql_query($query);
        if ($result) {
            $deletedItems = @mysql_affected_rows();
            if ($deletedItems > 0) {
                $this->setErrors("$deletedItems VMIDs are deleted from DB", 'SUCCESS');
            } else {
                $this->setErrors('There is no vmid for removing', 'WARNING');
            }
        } else {
            $this->setErrors("Cannot delete VMIDs from DB : " . mysql_error(), 'ERROR');
        }

        //LOGS
        $query = "DELETE FROM mod_w3esxi_logs WHERE serviceid NOT IN (SELECT id FROM tblhosting)";
        if (isset($_POST['w3e_cancelled'])) {
            $query .= " OR serviceid IN (SELECT id FROM tblhosting WHERE domainstatus = 'Cancelled')";
        }
        $result = @mysql_query($query);
        if ($result) {
            $deletedItems = @mysql_affected_rows();
            if ($deletedItems > 0) {
                $this->setErrors("$deletedItems logs are deleted from DB", 'SUCCESS');
            } else {
                $this->setErrors('There is no log for removing', 'WARNING');
            }
        } else {
            $this->setErrors("Cannot delete logs from DB : " . mysql_error(), 'ERROR');
        }
        //LOGS WITHOUT VMID

        $query = "DELETE FROM mod_w3esxi_logs WHERE w3e_id NOT IN (SELECT id FROM mod_w3esxi)";
        $result = @mysql_query($query);
        if ($result) {
            $deletedItems = @mysql_affected_rows();
            if ($deletedItems > 0) {
                $this->setErrors("$deletedItems logs are deleted from DB (#2)", 'SUCCESS');
            } else {
                $this->setErrors('There is no log for removing (#2)', 'WARNING');
            }
        } else {
            $this->setErrors("Cannot delete logs from DB (#2) : " . mysql_error(), 'ERROR');
        }

        $location = W3E_MOD_LINK . '&view=servers';

        if (isset($_REQUEST['noserverstates']) && $_REQUEST['noserverstates'] == '1') {
            $location .= '&noserverstates=1';
        }

        header('Location: ' . $location);
        exit();

    }

    private function processSetModuleToProducts()
    {
        //Products
        if (isset($_POST['W3EProductsSET'])) {
            for ($i = 0; $i < count($_POST['W3EProducts']); $i++) {
                $_POST['W3EProductsSET'][$i] = intval($_POST['W3EProductsSET'][$i]);
            }
            $product = implode(',', $_POST['W3EProductsSET']);
            $query = "UPDATE tblproducts SET
type = 'server',
servertype = 'w3esxi'
,configoption1 = 'Other'
,configoption2 = 'Other OS'
WHERE tblproducts.id IN ($product)";
            $result = @mysql_query($query);

            $updated = mysql_affected_rows();

            if ($updated > 0) {
                $this->setErrors($updated . ' items are updated', "SUCCESS");
            } else {
                $this->setErrors('No item is updated', 'WARNING');
            }

        } else {
            $this->setErrors('No Product is selected', 'WARNING');
        }
        header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
        exit();
    }

    private function processRemoveVMID()
    {

        $formElementsArr = array(
            'view',
            'filter',
            'W3EVMID',
            'W3EIP',
            'W3E_Remove',
            'W3EDateFROM',
            'W3EDateTO',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with filter form. Please go back to the Servers page and try again.', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
                exit();
            }
        }

        //Service IP
        $ip = mysql_real_escape_String(trim($_POST['W3EIP']));

        //VMID
        $vmid = trim($_POST['W3EVMID']);
        if ($vmid != null) {
            $vmidArr = explode(',', $vmid);
            for ($i = 0; $i < count($vmidArr); $i++) {
                $vmidArr[$i] = intval($vmidArr[$i]);
            }
            $vmid = implode(',', $vmidArr);
        }

        //Logs or VMID+Logs
        $removeVMID = true;
        $removeLogs = true;

        if ($_POST["W3E_Remove"] == 'W3E_Remove_Logs') {
            $removeVMID = false;
            $removeLogs = true;
        }

        //Date
        $fromDate = '0000-00-00';
        if (preg_match('@^(\d{2})\/(\d{2})\/(\d{4})$@', trim($_POST['W3EDateFROM']), $date1)) {
            $fromDate = "{$date1[3]}-{$date1[2]}-{$date1[1]}";
        }
        $fromDate .= ' 00:00:00';

        $toDate = strftime('%Y-%m-%d');
        if (preg_match('@^(\d{2})\/(\d{2})\/(\d{4})$@', trim($_POST['W3EDateTO']), $date1)) {
            $toDate = "{$date1[3]}-{$date1[2]}-{$date1[1]}";
        }
        $toDate .= ' 23:59:59';

        //Clients
        if (isset($_POST['W3EClients'])) {
            for ($i = 0; $i < count($_POST['W3EClients']); $i++) {
                $_POST['W3EClients'][$i] = intval($_POST['W3EClients'][$i]);
            }
            $client = implode(',', $_POST['W3EClients']);
        } else {
            $client = null;
        }
        //Products
        if (isset($_POST['W3EProducts'])) {
            for ($i = 0; $i < count($_POST['W3EProducts']); $i++) {
                $_POST['W3EProducts'][$i] = intval($_POST['W3EProducts'][$i]);
            }
            $product = implode(',', $_POST['W3EProducts']);
        } else {
            $product = null;
        }
        //Servers
        if (isset($_POST['W3EServers'])) {
            for ($i = 0; $i < count($_POST['W3EServers']); $i++) {
                $_POST['W3EServers'][$i] = intval($_POST['W3EServers'][$i]);
            }
            $server = implode(',', $_POST['W3EServers']);
        } else {
            $server = null;
        }

//Only Logs (mod_w3esxi_logs)
        if ($removeLogs) {
            $query = "DELETE FROM mod_w3esxi_logs
WHERE ";
            $query .= " mod_w3esxi_logs.date_logged BETWEEN '$fromDate' AND '$toDate' ";

            if ($client != null) {
                $query .= "
AND
mod_w3esxi_logs.serviceid IN ( SELECT tblhosting.id FROM tblhosting WHERE userid IN ($client) ) ";

            }

            if ($server != null) {
                $query .= "
AND
mod_w3esxi_logs.serviceid IN ( SELECT tblhosting.id FROM tblhosting WHERE server IN ($server) ) ";

            }
            if ($product != null) {
                $query .= "
AND
mod_w3esxi_logs.serviceid IN ( SELECT tblhosting.id FROM tblhosting WHERE packageid IN ($product) ) ";

            }
            if ($vmid != null) {
                $query .= "
AND
mod_w3esxi_logs.w3e_id IN ( SELECT mod_w3esxi.id FROM mod_w3esxi WHERE mod_w3esxi.vmid IN ($vmid) ) ";

            }

            if ($ip != null) {
                $query .= "
AND
mod_w3esxi_logs.serviceid IN ( SELECT tblhosting.id FROM tblhosting WHERE tblhosting.dedicatedip = '$ip') ";
            }
            $result = @mysql_query($query);
            if (!$result) {
                $this->setErrors('Cannot delete records from database: ' . mysql_error(), 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
                exit();
            }
            $affected_rows = mysql_affected_rows();
            if ($affected_rows > 0) {
                $this->setErrors("$affected_rows item(s) of Logs are deleted", 'SUCCESS');
            } else {
                $this->setErrors('There is no Log item to delete from database', 'WARNING');
            }
        }

//ONLY VMID {mod_w3esxi}
        if ($removeVMID) {

            $query = "
    DELETE
    FROM mod_w3esxi
    WHERE 1=1
    ";
            if ($client != null) {
                $query .= "
    AND
    mod_w3esxi.clientid IN ($client) ";

            }

            if ($server != null) {
                $query .= "
    AND
    mod_w3esxi.serverid IN ($server) ";

            }
            if ($product != null) {
                $query .= "
    AND
    mod_w3esxi.pid IN ($product) ";

            }
            if ($vmid != null) {
                $query .= "
    AND
    mod_w3esxi.vmid IN ($vmid) ";

            }

            if ($ip != null) {
                $query .= "
    AND
    mod_w3esxi.serviceid IN (SELECT tblhosting.id FROM tblhosting WHERE tblhosting.dedicatedip = '$ip') ";
            }

            $result = @mysql_query($query);
            if (!$result) {
                $this->setErrors('Cannot delete records from database: ' . mysql_error(), 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
                exit();
            }
            $affected_rows = mysql_affected_rows();
            if ($affected_rows > 0) {
                $this->setErrors("$affected_rows item(s) of VMIDs are deleted", 'SUCCESS');
            } else {
                $this->setErrors('There is no VMID item to delete from database', 'WARNING');
            }
        }

        header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
        exit();

    }
    private function processCommand()
    {
        if (!isset($_REQUEST['sid']) || !intval($_REQUEST['sid'])) {

            header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
            exit();
        }

        if (!isset($_POST['W3E_Command']) || !trim($_POST['W3E_Command'])) {

            return null;
        }

        $sid = intval($_REQUEST['sid']);
        $servers = $this->getAllServers(false, array($sid));

        if (count($servers) != 1) {
            $this->setErrors('server id is incorrect: ' . $sid, 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=servers&noserverstates=1');
            exit();
        }

        $s = $servers[0];
        $ip_port = explode(':', $s['ipaddress']);
        $ip = $ip_port[0];
        $port = null;
        if (!count($ip_port) != 2) {
            $port = 22;
        } else {
            $port = $ip_port[1];
        }

        $command = trim($_POST['W3E_Command']);
        $sshlibrary = $this->getSettings('ssh_library');

        $libssh = new LibSSH2($sshlibrary);
        $result = @$libssh->connect($ip, $port, true);

        if (!$result) {
            $this->setErrors('Cannot connect to server: ' . $s['servername'], 'ERROR');
            return '#ERROR: cannot connect to ssh';
        }
        $result = @$libssh->authPassword($s['username'], decrypt($s['password']), true);
        if (!$result) {
            $this->setErrors('User/Pass is incorrect for server: ' . $s['servername'], 'ERROR');
            return '#ERROR: user/pass is incorrect';

        }
        ob_implicit_flush(1);
        $result = @$libssh->cmdExec($command);
        return $result;
    }
    private function processSettings()
    {
        $issetArr = array(
            'ssh_library',
            'poweron_lock_time',
            'poweroff_lock_time',
            'reset_lock_time',
            'shutdownos_lock_time',
            'rebootos_lock_time',
            'allvm_items_per_page',
            'quick_insert_items_per_page',
            'admin_logs_items_per_page',
            'client_logs_items',

        );
        $sshlibArr = array('phpseclib', 'phpext');
        $lockMenuArr = array(
            '10s', '20s', '30s', '45s', '1m', '2m', '3m', '5m', '7m', '10m', '15m', '20m', '30m', '45m', '1h', '2h', '3h', '4h', '5h',
        );
        $allvmItemsArr = array(1, 3, 5, 7, 10, 12, 15);
        $quickInsertItemsArr = array(1, 3, 5, 7, 10, 15, 20, 25, 30, 35, 40, 45, 50);
        $logsAdminArr = array(1, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100);
        $logsClientArr = array(1, 3, 5, 7, 10, 15, 20);

        foreach ($issetArr as $element) {
            if (!isset($_POST[$element])) {
                $this->setErrors("Error in form[$element]. please try again.", 'ERROR');
                return null;
            }
        }

        $updateArr = array();

        $settings = $this->getSettings();

        //SSH_LIBRARY
        if (in_array(trim($_POST['ssh_library']), $sshlibArr)) {
            $updateArr['ssh_library'] = trim($_POST['ssh_library']);
        } else {
            $this->setErrors('SSH Library is invalid: ' . trim($_POST['ssh_library'], 'WARNING'));
        }

        //LOCK MENU
        if (in_array(trim($_POST['poweron_lock_time']), $lockMenuArr)) {
            $updateArr['poweron_lock_time'] = trim($_POST['poweron_lock_time']);
        } else {
            $this->setErrors('Lock Time is invalid: ' . trim($_POST['poweron_lock_time'], 'WARNING'));
        }

        if (in_array(trim($_POST['poweroff_lock_time']), $lockMenuArr)) {
            $updateArr['poweroff_lock_time'] = trim($_POST['poweroff_lock_time']);
        } else {
            $this->setErrors('Lock Time is invalid: ' . trim($_POST['poweroff_lock_time'], 'WARNING'));
        }

        if (in_array(trim($_POST['reset_lock_time']), $lockMenuArr)) {
            $updateArr['reset_lock_time'] = trim($_POST['reset_lock_time']);
        } else {
            $this->setErrors('Lock Time is invalid: ' . trim($_POST['reset_lock_time'], 'WARNING'));
        }

        if (in_array(trim($_POST['shutdownos_lock_time']), $lockMenuArr)) {
            $updateArr['shutdownos_lock_time'] = trim($_POST['shutdownos_lock_time']);
        } else {
            $this->setErrors('Lock Time is invalid: ' . trim($_POST['shutdownos_lock_time'], 'WARNING'));
        }

        if (in_array(trim($_POST['rebootos_lock_time']), $lockMenuArr)) {
            $updateArr['rebootos_lock_time'] = trim($_POST['rebootos_lock_time']);
        } else {
            $this->setErrors('Lock Time is invalid: ' . trim($_POST['rebootos_lock_time'], 'WARNING'));
        }

        //Default number for ALLVM
        if (in_array(intval($_POST['allvm_items_per_page']), $allvmItemsArr)) {
            $updateArr['allvm_items_per_page'] = intval($_POST['allvm_items_per_page']);
        } else {
            $this->setErrors('Default number is invalid: ' . intval($_POST['allvm_items_per_page'], 'WARNING'));
        }

        //Default number for QuickInsert
        if (in_array(intval($_POST['quick_insert_items_per_page']), $quickInsertItemsArr)) {
            $updateArr['quick_insert_items_per_page'] = intval($_POST['quick_insert_items_per_page']);
        } else {
            $this->setErrors('Default number is invalid: ' . intval($_POST['quick_insert_items_per_page'], 'WARNING'));
        }

        //Default number for Admin Logs
        if (in_array(intval($_POST['admin_logs_items_per_page']), $logsAdminArr)) {
            $updateArr['admin_logs_items_per_page'] = intval($_POST['admin_logs_items_per_page']);
        } else {
            $this->setErrors('Default number is invalid: ' . intval($_POST['admin_logs_items_per_page'], 'WARNING'));
        }

        //Default number for Client Logs
        if (in_array(intval($_POST['client_logs_items']), $logsClientArr)) {
            $updateArr['client_logs_items'] = intval($_POST['client_logs_items']);
        } else {
            $this->setErrors('Default number is invalid: ' . intval($_POST['client_logs_items'], 'WARNING'));
        }

        //CheckBoxes
        if (isset($_POST['show_info_client'])) {
            $updateArr['show_info_client'] = '1';
        } else {
            $updateArr['show_info_client'] = '0';
        }
        if (isset($_POST['only_show_power_state_to_client'])) {
            $updateArr['only_show_power_state_to_client'] = '1';
        } else {
            $updateArr['only_show_power_state_to_client'] = '0';
        }

        if (isset($_POST['show_info_admin'])) {
            $updateArr['show_info_admin'] = '1';
        } else {
            $updateArr['show_info_admin'] = '0';
        }
        if (isset($_POST['only_show_power_state_to_admin'])) {
            $updateArr['only_show_power_state_to_admin'] = '1';
        } else {
            $updateArr['only_show_power_state_to_admin'] = '0';
        }

        if (isset($_POST['show_hard_details'])) {
            $updateArr['show_hard_details'] = '1';
        } else {
            $updateArr['show_hard_details'] = '0';
        }

        if (isset($_POST['show_admin_commands_client_logs'])) {
            $updateArr['show_admin_commands_client_logs'] = '1';
        } else {
            $updateArr['show_admin_commands_client_logs'] = '0';
        }

        if (isset($_POST['force_admin_commands'])) {
            $updateArr['force_admin_commands'] = '1';
        } else {
            $updateArr['force_admin_commands'] = '0';
        }

        if (isset($_POST['auto_remove_vmid_of_deleted_services'])) {
            $updateArr['auto_remove_vmid_of_deleted_services'] = '1';
        } else {
            $updateArr['auto_remove_vmid_of_deleted_services'] = '0';
        }

        if (isset($_POST['auto_remove_also_cancelled_services'])) {
            $updateArr['auto_remove_also_cancelled_services'] = '1';
        } else {
            $updateArr['auto_remove_also_cancelled_services'] = '0';
        }

        if (isset($_POST['debug_mode'])) {
            $updateArr['debug_mode'] = '1';
        } else {
            $updateArr['debug_mode'] = '0';
        }

        //Update
        $updatedCounter = 0;
        foreach ($updateArr as $name => $value) {
            $result = update_query("mod_w3esxi_config", array('value' => $value), array('name' => $name));
            $updatedCounter += mysql_affected_rows();
        }

        if ($updatedCounter > 0) {
            if ($updatedCounter > 1) {
                $this->setErrors($updatedCounter . ' items are updated', "SUCCESS");
            } else {
                $this->setErrors($updatedCounter . ' item is updated', "SUCCESS");
            }
        } else {
            $this->setErrors('No item is updated', 'WARNING');
        }
        $location = W3E_MOD_LINK . '&view=settings';
        if (isset($_REQUEST['dontcheckversion']) && $_REQUEST['dontcheckversion'] == '1') {
            $location .= '&dontcheckversion=1';
        }
        header("Location: $location");
        exit();

    }
    private function processLogs()
    {
        if (isset($_SESSION['W3ELOGS'])) {
            $_SESSION['W3ELOGS'] = array();
            unset($_SESSION['W3ELOGS']);
        }
        $formElementsArr = array(
            'W3EItems',
            'view',
            'filter',
            'W3EVMID',
            'W3EIP',
            'W3ECLIENTIP',
            'W3EAction',
            'W3EDateFROM',
            'W3EDateTO',
        );

        foreach ($formElementsArr as $element) {
            if (!isset($element, $_POST)) {
                $this->setErrors('Problem with filter form. Please go back to the Logs page and try again.', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=log');
                exit();
            }
        }

        //Items
        $item = $this->getSettings('admin_logs_items_per_page');
        $logsAdminArr = array(1, 5, 10, 15, 20, 25, 30, 40, 50, 75, 100);
        if (in_array($_POST['W3EItems'], $logsAdminArr)) {
            $item = $_POST['W3EItems'];
        }

        //Service IP
        $ip = mysql_real_escape_String(trim($_POST['W3EIP']));
        //Client IP
        $clientip = mysql_real_escape_String(trim($_POST['W3ECLIENTIP']));

        //VMID
        $vmid = trim($_POST['W3EVMID']);
        if ($vmid != null) {
            $vmidArr = explode(',', $vmid);
            for ($i = 0; $i < count($vmidArr); $i++) {
                $vmidArr[$i] = intval($vmidArr[$i]);
            }
            $vmid = implode(',', $vmidArr);
        }
        //By
        $byclient = (isset($_POST['W3EBYCLIENT'])) ? true : false;
        $byadmin = (isset($_POST['W3EBYADMIN'])) ? true : false;

        //Action

        $action = mysql_real_escape_string(trim($_POST['W3EAction']));

        //Date
        $fromDate = '0000-00-00';
        if (preg_match('@^(\d{2})\/(\d{2})\/(\d{4})$@', trim($_POST['W3EDateFROM']), $date1)) {
            $fromDate = "{$date1[3]}-{$date1[2]}-{$date1[1]}";
        }
        $fromDate .= ' 00:00:00';

        $toDate = strftime('%Y-%m-%d');
        if (preg_match('@^(\d{2})\/(\d{2})\/(\d{4})$@', trim($_POST['W3EDateTO']), $date1)) {
            $toDate = "{$date1[3]}-{$date1[2]}-{$date1[1]}";
        }
        $toDate .= ' 23:59:59';

        //Clients
        if (isset($_POST['W3EClients'])) {
            for ($i = 0; $i < count($_POST['W3EClients']); $i++) {
                $_POST['W3EClients'][$i] = intval($_POST['W3EClients'][$i]);
            }
            $client = implode(',', $_POST['W3EClients']);
        } else {
            $client = null;
        }
        //Products
        if (isset($_POST['W3EProducts'])) {
            for ($i = 0; $i < count($_POST['W3EProducts']); $i++) {
                $_POST['W3EProducts'][$i] = intval($_POST['W3EProducts'][$i]);
            }
            $product = implode(',', $_POST['W3EProducts']);
        } else {
            $product = null;
        }
        //Servers
        if (isset($_POST['W3EServers'])) {
            for ($i = 0; $i < count($_POST['W3EServers']); $i++) {
                $_POST['W3EServers'][$i] = intval($_POST['W3EServers'][$i]);
            }
            $server = implode(',', $_POST['W3EServers']);
        } else {
            $server = null;
        }
        $fields = "mod_w3esxi.vmid,mod_w3esxi.id w3eid,
mod_w3esxi_logs.date_logged,mod_w3esxi_logs.client_ip,mod_w3esxi_logs.user_agent,mod_w3esxi_logs.command_by,mod_w3esxi_logs.action,
tblclients.firstname,tblclients.lastname,tblclients.id clientid,
tblhosting.dedicatedip,tblhosting.id serviceid,
tblproducts.name productname,tblproducts.configoption1 os,configoption2 otheros,tblproducts.id pid,
tblservers.id serverid,tblservers.name servername,tblservers.ipaddress
";
        $query = "
SELECT
{W3E_FIELDS}
FROM mod_w3esxi,tblclients,tblservers,tblhosting,tblproducts,mod_w3esxi_logs
WHERE
mod_w3esxi.id = mod_w3esxi_logs.w3e_id
AND
tblclients.id = tblhosting.userid
AND
tblhosting.id = mod_w3esxi_logs.serviceid
AND
tblproducts.id = tblhosting.packageid
AND
tblservers.id = tblhosting.server
";
        if ($client != null) {
            $query .= "
AND
tblclients.id IN ($client) ";

        }

        if ($server != null) {
            $query .= "
AND
tblservers.id IN ($server) ";

        }
        if ($product != null) {
            $query .= "
AND
tblproducts.id IN ($product) ";

        }
        if ($vmid != null) {
            $query .= "
AND
mod_w3esxi.vmid IN ($vmid) ";

        }

        if ($ip != null) {
            $query .= " AND tblhosting.dedicatedip = '$ip' ";
        }

        if ($clientip != null) {
            $query .= " AND mod_w3esxi_logs.client_ip = '$clientip' ";
        }
        if ($action != 'all') {
            $query .= " AND mod_w3esxi_logs.action = '$action' ";
        }

        $byArr = null;
        if ($byclient) {
            $byArr[] = "'client'";
        }
        if ($byadmin) {
            $byArr[] = "'admin'";
        }
        if (count($byArr) > 0) {
            $by = implode(',', $byArr);
            $query .= " AND mod_w3esxi_logs.command_by IN ($by)";
        }

        $query .= " AND mod_w3esxi_logs.date_logged BETWEEN '$fromDate' AND '$toDate' ";
        $query .= ' ORDER BY mod_w3esxi_logs.date_logged DESC ';

        $_SESSION['W3ELOGS'] = array();
        $_SESSION['W3ELOGS']['query'] = str_replace('{W3E_FIELDS}', $fields, $query);
        $_SESSION['W3ELOGS']['pagination_query'] = str_replace('{W3E_FIELDS}', 'count(*) total', $query);
        $_SESSION['W3ELOGS']['items'] = $item;

        $html = $this->renderLogsTable();
        $html .= $this->renderPagination('log');
        return $html;
    }

//Helper Methods
    private function getAllServers($makeOptions = false, $where = false)
    {

        //Query For getting all servers
        $query = "
SELECT tblservers.id,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password
FROM tblservers
WHERE type = 'w3esxi' AND disabled = 0";
        if ($where) {
            if (count($where) >= 1) {
                $query .= ' AND tblservers.id IN (';
                $query .= intval(implode(',', $where));
                $query .= ') ';
            }

        }
        $query .= ' ORDER BY tblservers.name ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $serversArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['id'] . '">' . $row['servername'] . "</option>";
                }
                return $options;
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $serversArr[] = $row;
                }
                return $serversArr;
            }

        } else {
            return null;
        }

    }

    private function getAllServersForLogs($makeOptions = false)
    {
        //Query For getting all servers
        $query = "
SELECT tblservers.id,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password
FROM tblservers
WHERE type = 'w3esxi'";

        $query .= ' ORDER BY tblservers.name ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $serversArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['id'] . '">' . $row['servername'] . "</option>";
                }
                return $options;
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $serversArr[] = $row;
                }
                return $serversArr;
            }

        } else {
            return null;
        }
    }
    private function getAllServerForRemoving($makeOptions = false)
    {
        //Query For getting all servers
        $query = "
SELECT tblservers.id,tblservers.name servername,tblservers.ipaddress,tblservers.username,tblservers.password
FROM tblservers ";

        $query .= ' ORDER BY tblservers.name ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $serversArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['id'] . '">' . $row['servername'] . "</option>";
                }
                return $options;
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $serversArr[] = $row;
                }
                return $serversArr;
            }

        } else {
            return null;
        }
    }
    private function getVMIDlist($serverIdArr)
    {
        $servers = $this->getAllServers(false, $serverIdArr);
        if (!$servers || !count($servers)) {
            $this->setErrors('There is no active server to get vmid list', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        }
        $error = false;

        $option = null;
        foreach ($servers as $s) {
            $sid = $s['id'];
            $name = $s['servername'];
            $user = $s['username'];
            $pass = decrypt($s['password']);
            $host = $s['ipaddress'];

            $option .= '<optgroup label="' . $name . '">';

            $vmware = new VMware;
            $vmware->setServerConfig($host, $user, $pass);
            $list = $vmware->getAllVmInfo();

            if (!$list || !count($list)) {
                $this->setErrors("Cannot get vmid list from server $name ($host)", 'ERROR');
                $error = true;
                continue;
            }

            foreach ($list as $vm) {
                $option .= "<option value=\"{$sid}_{$vm['vmid']}\">{$vm['vmid']} - {$vm['name']}</option>\n";
            }

            $option .= '</optgroup>';

        }
        if (!$option) {
            $this->setErrors('Cannot get vmid list from servers', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=quick');
            exit();
        }

        return $option;

    }
    private function getAllProducts($makeOptions = false, $where = false)
    {
        //Query For getting all products
        $query = "
SELECT tblproducts.id,tblproducts.name productname,tblproducts.configoption1 os
FROM tblproducts
WHERE servertype = 'w3esxi' AND type = 'server'";

        if ($where) {
            if (count($where) >= 1) {
                $query .= ' AND tblproducts.id IN (';
                $query .= intval(implode(',', $where));
                $query .= ') ';
            }

        }
        $query .= ' ORDER BY tblproducts.name ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $productsArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['id'] . '">' . $row['productname'] . "</option>";
                }
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $productsArr[] = $row;
                }
            }
            return $options;
        } else {
            return null;
        }

    }
    private function getAllProductsForSetting($makeOptions = false)
    {
        //Query For getting all products
        $query = "
SELECT tblproducts.id,tblproducts.name productname,tblproducts.configoption1 os
FROM tblproducts ";

        $query .= ' ORDER BY tblproducts.name ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $productsArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['id'] . '">' . $row['productname'] . "</option>";
                }
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $productsArr[] = $row;
                }
            }
            return $options;
        } else {
            return null;
        }

    }
    private function getAllClients($makeOptions = false, $onlyWithVMID = true, $where = false)
    {
        //Query For getting all clients (with vmid)
        $query = '';
        if ($onlyWithVMID) {
            $query = "
SELECT tblclients.id clientid,tblclients.firstname,tblclients.lastname
FROM mod_w3esxi,tblclients
WHERE tblclients.id = mod_w3esxi.clientid
";
        } else {
            $query = "
SELECT
tblclients.firstname,tblclients.lastname,tblclients.id clientid,
tblhosting.regdate,tblhosting.dedicatedip,tblhosting.id serviceid,tblhosting.packageid pid,tblhosting.server sid
FROM tblclients,tblhosting
WHERE tblhosting.packageid IN (
SELECT id
FROM tblproducts
WHERE servertype = 'w3esxi' AND type = 'server'
)
AND tblclients.id = tblhosting.userid
                ";
        }

        if ($where) {
            if (count($where) >= 1) {
                $query .= ' AND tblclients.id IN (';
                $query .= intval(implode(',', $where));
                $query .= ') ';
            }

        }
        $query .= '
             GROUP BY tblclients.id
             ORDER BY tblclients.lastname ASC';
        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $productsArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['clientid'] . '">' . $row['lastname'] . ' - ' . $row['firstname'] . '</option>';
                }
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $productsArr[] = $row;
                }
            }
            return $options;
        } else {
            return null;
        }

    }

    private function getAllClientsForRemoving($makeOptions = false)
    {
        //Query For getting all clients
        $query = "
            SELECT tblclients.id clientid,tblclients.firstname,tblclients.lastname
            FROM tblclients
            ";

        $query .= ' ORDER BY tblclients.lastname ASC';

        $result = @mysql_query($query);
        if ($result) {
            $options = '';
            $productsArr = array();

            if ($makeOptions) {
                while ($row = mysql_fetch_assoc($result)) {
                    $options .= '<option value="' . $row['clientid'] . '">' . $row['lastname'] . ' - ' . $row['firstname'] . '</option>';
                }
            } else {
                while ($row = mysql_fetch_assoc($result)) {
                    $productsArr[] = $row;
                }
            }
            return $options;
        } else {
            return null;
        }

    }
    private function check()
    {
        if (!mysql_num_rows(mysql_query("SHOW TABLES LIKE 'mod_w3esxi'")) ||
            !mysql_num_rows(mysql_query("SHOW TABLES LIKE 'mod_w3esxi_config'"))) {
            return $this->enable();
        }
        //Check Version
        if (version_compare($this->getSettings('version'), W3E_VERSION, '<')) {
            if (isset($_GET['view']) && $_GET['view'] != 'home') {
                header('Location: ' . W3E_MOD_LINK . '&view=home');
                exit();
            }

            $this->setErrors("You must first update w3esxi to " . W3E_VERSION, 'ERROR');
            return false;
        }

        $this->vmware = new VMware;

        return true;

    }
    public static function setErrors($text, $type)
    {
        if (!isset($_SESSION['W3E_MESSAGES'])) {
            $_SESSION['W3E_MESSAGES'] = array();
        }
        if (!isset($_SESSION['W3E_MESSAGES']['error'])) {
            $_SESSION['W3E_MESSAGES']['error'] = array();
        }
        if (!isset($_SESSION['W3E_MESSAGES']['warning'])) {
            $_SESSION['W3E_MESSAGES']['warning'] = array();
        }
        if (!isset($_SESSION['W3E_MESSAGES']['success'])) {
            $_SESSION['W3E_MESSAGES']['success'] = array();
        }
        if ($type == 'ERROR') {
            if (!in_array($text, $_SESSION['W3E_MESSAGES']['error'])) {
                $_SESSION['W3E_MESSAGES']['error'][] = $text;
            }
        }
        if ($type == 'WARNING') {
            if (!in_array($text, $_SESSION['W3E_MESSAGES']['warning'])) {
                $_SESSION['W3E_MESSAGES']['warning'][] = $text;
            }
        }
        if ($type == 'SUCCESS') {
            if (!in_array($text, $_SESSION['W3E_MESSAGES']['success'])) {
                $_SESSION['W3E_MESSAGES']['success'][] = $text;
            }
        }

    }

    private function getSettings($setting = '*')
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

    private function loadCSS()
    {
        $css = @file_get_contents(W3E_PATH . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'w3esxi_admin.css');
        if (!$css) {
            $this->setErrors('w3esxi_admin.css is not exist', 'ERROR');
            return null;
        }
        return '<style type="text/css">' . $css . '</style>';
    }

    private function checkSSH($lib)
    {
        if ($lib == 'phpseclib') {
            if (!class_exists('Math_BigInteger')) {
                $this->setErrors('Class Math_BigInteger is not exist ', 'ERROR');
            }
            if (!class_exists('Net_SSH2')) {
                $this->setErrors('Class Net_SSH2 is not exist ', 'ERROR');
            }
            $funcArr = array(
                'fsockopen',
                'str_pad',
                'bcmul',
                'bcadd',
                'bin2hex',
                'dechex',
                'bindec',
                'bccomp',
                'str_repeat',
                'bcsub',
                'pack',
                'unpack',
                'fputs',
                'user_error',
                'extract',
                'strtok',
                'mcrypt_generic',
                'mcrypt_generic_deinit',
                'mcrypt_generic_init',
                'mcrypt_list_algorithms',
                'mcrypt_list_modes',
                'mcrypt_module_open',
                'mcrypt_module_close',
                'mdecrypt_generic',
                'hash_algos',
                'hash_hmac',
                'call_user_func',
                'call_user_func_array',
                'chunk_split',
                'xml_parse',
                'xml_parser_create',
                'xml_set_character_data_handler',
                'xml_set_element_handler',
                'xml_set_object',

            );
            $unAvailableFuncArr = array();
            foreach ($funcArr as $func) {
                if (!function_exists($func)) {
                    $unAvailableFuncArr[] = $func;
                }
            }
            if (count($unAvailableFuncArr) > 0) {
                $this->setErrors('These functions are not exist: ' . implode(', ', $unAvailableFuncArr), 'ERROR');
            }

        } else if ($lib == 'phpext') {
            if (!extension_loaded('ssh2')) {
                $this->setErrors('Extension `lib_ssh2` is not loaded', 'ERROR');
            }

            $funcArr = array(
                'ssh2_connect',
                'ssh2_auth_password',
                'ssh2_exec',
                'stream_set_blocking',
            );
            $unAvailableFuncArr = array();
            foreach ($funcArr as $func) {
                if (!function_exists($func)) {
                    $unAvailableFuncArr[] = $func;
                }
            }
            if (count($unAvailableFuncArr) > 0) {
                $this->setErrors('These functions are not exist: ' . implode(', ', $unAvailableFuncArr), 'ERROR');
            }
        }
    }
    private function checkPHPRequirment()
    {
        $funcArr = array(
            'ob_end_clean',
            'ob_get_contents',
            'ob_implicit_flush',
            'ob_start',
            'ini_get',
        );
        $unAvailableFuncArr = array();
        foreach ($funcArr as $func) {
            if (!function_exists($func)) {
                $unAvailableFuncArr[] = $func;
            }
        }
        if (count($unAvailableFuncArr) > 0) {
            $this->setErrors('These functions are not exist: ' . implode(', ', $unAvailableFuncArr), 'ERROR');
        }
        if (version_compare(PHP_VERSION, '5.2.0', '<')) {
            $this->setErrors('You should update your php version to 5.2.0+. (Your PHP version is ' . PHP_VERSION . ')', 'ERROR');
        }
        if (function_exists('ini_get')) {
            if (!ini_get('allow_url_fopen')) {
                $this->setErrors('"allow_url_fopen" is not enabled in php.ini', 'ERROR');
            }
            if ($socket_time = ini_get('default_socket_timeout')) {
                if ($socket_time < 60) {
                    $this->setErrors('Value of "default_socket_timeout" is less than 60 sec in php.ini: ' . $socket_time . ' sec', 'WARNING');
                }
            } else {
                $this->setErrors('"default_socket_timeout" is not exist in php.ini', 'ERROR');
            }

            if (($time_limit = ini_get('max_execution_time')) < 30) {
                $this->setErrors('Value of "max_execution_time" is less than 60 sec in php.ini: ' . $time_limit . ' sec', 'WARNING');
            }

            if (ini_get('safe_mode')) {
                $this->setErrors('"safe_mode" is enabled in php.ini', 'WARNING');
            }
            if (ini_get('magic_quotes_gpc')) {
                $this->setErrors('"magic_quotes_gpc" is enabled in php.ini', 'ERROR');
            }

        }

    }
    private function enable()
    {
        $result = @mysql_query('
        CREATE TABLE IF NOT EXISTS `mod_w3esxi_config` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `name` VARCHAR( 256 ) NOT NULL ,
        `value` TEXT NOT NULL
        ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;
        ');
        if (!$result) {
            $this->setErrors('Cannot create table `mod_w3esxi_config` in database: ' . mysql_error(), 'ERROR');
        }
        $result = @mysql_query('
        CREATE TABLE IF NOT EXISTS  `mod_w3esxi` (
        `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `serviceid` BIGINT NOT NULL ,
        `vmid` BIGINT NOT NULL ,
        `clientid` BIGINT NOT NULL ,
        `serverid` BIGINT NOT NULL ,
        `pid` BIGINT NOT NULL
        ) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;
        ');

        if (!$result) {
            $this->setErrors('Cannot create table `mod_w3esxi` in database: ' . mysql_error(), 'ERROR');
        }

        $result = @mysql_query("
        INSERT INTO `mod_w3esxi_config` (`id`, `name`, `value`) VALUES
        (2, 'show_info_client', '1'),
        (3, 'show_info_admin', '1'),
        (4, 'version', '0.7.2');
        ");

        $this->update();
    }

    private function update()
    {

        $oldVersion = $this->getSettings('version');
        if ($oldVersion == '0.7' || $oldVersion == '0.7.1' || $oldVersion == '0.7.2' || $oldVersion == '0.7.3') {
            $tableSQL = '
CREATE TABLE IF NOT EXISTS `mod_w3esxi_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `w3e_id` int(11) NOT NULL,
      `serviceid` int(11) NOT NULL,
      `date_logged` datetime NOT NULL,
      `locking_date_expired` datetime NOT NULL,
      `client_ip` varchar(64) DEFAULT NULL,
      `user_agent` text,
      `command_by` varchar(6) NOT NULL,
      `action` varchar(20) NOT NULL,
      PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;';
            $result = @mysql_query($tableSQL);
            if (!$result) {
                $this->setErrors('Cannot create table `mod_w3esxi_logs` in database: ' . mysql_error(), 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=home');
                exit();
            }
            $insertConfigSQL = "
INSERT INTO `mod_w3esxi_config` (`id`, `name`, `value`) VALUES
(5, 'quick_insert_items_per_page', '20'),
(6, 'allvm_items_per_page', '10'),
(7, 'admin_logs_items_per_page', '25'),
(8, 'client_logs_items', '5'),
(9, 'force_admin_commands', '1'),
(10, 'ssh_library', 'phpseclib'),
(11, 'reset_lock_time', '45s'),
(12, 'poweron_lock_time', '45s'),
(13, 'poweroff_lock_time', '45s'),
(14, 'rebootos_lock_time', '2m'),
(15, 'shutdownos_lock_time', '2m'),
(16, 'show_hard_details', '0'),
(17, 'show_admin_commands_client_logs', '1'),
(18, 'debug_mode',  '0'),
(19, 'only_show_power_state_to_admin', '0'),
(20, 'only_show_power_state_to_client', '0'),
(21, 'auto_remove_vmid_of_deleted_services', '1'),
(22, 'auto_remove_also_cancelled_services', '0')
;

        ";

            $result = @mysql_query($insertConfigSQL);
            if (!$result) {
                $this->setErrors('Cannot insert new rows in `mod_w3esxi_config`: ' . mysql_error(), 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=home');
                exit();
            }
            $rows = mysql_affected_rows();
            if ($rows != 18) {
                $this->setErrors(18 - $rows . ' rows were not inserted in database', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=home');
                exit();
            }
            @update_query('mod_w3esxi_config', array('value' => W3E_VERSION), array('name' => 'version'));
            if (mysql_affected_rows() != 1) {
                $this->setErrors('Cannot update version in database', 'ERROR');
                header('Location: ' . W3E_MOD_LINK . '&view=home');
                exit();
            }

            $this->setErrors("Module has been updated to " . W3E_VERSION, 'SUCCESS');

            header('Location: ' . W3E_MOD_LINK . '&view=home');
            exit();

        } else if ($oldVersion == W3E_VERSION) {
            $this->setErrors('You\'ve already updated w3esxi', 'WARNING');
            header('Location: ' . W3E_MOD_LINK . '&view=home');
            exit();
        } else {
            $this->setErrors('Your module has invalid version number in Database', 'ERROR');
            header('Location: ' . W3E_MOD_LINK . '&view=home');
            exit();
        }

    }

    private function checkConnectivity($ip, $port, $user, $pass)
    {
        $sshlibrary = $this->getSettings('ssh_library');
        $libssh = new LibSSH2($sshlibrary);

        $result = @$libssh->connect($ip, $port, true);

        if (!$result) {
            return 'RED';
        }
        $result = @$libssh->authPassword($user, $pass, true);
        if (!$result) {
            return 'ORANGE';
        }

        $result = @$libssh->cmdExec('echo OK');
        if (!$result || stripos($result, 'OK') === false) {
            return 'YELLOW';
        }
        return 'GREEN';

    }

    private function checkDebuggingMode()
    {
        if ($this->getSettings('debug_mode')) {
            error_reporting(E_ALL);
            $this->setErrors('Debugging mode is enabled', 'WARNING');
        } else {
            error_reporting(0);
        }
    }
}
