<div class="grid_5" style="text-align:left;direction:ltr;">
    <div class="module">
<h3><span>Service Overview<span></h3>            
            
            <div class="module-body">
            
            	<p>
				{W3EIF_STATUS}
                    <strong>Status: </strong>{W3E_POWER_STATE_TEXT}  <img src="{W3E_IMG_PATH}circle_{W3E_POWER_STATE_COLOR}.png" width="16" height="16" alt="{W3E_POWER_STATE_TEXT}" title="{W3E_POWER_STATE_TEXT}" /><br />
				{/W3EIF_STATUS}
				{W3EELSE_STATUS}
					<strong>IP: </strong>{W3E_IP}<br />
                    <strong>Host: </strong>{W3E_HOST}<br />
                </p>
				<p>
					<strong>OS: </strong>{W3E_OS}<br />
					<strong>BootTime: </strong>{W3E_BOOT_TIME}<br />
					<strong>UpTime: </strong>{W3E_UPTIME}<br />
					<strong>CPU Usage: </strong> {W3E_CPU_USAGE} MHz
					
				</p>
				 <div>
                     <div class="indicator">
                         <div style="width: {W3E_RAM_USAGE_PERCENT}%;"></div>
                     </div>
                     <p><strong>Ram Usage: </strong>{W3E_RAM_USAGE} MB of {W3E_RAM} MB</p>
                 </div>
				 
                 <div>
				{W3EIF_HARD_DETAILS}
					 <div class="indicator">
                         <div style="width: {W3E_SPACE_USAGE_PERCENT}%;"></div>
                     </div>
                     <p><strong>Free Space: </strong>{W3E_FREE_SPACE} GB of {W3E_SPACE} GB</p>
                     <strong>Number of Hards: </strong>{W3E_HARD_NUMS}<br />
				{/W3EIF_HARD_DETAILS}
				{W3EELSE_HARD_DETAILS}
					<p><strong>Space: </strong>{W3E_SPACE} GB<br />
				{/W3EELSE_HARD_DETAILS}
                 </div>
				{/W3EELSE_STATUS}
            </div>
    </div>
    <div style="clear:both;"></div>
</div>
    