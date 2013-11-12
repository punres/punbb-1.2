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


// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/common_admin.php';


if ($pun_user['g_id'] > PUN_ADMIN)
	message($lang_common['No permission']);


// Prune posts
if (isset($_POST['prune']) || isset($_POST['prune_comply']))
{
	if (isset($_POST['prune_comply']))
	{
		confirm_referrer('admin_prune.php');

		$prune_from = $_POST['prune_from'];
		$prune_sticky = isset($_POST['prune_sticky']) ? '1' : '0';
		$prune_days = intval($_POST['prune_days']);
		$prune_date = ($prune_days) ? time() - ($prune_days*86400) : -1;

		@set_time_limit(0);

		if ($prune_from == 'all')
		{
			$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());
			$num_forums = $db->num_rows($result);

			for ($i = 0; $i < $num_forums; ++$i)
			{
				$fid = $db->result($result, $i);

				prune($fid, $prune_sticky, $prune_date);
				update_forum($fid);
			}
		}
		else
		{
			$prune_from = intval($prune_from);
			prune($prune_from, $prune_sticky, $prune_date);
			update_forum($prune_from);
		}

		// Locate any "orphaned redirect topics" and delete them
		$result = $db->query('SELECT t1.id FROM '.$db->prefix.'topics AS t1 LEFT JOIN '.$db->prefix.'topics AS t2 ON t1.moved_to=t2.id WHERE t2.id IS NULL AND t1.moved_to IS NOT NULL') or error('Unable to fetch redirect topics', __FILE__, __LINE__, $db->error());
		$num_orphans = $db->num_rows($result);

		if ($num_orphans)
		{
			for ($i = 0; $i < $num_orphans; ++$i)
				$orphans[] = $db->result($result, $i);

			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $orphans).')') or error('Unable to delete redirect topics', __FILE__, __LINE__, $db->error());
		}

		redirect('admin_prune.php', 'Posts pruned. Redirecting &hellip;');
	}


	$prune_days = $_POST['prune_days'];
	if (!@preg_match('/^[0-9]+$/', $prune_days))
		message('Days to prune must be an integer equal to or greater than zero.');

	$prune_date = time() - ($prune_days*86400);
	$prune_from = $_POST['prune_from'];

	// Concatenate together the query for counting number of topics to prune
	$sql = 'SELECT COUNT(id) FROM '.$db->prefix.'topics WHERE last_post<'.$prune_date.' AND moved_to IS NULL';

	if (!$prune_sticky)
		$sql .= ' AND sticky=\'0\'';

	if ($prune_from != 'all')
	{
		$prune_from = intval($prune_from);
		$sql .= ' AND forum_id='.$prune_from;

		// Fetch the forum name (just for cosmetic reasons)
		$result = $db->query('SELECT forum_name FROM '.$db->prefix.'forums WHERE id='.$prune_from) or error('Unable to fetch forum name', __FILE__, __LINE__, $db->error());
		$forum = '"'.pun_htmlspecialchars($db->result($result)).'"';
	}
	else
		$forum = 'all forums';

	$result = $db->query($sql) or error('Unable to fetch topic prune count', __FILE__, __LINE__, $db->error());
	$num_topics = $db->result($result);

	if (!$num_topics)
		message('There are no topics that are '.$prune_days.' days old. Please decrease the value of "Days old" and try again.');


	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Prune';
	require PUN_ROOT.'header.php';

	generate_admin_menu('prune');

?>
	<div class="blockform">
		<h2><span>Prune</span></h2>
		<div class="box">
			<form method="post" action="admin_prune.php?action=foo">
				<div class="inform">
					<input type="hidden" name="prune_days" value="<?php echo $prune_days ?>" />
					<input type="hidden" name="prune_sticky" value="<?php echo $prune_sticky ?>" />
					<input type="hidden" name="prune_from" value="<?php echo $prune_from ?>" />
					<fieldset>
						<legend>Confirm prune posts</legend>
						<div class="infldset">
							<p>Are you sure that you want to prune all topics older than <?php echo $prune_days ?> days from <?php echo $forum ?>? (<?php echo $num_topics ?> topics)</p>
							<p>WARNING! Pruning posts deletes them permanently.</p>
						</div>
					</fieldset>
				</div>
				<p><input type="submit" name="prune_comply" value="Prune" /><a href="javascript:history.go(-1)">Go back</a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


