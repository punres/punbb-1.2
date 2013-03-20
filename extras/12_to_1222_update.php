<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// This script updates the forum database from version 1.2.* to 1.2.22.
// Copy this file to the forum root directory and run it. Then remove it from
// the root directory.


$update_from = array('1.2', '1.2.1', '1.2.2', '1.2.3', '1.2.4', '1.2.5', '1.2.6', '1.2.7', '1.2.8', '1.2.9', '1.2.10', '1.2.11', '1.2.12', '1.2.13', '1.2.14', '1.2.15', '1.2.16', '1.2.17', '1.2.18', '1.2.19', '1.2.20', '1.2.21');
$update_to = '1.2.22';


define('PUN_ROOT', './');
@include PUN_ROOT.'config.php';

// If PUN isn't defined, config.php is missing or corrupt or we are outside the root directory
if (!defined('PUN'))
	exit('This file must be run from the forum root directory.');

// Enable debug mode
define('PUN_DEBUG', 1);

// Disable error reporting for uninitialized variables
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())	
	set_magic_quotes_runtime(0);

// Turn off PHP time limit
@set_time_limit(0);


// Load the functions script
require PUN_ROOT.'include/functions.php';


// Load DB abstraction layer and try to connect
require PUN_ROOT.'include/dblayer/common_db.php';


// Check current version
$result1 = $db->query('SELECT cur_version FROM '.$db->prefix.'options');
$result2 = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name=\'o_cur_version\'');
$cur_version = ($result1) ? $db->result($result1) : (($result2 && $db->num_rows($result2)) ? $db->result($result2) : 'beta');

if ($cur_version == $update_to)
	error('The database \''.$db_name.'\' has already been updated to version '.$update_to.'.', __FILE__, __LINE__);
else if (!in_array($cur_version, $update_from))
	error('Version mismatch. This script updates version '.implode(', ', $update_from).' to version '.$update_to.'. The database \''.$db_name.'\' doesn\'t seem to be running a supported version.', __FILE__, __LINE__);


// Get the forum config
$result = $db->query('SELECT * FROM '.$db->prefix.'config');
while ($cur_config_item = $db->fetch_row($result))
	$pun_config[$cur_config_item[0]] = $cur_config_item[1];


