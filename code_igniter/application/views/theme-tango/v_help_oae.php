<?php 
#  Copyright 2003-2014 Opmantek Limited (www.opmantek.com)
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
 * @package Open-AudIT
 * @author Mark Unwin <marku@opmantek.com>
 * @version 1.5.1
 * @copyright Copyright (c) 2014, Opmantek
 * @license http://www.gnu.org/licenses/agpl-3.0.html aGPL v3
 */

$sortcolumn = 2;
?>
<form action="vars.php" method="post" class="niceforms">
	<fieldset id="about" class="niceforms">
		<legend><span style="font-size: 12pt;">&nbsp;<?php echo __('Open-AudIT Enterprise')?></span></legend>
		Open-AudIT Enterprise is now available.<br />
		Scheduled Reports, Interactive Dashboards and Maps and more.<br />
		All purchases go to further Open-AudIT for all of us. Contact <a href="https://opmantek.com" style="color: green;">Opmantek</a>.<br />
		<br /><br />
		<img src="<?php echo base_url(); ?>theme-tango/tango-images/sample_dashboard.png" /><br /><br />
		<img src="<?php echo base_url(); ?>theme-tango/tango-images/sample_report.png" /><br />
	</fieldset>
</form>