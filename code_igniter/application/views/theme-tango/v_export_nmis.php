<?php $sortcolumn = 3; 
# check to see if user_access_level for this group is > 7


if (!file_exists("/usr/local/nmis8/admin/import_nodes.pl")) {
	$warning = "<span style='color: red;'>NOTE</span> - You do not have nmis installed on this system. A file will be provided that you can copy to the nmis server and run with the nmis8/admin/import_nodes.pl script.<br /><span style='color: red;'>NOTE</span> - Any attributes that are blank have had default values substituted in <span style='color: blue;'>blue</span>.";
} else {
	$warning = "";
}

$manual_edit = 'y';
echo "<div style=\"float:left; width:100%;\">\n";
$attributes = array('id' => 'change_form', 'name' => 'change_form');
#$attributes = array('id' => 'alertform', 'name' => 'alertform');
echo form_open('admin/export_nmis', $attributes) . "\n"; 
echo "<input type=\"hidden\" name=\"group_id\" value=\"" . $group_id . "\" />\n";

echo "<span id=\"warning\">" . $warning . "</span>\n";
echo "<table cellspacing=\"1\" class=\"tablesorter\">\n";
echo "\t<thead>\n";
echo "\t\t<tr>\n";
echo "<th width=\"30%\" align=\"left\">Name</th>";
echo "<th width=\"20%\" align=\"left\">Host</th>";
echo "<th width=\"20%\" align=\"left\">Group</th>";
echo "<th width=\"10%\" align=\"left\">Role</th>";
echo "<th width=\"10%\" align=\"left\">Community</th>";

# edit column
#echo "<th align=\"center\" class=\"{sorter: false}\"><button onClick=\"document.change_form.submit();\">Edit</button>";
#echo "<input type=\"checkbox\" id=\"system_id_0\" name=\"system_id_0\" onchange=\"check_all_systems();\"/></th>";

# export column
echo "<th align=\"center\" class=\"{sorter: false}\"><button id='export' name='export' onClick=\"document.alertform.submit();\">Export</button>";
echo "<input type=\"checkbox\" id=\"system_id_0\" name=\"system_id_0\" onchange=\"check_all_systems();\"/></th>";

echo "\t\t</tr>\n";
echo "\t</thead>\n";
$id = 0;
$blank_attributes = 'n'; 
if (count($query) > 0) {
	echo "\t<tbody>\n";
	$i = 0;
	foreach($query as $key) {
		echo "\t\t<tr>\n";
		echo "\t\t\t<td align=\"left\"><a class=\"SystemPopupTrigger\" rel=\"" . $key->system_id . "\" href=\"" . site_url()  . "/main/system_display/" . $key->system_id . "\">" . $key->nmis_name . "</a></td>\n";
		echo "\t\t\t<td align=\"left\">" . $key->nmis_host . "</td>\n";
		echo "\t\t\t<td align=\"left\">" . $key->nmis_group . "</td>\n";
		echo "\t\t\t<td align=\"left\">" . $key->nmis_role . "</td>\n";
		echo "\t\t\t<td align=\"left\">" . $key->nmis_community . "</td>\n";
		if ( $manual_edit == 'y') { 
			#echo "\t\t\t<td align=\"center\"><input type=\"checkbox\" id=\"system_id_" . $key->system_id . "\" name=\"system_id_" . $key->system_id . "\" /></td>\n";
			echo "\t\t\t<td align=\"center\"><input type=\"checkbox\" id=\"system_id_" . $key->system_id . "\" name=\"system_id_" . $key->system_id . "\" /></td>\n";
		}
		echo "\n\t\t</tr>\n";
	}
	echo "\t</tbody>\n";
} else {
	echo "\t\t<tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>\n";
}
echo "</table>\n";
if ($manual_edit == 'y') {
	echo "</form>\n";
	echo "</div>\n";
}
?>

<script type="text/javascript">

function check_all_systems() {
	if (document.getElementById("system_id_0").checked == true) {
		<?php
		foreach($query as $key):
			if (isset($key->system_id)) {
				echo "\tdocument.getElementById(\"system_id_" . $key->system_id . "\").checked = true;\n";
			}
		endforeach;
		?>
	} else {
		<?php
		foreach($query as $key):
			if (isset($key->system_id)) {
				echo "\tdocument.getElementById(\"system_id_" . $key->system_id . "\").checked = false;\n";
			}
		endforeach;
		?>
	}
}
</script>


<?php
function replace_amp($string) {
	$replaced_amp = str_replace("&amp;", "&", $string);
	$replaced_amp = str_replace("&", "&amp;", $replaced_amp);
	return $replaced_amp;
}
?>