if (!isset($_POST['form_sent']))
{

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>PunBB Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="blockform">
	<h2><span>PunBB Update</span></h2>
	<div class="box">
		<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" onsubmit="this.start.disabled=true">
			<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
				<p style="font-size: 1.1em">This script will update your current PunBB <?php echo $cur_version ?> forum database to PunBB <?php echo $update_to ?>. The update procedure might take anything from a second to a few minutes depending on the speed of the server and the size of the forum database. Don't forget to make a backup of the database before continuing.</p>
				<p style="font-size: 1.1em">Did you read the update instructions in the documentation? If not, start there.</p>
			</div>
			<p><input type="submit" name="start" value="Start upgrade" /></p>
		</form>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

}
else
{
	//
	// Database update functions
	//
	function table_exists($table_name, $no_prefix = false)
	{
		global $db, $db_type;

		switch ($db_type)
		{
			case 'pgsql':
				$result = $db->query('SELECT 1 FROM pg_class WHERE relname = \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\'');
				return $db->num_rows($result) > 0;

			case 'sqlite':
				$result = $db->query('SELECT 1 FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\' AND type=\'table\'');
				return $db->num_rows($result) > 0;

			default:
				$result = $db->query('SHOW TABLES LIKE \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\'');
				return $db->num_rows($result) > 0;
		}
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		global $db, $db_type;

		switch ($db_type)
		{
			case 'pgsql':
				$result = $db->query('SELECT 1 FROM pg_class c INNER JOIN pg_attribute a ON a.attrelid = c.oid WHERE c.relname = \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\' AND a.attname = \''.$db->escape($field_name).'\'');
				return $db->num_rows($result) > 0;

			case 'sqlite':
				$result = $db->query('SELECT sql FROM sqlite_master WHERE name = \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\' AND type=\'table\'');
				if (!$db->num_rows($result))
					return false;

				return preg_match('%[\r\n]'.preg_quote($field_name, '%').' %', $db->result($result));

			default:
				$result = $db->query('SHOW COLUMNS FROM '.($no_prefix ? '' : $db->prefix).$table_name.' LIKE \''.$db->escape($field_name).'\'');
				return $db->num_rows($result) > 0;
		}
	}


	function drop_table($table_name, $no_prefix = false)
	{
		global $db;

		if (!table_exists($table_name, $no_prefix))
			return true;

		return $db->query('DROP TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name)) ? true : false;
	}


	function get_table_info($table_name, $no_prefix = false)
	{
		global $db, $db_type;
	
		if ($db_type != 'sqlite')
			return;

		// Grab table info
		$result = $db->query('SELECT sql FROM sqlite_master WHERE tbl_name = \''.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'\' ORDER BY type DESC') or error('Unable to fetch table information', __FILE__, __LINE__, $db->error());
		$num_rows = $db->num_rows($result);

		if ($num_rows == 0)
			return;

		$table = array();
		$table['indices'] = array();
		while ($cur_index = $db->fetch_assoc($result))
		{
			if (empty($cur_index['sql']))
				continue;

			if (!isset($table['sql']))
				$table['sql'] = $cur_index['sql'];
			else
				$table['indices'][] = $cur_index['sql'];
		}

		// Work out the columns in the table currently
		$table_lines = explode("\n", $table['sql']);
		$table['columns'] = array();
		foreach ($table_lines as $table_line)
		{
			$table_line = trim($table_line, " \t\n\r,"); // trim spaces, tabs, newlines, and commas
			if (substr($table_line, 0, 12) == 'CREATE TABLE')
				continue;
			else if (substr($table_line, 0, 11) == 'PRIMARY KEY')
				$table['primary_key'] = $table_line;
			else if (substr($table_line, 0, 6) == 'UNIQUE')
				$table['unique'] = $table_line;
			else if (substr($table_line, 0, strpos($table_line, ' ')) != '')
				$table['columns'][substr($table_line, 0, strpos($table_line, ' '))] = trim(substr($table_line, strpos($table_line, ' ')));
		}

		return $table;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		global $db, $db_type;

		switch ($db_type)
		{
			case 'pgsql':
				if (field_exists($table_name, $field_name, $no_prefix))
					return true;

				$datatype_transformations = array(
					'%^(TINY|SMALL)INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'			=>	'SMALLINT',
					'%^(MEDIUM)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'				=>	'INTEGER',
					'%^BIGINT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'						=>	'BIGINT',
					'%^(TINY|MEDIUM|LONG)?TEXT$%i'										=>	'TEXT',
					'%^DOUBLE( )?(\\([0-9,]+\\))?( )?(UNSIGNED)?$%i'					=>	'DOUBLE PRECISION',
					'%^FLOAT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'						=>	'REAL'
				);

				$field_type = preg_replace(array_keys($datatype_transformations), array_values($datatype_transformations), $field_type);

				$result = $db->query('ALTER TABLE '.($no_prefix ? '' : $db->prefix).$table_name.' ADD '.$field_name.' '.$field_type) ? true : false;

				if (!is_null($default_value))
				{
					if (!is_int($default_value) && !is_float($default_value))
						$default_value = '\''.$db->escape($default_value).'\'';

					$result &= $db->query('ALTER TABLE '.($no_prefix ? '' : $db->prefix).$table_name.' ALTER '.$field_name.' SET DEFAULT '.$default_value) ? true : false;
					$result &= $db->query('UPDATE '.($no_prefix ? '' : $db->prefix).$table_name.' SET '.$field_name.'='.$default_value) ? true : false;
				}

				if (!$allow_null)
					$result &= $db->query('ALTER TABLE '.($no_prefix ? '' : $db->prefix).$table_name.' ALTER '.$field_name.' SET NOT NULL') ? true : false;

				return $result;

			case 'sqlite':
				if (field_exists($table_name, $field_name, $no_prefix))
					return true;

				$table = get_table_info($table_name, $no_prefix);

				// Create temp table
				$now = time();
				$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now.' (', $table['sql']);
				$result = $db->query($tmptable) ? true : false;
				$result &= $db->query('INSERT INTO '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $db->prefix).$db->escape($table_name)) ? true : false;

				$datatype_transformations = array(
					'%^SERIAL$%'															=>	'INTEGER',
					'%^(TINY|SMALL|MEDIUM|BIG)?INT( )?(\\([0-9]+\\))?( )?(UNSIGNED)?$%i'	=>	'INTEGER',
					'%^(TINY|MEDIUM|LONG)?TEXT$%i'											=>	'TEXT'
				);

				// Create new table sql
				$field_type = preg_replace(array_keys($datatype_transformations), array_values($datatype_transformations), $field_type);
				$query = $field_type;

				if (!$allow_null)
					$query .= ' NOT NULL';

				if ($default_value === '')
					$default_value = '\'\'';

				if (!is_null($default_value))
					$query .= ' DEFAULT '.$default_value;

				$old_columns = array_keys($table['columns']);

				// Determine the proper offset
				if (!is_null($after_field))
					$offset = array_search($after_field, array_keys($table['columns']), true) + 1;
				else
					$offset = count($table['columns']);

				// Out of bounds checks
				if ($offset > count($table['columns']))
					$offset = count($table['columns']);
				else if ($offset < 0)
					$offset = 0;

				if (!is_null($field_name) && $field_name !== '')
					$table['columns'] = array_merge(array_slice($table['columns'], 0, $offset), array($field_name => $query), array_slice($table['columns'], $offset));

				$new_table = 'CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' (';

				foreach ($table['columns'] as $cur_column => $column_details)
					$new_table .= "\n".$cur_column.' '.$column_details.',';

				if (isset($table['unique']))
					$new_table .= "\n".$table['unique'].',';

				if (isset($table['primary_key']))
					$new_table .= "\n".$table['primary_key'].',';

				$new_table = trim($new_table, ',')."\n".');';

				// Drop old table
				$result &= drop_table($table_name, $no_prefix);

				// Create new table
				$result &= $db->query($new_table) ? true : false;

				// Recreate indexes
				if (!empty($table['indices']))
				{
					foreach ($table['indices'] as $cur_index)
						$result &= $db->query($cur_index) ? true : false;
				}

				// Copy content back
				$result &= $db->query('INSERT INTO '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' ('.implode(', ', $old_columns).') SELECT * FROM '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now) ? true : false;

				// Drop temp table
				$result &= drop_table($table_name.'_t'.$now, $no_prefix);

				return $result;

			default:
				if (field_exists($table_name, $field_name, $no_prefix))
					return true;

				$datatype_transformations = array(
					'%^SERIAL$%'	=>	'INT(10) UNSIGNED AUTO_INCREMENT'
				);

				$field_type = preg_replace(array_keys($datatype_transformations), array_values($datatype_transformations), $field_type);

				if (!is_null($default_value) && !is_int($default_value) && !is_float($default_value))
					$default_value = '\''.$db->escape($default_value).'\'';

				return $db->query('ALTER TABLE '.($no_prefix ? '' : $db->prefix).$table_name.' ADD '.$field_name.' '.$field_type.($allow_null ? ' ' : ' NOT NULL').(!is_null($default_value) ? ' DEFAULT '.$default_value : ' ').(!is_null($after_field) ? ' AFTER '.$after_field : '')) ? true : false;
		}
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		global $db, $db_type;

		switch ($db_type)
		{
			case 'sqlite':
				if (!field_exists($table_name, $field_name, $no_prefix))
					return true;

				$table = get_table_info($table_name, $no_prefix);

				// Create temp table
				$now = time();
				$tmptable = str_replace('CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' (', 'CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now.' (', $table['sql']);
				$result = $db->query($tmptable) ? true : false;
				$result &= $db->query('INSERT INTO '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now.' SELECT * FROM '.($no_prefix ? '' : $db->prefix).$db->escape($table_name)) ? true : false;

				// Work out the columns we need to keep and the sql for the new table
				unset($table['columns'][$field_name]);
				$new_columns = array_keys($table['columns']);

				$new_table = 'CREATE TABLE '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' (';

				foreach ($table['columns'] as $cur_column => $column_details)
					$new_table .= "\n".$cur_column.' '.$column_details.',';

				if (isset($table['unique']))
					$new_table .= "\n".$table['unique'].',';

				if (isset($table['primary_key']))
					$new_table .= "\n".$table['primary_key'].',';

				$new_table = trim($new_table, ',')."\n".');';

				// Drop old table
				$result &= drop_table($table_name, $no_prefix);

				// Create new table
				$result &= $db->query($new_table) ? true : false;

				// Recreate indexes
				if (!empty($table['indices']))
				{
					foreach ($table['indices'] as $cur_index)
						if (!preg_match('%\('.preg_quote($field_name, '%').'\)%', $cur_index))
							$result &= $db->query($cur_index) ? true : false;
				}

				// Copy content back
				$result &= $db->query('INSERT INTO '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).' SELECT '.implode(', ', $new_columns).' FROM '.($no_prefix ? '' : $db->prefix).$db->escape($table_name).'_t'.$now) ? true : false;

				// Drop temp table
				$result &= drop_table($table_name.'_t'.$now, $no_prefix);

				return $result;

			default:
				if (!field_exists($table_name, $field_name, $no_prefix))
					return true;
	
				return $db->query('ALTER TABLE '.($no_prefix ? '' : $db->prefix).$table_name.' DROP '.$field_name) ? true : false;
		}
	}


	function truncate_table($table_name, $no_prefix = false)
	{
		global $db, $db_type;

		switch ($db_type)
		{
			case 'pgsql':
			case 'sqlite':
				return $db->query('DELETE FROM '.($no_prefix ? '' : $db->prefix).$table_name) ? true : false;

			default:
				return $db->query('TRUNCATE TABLE '.($no_prefix ? '' : $db->prefix).$table_name) ? true : false;
		}
	}


	// If we're upgrading from 1.2
	if ($cur_version == '1.2')
	{
		// Insert new config option o_additional_navlinks
		$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES(\'o_additional_navlinks\', NULL)') or error('Unable to alter DB structure.', __FILE__, __LINE__, $db->error());
	}

	// Add the read_topics column to the users table
	add_field('users', 'read_topics', 'MEDIUMTEXT', true, null, 'last_visit') or error('Unable to add last_visit field', __FILE__, __LINE__, $db->error());

	// Increase visit timeout to 60 minutes (if it is less than 60 minutes)
	$result = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name = \'o_timeout_visit\'');
	if ($db->num_rows($result))
	{
		$timeout_visit = $db->result($result);

		if (intval($timeout_visit) < 3600)
			$db->query('UPDATE '.$db->prefix.'config SET conf_value=\'3600\' WHERE conf_name=\'o_timeout_visit\'') or error('Unable to update board config', __FILE__, __LINE__, $db->error());
	}

	// We need to add a unique index to avoid users having multiple rows in the online table
	if ($db_type == 'mysql' || $db_type == 'mysqli')
	{
		$result = $db->query('SHOW INDEX FROM '.$db->prefix.'online') or error('Unable to check DB structure.', __FILE__, __LINE__, $db->error());

		if ($db->num_rows($result) == 1)
			$db->query('ALTER TABLE '.$db->prefix.'online ADD UNIQUE INDEX '.$db->prefix.'online_user_id_ident_idx(user_id,ident)') or error('Unable to alter DB structure.', __FILE__, __LINE__, $db->error());
	}

	// This feels like a good time to synchronize the forums
	$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum info.', __FILE__, __LINE__, $db->error());

	while ($row = $db->fetch_row($result))
		update_forum($row[0]);


	// We'll empty the search cache table as well (using DELETE FROM since SQLite does not support TRUNCATE TABLE)
	$db->query('DELETE FROM '.$db->prefix.'search_cache') or error('Unable to flush search results.', __FILE__, __LINE__, $db->error());


	// Finally, we update the version number
	$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$update_to.'\' WHERE conf_name=\'o_cur_version\'') or error('Unable to update version.', __FILE__, __LINE__, $db->error());


	// Delete all .php files in the cache (someone might have visited the forums while we were updating and thus, generated incorrect cache files)
	$d = dir(PUN_ROOT.'cache');
	while (($entry = $d->read()) !== false)
	{
		if (substr($entry, strlen($entry)-4) == '.php')
			@unlink(PUN_ROOT.'cache/'.$entry);
	}
	$d->close();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>PunBB Update</title>
<link rel="stylesheet" type="text/css" href="style/Oxygen.css" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<div class="block">
	<h2><span>Update completed</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Update successful! Your forum database has now been updated to version <?php echo $update_to ?>. You should now remove this script from the forum root directory and follow the rest of the instructions in the documentation.</p>
		</div>
	</div>
</div>

</div>
</div>

</body>
</html>
<?php

}