// Prune unverified users
else if (isset($_POST['prune_users']) || isset($_POST['prune_users_comply']))
{
	if (isset($_POST['prune_users_comply']))
	{
		confirm_referrer('admin_prune.php');

		if (isset($_POST['prune_users_days']))
		{
			$prune_days = intval($_POST['prune_users_days']);
			$prune_date = time() - ($prune_days*86400);
		}
		else
			$prune_date = -1;

		@set_time_limit(0);

		// Delete the unverified users
		$db->query('DELETE FROM '.$db->prefix.'users WHERE group_id='.PUN_UNVERIFIED.' AND registered<'.$prune_date) or error('Unable to delete unverified users', __FILE__, __LINE__, $db->error());

		redirect('admin_prune.php', 'Unverified users pruned. Redirecting &hellip;');
	}


	$prune_days = $_POST['prune_users_days'];
	if (!@preg_match('/^[0-9]+$/', $prune_days))
		message('Days to prune must be an integer equal to or greater than zero.');

	$prune_date = time() - ($prune_days*86400);

	// Concatenate together the query for counting number of unverified users to prune
	$sql = 'SELECT COUNT(id) FROM '.$db->prefix.'users WHERE group_id='.PUN_UNVERIFIED.' AND registered<'.$prune_date;

	$result = $db->query($sql) or error('Unable to fetch user prune count', __FILE__, __LINE__, $db->error());
	$num_users = $db->result($result);

	if (!$num_users)
	{
		if ($prune_days == '0')
			message('There are no unverified users in the forum.');
		else
			message('There are no unverified users that who have not verified their accounts within the last '.$prune_days.' days. Please decrease the value of "Days unverified" and try again.');
	}

	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Prune';
	require PUN_ROOT.'header.php';

	generate_admin_menu('prune');

?>
	<div class="blockform">
		<h2><span>Prune</span></h2>
		<div class="box">
			<form method="post" action="admin_prune.php?action=foo">
				<div class="inform">
					<input type="hidden" name="prune_users_days" value="<?php echo $prune_days ?>" />
					<fieldset>
						<legend>Confirm prune unverified users</legend>
						<div class="infldset">
<?php
if ($prune_days == '0')
	echo "\t\t\t\t\t\t\t".'<p>Are you sure that you want to prune all unverified users? ('.$num_users.' users)</p>'."\n";
else
	echo "\t\t\t\t\t\t\t".'<p>Are you sure that you want to prune all unverified users who have not verified their accounts within the last '.$prune_days.' days? ('.$num_users.' users)</p>'."\n";
?>
							<p>WARNING! Pruning unverified users deletes them permanently.</p>
						</div>
					</fieldset>
				</div>
				<p><input type="submit" name="prune_users_comply" value="Prune" /><a href="javascript:history.go(-1)">Go back</a></p>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


else
{
	$page_title = pun_htmlspecialchars($pun_config['o_board_title']).' / Admin / Prune';
	$focus_element = array('prune', 'prune_days');
	require PUN_ROOT.'header.php';

	generate_admin_menu('prune');

?>
	<div class="blockform">
		<h2><span>Prune</span></h2>
		<div class="box">
			<form id="prune" method="post" action="admin_prune.php?action=foo" onsubmit="return process_form(this)">
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
						<legend>Prune old posts</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Days old</th>
									<td>
										<input type="text" name="prune_days" size="3" maxlength="3" tabindex="1" />
										<span>The number of days "old" a topic must be to be pruned. E.g. if you were to enter 30, every topic that didn't contain a post dated less than 30 days old would be deleted.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Prune sticky topics</th>
									<td>
										<input type="radio" name="prune_sticky" value="1" tabindex="2" checked="checked" />&nbsp;<strong>Yes</strong>&nbsp;&nbsp;&nbsp;<input type="radio" name="prune_sticky" value="0" />&nbsp;<strong>No</strong>
										<span>When enabled sticky topics will also be pruned.</span>
									</td>
								</tr>
								<tr>
									<th scope="row">Prune from forum</th>
									<td>
										<select name="prune_from" tabindex="3">
											<option value="all">All forums</option>
<?php

	$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id WHERE f.redirect_url IS NULL ORDER BY c.disp_position, c.id, f.disp_position') or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

	$cur_category = 0;
	while ($forum = $db->fetch_assoc($result))
	{
		if ($forum['cid'] != $cur_category)	// Are we still in the same category?
		{
			if ($cur_category)
				echo "\t\t\t\t\t\t\t\t\t\t\t".'</optgroup>'."\n";

			echo "\t\t\t\t\t\t\t\t\t\t\t".'<optgroup label="'.pun_htmlspecialchars($forum['cat_name']).'">'."\n";
			$cur_category = $forum['cid'];
		}

		echo "\t\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$forum['fid'].'">'.pun_htmlspecialchars($forum['forum_name']).'</option>'."\n";
	}

?>
											</optgroup>
										</select>
										<span>The forum from which you want to prune posts.</span>
									</td>
								</tr>
							</table>
							<p class="topspace">Use this feature with caution. Pruned posts can <strong>never</strong> be recovered. For best performance you should put the forum in maintenance mode during pruning.</p>
							<div class="fsetsubmit"><input type="submit" name="prune" value="Prune" tabindex="5" /></div>
						</div>
					</fieldset>
				</div>
				<div class="inform">
				<input type="hidden" name="form_sent" value="1" />
					<fieldset>
						<legend>Prune unverified users</legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row">Days unverified</th>
									<td>
										<input type="text" name="prune_users_days" size="3" maxlength="3" tabindex="1" />
										<span>Prune all users who have not verified their accounts within this number of days. E.g. if you were to enter 30, all unverified user who registered more than 30 days ago would be deleted. Enter 0 to delete all unverified users.</span>
									</td>
								</tr>
							</table>
							<p class="topspace">Use this feature with caution. Pruned users can <strong>never</strong> be recovered.</p>
							<div class="fsetsubmit"><input type="submit" name="prune_users" value="Prune" tabindex="5" /></div>
						</div>
					</fieldset>
				</div>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}
