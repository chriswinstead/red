<?php

function layouts_content(&$a) {

	if(argc() > 1)
		$which = argv(1);
	else {
		notice( t('Requested profile is not available.') . EOL );
		$a->error = 404;
		return;
	}

	profile_load($a,$which,0);


	// Figure out who the page owner is.
	$r = q("select channel_id from channel where channel_address = '%s'",
		dbesc($which)
	);
	if($r) {
		$owner = intval($r[0]['channel_id']);
	}

	// Block design features from visitors 

	if((! local_user()) || (local_user() != $owner)) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	// Get the observer, check their permissions

	$observer = $a->get_observer();
	$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

	$perms = get_all_perms($owner,$ob_hash);

	if(! $perms['write_pages']) {
		notice( t('Permission denied.') . EOL);
		return;
	}

	if((argc() > 3) && (argv(2) === 'share') && (argv(3))) {
		$r = q("select sid, service, mimetype, title, body  from item_id left join item on item.id = item_id.iid where item_id.uid = %d and item.mid = '%s' and service = 'PDL' order by sid asc",
			intval($owner),
			dbesc(argv(3))
		);
		if($r) {
			header('Content-type: application/x-redmatrix-layout');
			header('Content-disposition: attachment; filename="' . $r[0]['sid'] . '.pdl"');
			echo json_encode($r);
			killme();

		}
	}

	$tabs = array(
		array(
		'label' => t('Layout Help'),
		'url'   => 'help/Comanche',
		'sel'   => '',
		'title' => t('Help with this feature'),
		'id'    => 'layout-help-tab',
	));


	$o .= replace_macros(get_markup_template('common_tabs.tpl'),array('$tabs' => $tabs));


	// Create a status editor (for now - we'll need a WYSIWYG eventually) to create pages
	// Nickname is set to the observers xchan, and profile_uid to the owners.  
	// This lets you post pages at other people's channels.

	require_once ('include/conversation.php');

	$x = array(
		'webpage' => ITEM_PDL,
		'is_owner' => true,
		'nickname' => $a->profile['channel_address'],
		'lockstate' => (($group || $cid || $channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
		'bang' => (($group || $cid) ? '!' : ''),
		'showacl' => false,
		'visitor' => false,
		'nopreview' => 1,
		'ptlabel' => t('Layout Name'),
		'profile_uid' => intval($owner),
	);

	if($_REQUEST['title'])
		$x['title'] = $_REQUEST['title'];
	if($_REQUEST['body'])
		$x['body'] = $_REQUEST['body'];
	if($_REQUEST['pagetitle'])
		$x['pagetitle'] = $_REQUEST['pagetitle'];


	$o .= status_editor($a,$x);

	// Get a list of blocks.  We can't display all them because endless scroll makes that unusable, so just list titles and an edit link.
	// TODO - this should be replaced with pagelist_widget

	$r = q("select iid, sid, mid from item_id left join item on item.id = item_id.iid where item_id.uid = %d and service = 'PDL' order by sid asc",
		intval($owner)
	);

	$pages = null;

	if($r) {
		$pages = array();
		foreach($r as $rr) {
			$pages[$rr['iid']][] = array('url' => $rr['iid'],'title' => $rr['sid'], 'mid' => $rr['mid']);
		} 
	}


	//Build the base URL for edit links
	$url = z_root() . "/editlayout/" . $which; 

	return $o . replace_macros(get_markup_template("layoutlist.tpl"), array(
		'$baseurl' => $url,
		'$edit' => t('Edit'),
		'$share' => t('Share'),
		'$pages' => $pages,
		'$channel' => $which,
		'$view' => t('View'),
		'$preview' => '1',
	
	));
    

}
