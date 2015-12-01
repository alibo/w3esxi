<div class="module-body" style="text-align:left;direction:ltr;">

	<p>
		{W3EIF_STATUS}
        <strong>Status: </strong>{W3E_POWER_STATE_TEXT}  <img src="../{W3E_IMG_PATH}circle_{W3E_POWER_STATE_COLOR}.png" width="16" height="16" alt="{W3E_POWER_STATE_TEXT}" title="{W3E_POWER_STATE_TEXT}" /><br />
		{/W3EIF_STATUS}
		{W3EELSE_STATUS}
		<strong>IP: </strong>{W3E_IP}<br />
        <strong>Host: </strong>{W3E_HOST}<br />
    </p>
	<p>
		<strong>OS: </strong>{W3E_OS}<br />
		<strong>OS (vmware): </strong>{W3E_ONVMWARE_OS}<br />
		<strong>OS (Fullname - vmware): </strong>{W3E_ONVMWARE_FULLNAME_OS}<br />
		<strong>BootTime: </strong>{W3E_BOOT_TIME}<br />
		<strong>UpTime: </strong>{W3E_UPTIME}<br />
        <strong>CPU Usage: </strong>{W3E_CPU_USAGE} MHz of {W3E_CPU} MHz<br />
        <strong>Ram Usage: </strong>{W3E_RAM_USAGE} MB of {W3E_RAM} MB<br />
		{W3EIF_HARD_DETAILS}
			<strong>Free Space: </strong>{W3E_FREE_SPACE} GB of {W3E_SPACE} GB<br />
			<strong>Number of Hards: </strong>{W3E_HARD_NUMS}<br />
		{/W3EIF_HARD_DETAILS}
		{W3EELSE_HARD_DETAILS}
			<strong>Space: </strong>{W3E_SPACE} GB<br />
		{/W3EELSE_HARD_DETAILS}
		{/W3EELSE_STATUS}
	</p>
</div>