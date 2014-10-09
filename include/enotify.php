<?php /** @file */

function notification($params) {

	logger('notification: entry', LOGGER_DEBUG);

	// throw a small amount of entropy into the system to breakup duplicates arriving at the same precise instant.
	usleep(mt_rand(0,10000));
	

	$a = get_app();


	if($params['from_xchan']) {
		$x = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($params['from_xchan'])
		);
	}
	if($params['to_xchan']) {
		$y = q("select channel.*, account.* from channel left join account on channel_account_id = account_id
			where channel_hash = '%s' and not (channel_pageflags & %d) limit 1",
			dbesc($params['to_xchan']),
			intval(PAGE_REMOVED)
		);
	}
	if($x & $y) {
		$sender = $x[0];
		$recip = $y[0];
	}
	else {
		logger('notification: no sender or recipient.');
		logger('sender: ' . $params['from_xchan']);
		logger('recip: ' . $params['to_xchan']);
		return;
	}

	// from here on everything is in the recipients language

	push_lang($recip['account_language']); // should probably have a channel language

	$banner     = t('Red Matrix Notification');
	$product    = t('redmatrix'); // RED_PLATFORM;
	$siteurl    = $a->get_baseurl(true);
	$thanks     = t('Thank You,');
	$sitename   = get_config('system','sitename');
	$site_admin = sprintf( t('%s Administrator'), $sitename);

	$sender_name = $product;
	$hostname = $a->get_hostname();
	if(strpos($hostname,':'))
		$hostname = substr($hostname,0,strpos($hostname,':'));

	// Do not translate 'noreply' as it must be a legal 7-bit email address
	$sender_email = 'noreply' . '@' . $hostname;

	$additional_mail_header = "";

	if(array_key_exists('item',$params)) {
		require_once('include/conversation.php');
		// if it's a normal item...
		if(array_key_exists('verb',$params['item'])) {
			// localize_item() alters the original item so make a copy first
			$i = $params['item'];
			logger('calling localize');
			localize_item($i);
			$title = $i['title'];
			$body = $i['body'];
			$private = (($i['item_private']) || ($i['item_flags'] & ITEM_OBSCURED));
		}
		else {
			$title = $params['item']['title'];
			$body = $params['item']['body'];
		}
	}
	else {
		$title = $body = '';
	}


	// e.g. "your post", "David's photo", etc.
	$possess_desc = t('%s <!item_type!>');

	if($params['type'] == NOTIFY_MAIL) {
		logger('notification: mail');
		$subject = 	sprintf( t('[Red:Notify] New mail received at %s'),$sitename);

		$preamble = sprintf( t('%1$s, %2$s sent you a new private message at %3$s.'),$recip['channel_name'], $sender['xchan_name'],$sitename);
		$epreamble = sprintf( t('%1$s sent you %2$s.'),'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]', '[zrl=$itemlink]' . t('a private message') . '[/zrl]');
		$sitelink = t('Please visit %s to view and/or reply to your private messages.');
		$tsitelink = sprintf( $sitelink, $siteurl . '/mail/' . $params['item']['id'] );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '/mail/' . $params['item']['id'] . '">' . $sitename . '</a>');
		$itemlink = $siteurl . '/mail/' . $params['item']['id'];
	}

	if($params['type'] == NOTIFY_COMMENT) {
//		logger("notification: params = " . print_r($params, true), LOGGER_DEBUG);

		$itemlink =  $params['link'];


		// ignore like/unlike activity on posts - they probably require a sepearate notification preference

		if(array_key_exists('item',$params) && (! visible_activity($params['item'])))
			return;

		$parent_mid = $params['parent_mid'];

		// Check to see if there was already a notify for this post.
		// If so don't create a second notification
		
		$p = null;
		$p = q("select id from notify where link = '%s' and uid = %d limit 1",
			dbesc($params['link']),
			intval($recip['channel_id'])
		);
		if($p) {
			logger('notification: comment already notified');
			pop_lang();
			return;
		}
	

		// if it's a post figure out who's post it is.

		$p = null;

		if($params['otype'] === 'item' && $parent_mid) {
			$p = q("select * from item where mid = '%s' and uid = %d limit 1",
				dbesc($parent_mid),
				intval($recip['channel_id'])
			);
		}

		xchan_query($p);


		$item_post_type = item_post_type($p[0]);
//		$private = $p[0]['item_private'];
		$parent_id = $p[0]['id'];

		//$possess_desc = str_replace('<!item_type!>',$possess_desc);

		// "a post"
		$dest_str = sprintf(t('%1$s, %2$s commented on [zrl=%3$s]a %4$s[/zrl]'),
			$recip['channel_name'],
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$itemlink,
			$item_post_type);

		// "George Bull's post"
		if($p)
			$dest_str = sprintf(t('%1$s, %2$s commented on [zrl=%3$s]%4$s\'s %5$s[/zrl]'),
				$recip['channel_name'],
				'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
				$itemlink,
				$p[0]['author']['xchan_name'],
				$item_post_type);
		
		// "your post"
		if($p[0]['owner']['xchan_name'] == $p[0]['author']['xchan_name'] && ($p[0]['item_flags'] & ITEM_WALL))
			$dest_str = sprintf(t('%1$s, %2$s commented on [zrl=%3$s]your %4$s[/zrl]'),
				$recip['channel_name'],
				'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
				$itemlink,
				$item_post_type);

		// Some mail softwares relies on subject field for threading.
		// So, we cannot have different subjects for notifications of the same thread.
		// Before this we have the name of the replier on the subject rendering 
		// differents subjects for messages on the same thread.

		$subject = sprintf( t('[Red:Notify] Comment to conversation #%1$d by %2$s'), $parent_id, $sender['xchan_name']);
		$preamble = sprintf( t('%1$s, %2$s commented on an item/conversation you have been following.'), $recip['channel_name'], $sender['xchan_name']); 
		$epreamble = $dest_str; 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
	}

	if($params['type'] == NOTIFY_WALL) {
		$subject = sprintf( t('[Red:Notify] %s posted to your profile wall') , $sender['xchan_name']);

		$preamble = sprintf( t('%1$s, %2$s posted to your profile wall at %3$s') , $recip['channel_name'], $sender['xchan_name'], $sitename);
		
		$epreamble = sprintf( t('%1$s, %2$s posted to [zrl=%3$s]your wall[/zrl]') ,
			$recip['channel_name'], 
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 
		
		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_TAGSELF) {

		$p = null;
		$p = q("select id from notify where link = '%s' and uid = %d limit 1",
			dbesc($params['link']),
			intval($recip['channel_id'])
		);
		if($p) {
			logger('enotify: tag: already notified about this post');
			pop_lang();
			return;
		}
	
		$subject =	sprintf( t('[Red:Notify] %s tagged you') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s, %2$s tagged you at %3$s') , $recip['channel_name'], $sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s, %2$s [zrl=%3$s]tagged you[/zrl].') ,
			$recip['channel_name'],
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_POKE) {

		$subject =	sprintf( t('[Red:Notify] %1$s poked you') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s, %2$s poked you at %3$s') , $recip['channel_name'], $sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s, %2$s [zrl=%2$s]poked you[/zrl].') ,
			$recip['channel_name'], 
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$params['link']); 

		$subject = str_replace('poked', t($params['activity']), $subject);
		$preamble = str_replace('poked', t($params['activity']), $preamble);
		$epreamble = str_replace('poked', t($params['activity']), $epreamble);

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_TAGSHARE) {
		$subject =	sprintf( t('[Red:Notify] %s tagged your post') , $sender['xchan_name']);
		$preamble = sprintf( t('%1$s, %2$s tagged your post at %3$s') , $recip['channel_name'],$sender['xchan_name'], $sitename);
		$epreamble = sprintf( t('%1$s, %2$s tagged [zrl=%3$s]your post[/zrl]') ,
			$recip['channel_name'],
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]',
			$itemlink); 

		$sitelink = t('Please visit %s to view and/or reply to the conversation.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_INTRO) {
		$subject = sprintf( t('[Red:Notify] Introduction received'));
		$preamble = sprintf( t('%1$s, you\'ve received an new connection request from \'%2$s\' at %3$s'), $recip['channel_name'], $sender['xchan_name'], $sitename); 
		$epreamble = sprintf( t('%1$s, you\'ve received [zrl=%2$s]a new connection request[/zrl] from %3$s.'),
			$recip['channel_name'],
			$itemlink,
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]'); 
		$body = sprintf( t('You may visit their profile at %s'),$sender['xchan_url']);

		$sitelink = t('Please visit %s to approve or reject the connection request.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_SUGGEST) {
		$subject = sprintf( t('[Red:Notify] Friend suggestion received'));
		$preamble = sprintf( t('%1$s, you\'ve received a friend suggestion from \'%2$s\' at %3$s'), $recip['channel_name'], $sender['xchan_name'], $sitename); 
		$epreamble = sprintf( t('%1$s, you\'ve received [zrl=%2$s]a friend suggestion[/zrl] for %3$s from %4$s.'),
			$recip['channel_name'],
			$itemlink,
			'[zrl=' . $params['item']['url'] . ']' . $params['item']['name'] . '[/zrl]',
			'[zrl=' . $sender['xchan_url'] . ']' . $sender['xchan_name'] . '[/zrl]'); 
									
		$body = t('Name:') . ' ' . $params['item']['name'] . "\n";
		$body .= t('Photo:') . ' ' . $params['item']['photo'] . "\n";
		$body .= sprintf( t('You may visit their profile at %s'),$params['item']['url']);

		$sitelink = t('Please visit %s to approve or reject the suggestion.');
		$tsitelink = sprintf( $sitelink, $siteurl );
		$hsitelink = sprintf( $sitelink, '<a href="' . $siteurl . '">' . $sitename . '</a>');
		$itemlink =  $params['link'];
	}

	if($params['type'] == NOTIFY_CONFIRM) {

	}

	if($params['type'] == NOTIFY_SYSTEM) {
		
	}

	$h = array(
		'params'    => $params, 
		'subject'   => $subject,
		'preamble'  => $preamble, 
		'epreamble' => $epreamble, 
		'body'      => $body, 
		'sitelink'  => $sitelink,
		'tsitelink' => $tsitelink,
		'hsitelink' => $hsitelink,
		'itemlink'  => $itemlink
	);
		
	call_hooks('enotify',$h);

	$subject   = $h['subject'];
	$preamble  = $h['preamble'];
	$epreamble = $h['epreamble'];
	$body      = $h['body'];
	$sitelink  = $h['sitelink'];
	$tsitelink = $h['tsitelink'];
	$hsitelink = $h['hsitelink'];
	$itemlink  = $h['itemlink']; 


	require_once('include/html2bbcode.php');	

	do {
		$dups = false;
		$hash = random_string();
        $r = q("SELECT `id` FROM `notify` WHERE `hash` = '%s' LIMIT 1",
			dbesc($hash));
		if(count($r))
			$dups = true;
	} while($dups == true);


	$datarray = array();
	$datarray['hash']   = $hash;
	$datarray['name']   = $sender['xchan_name'];
	$datarray['url']    = $sender['xchan_url'];
	$datarray['photo']  = $sender['xchan_photo_s'];
	$datarray['date']   = datetime_convert();
	$datarray['aid']    = $recip['channel_account_id'];
	$datarray['uid']    = $recip['channel_id'];
	$datarray['link']   = $itemlink;
	$datarray['parent'] = $parent_mid;
	$datarray['type']   = $params['type'];
	$datarray['verb']   = $params['verb'];
	$datarray['otype']  = $params['otype'];
 	$datarray['abort']  = false;

	$datarray['item'] = $params['item'];
 
	call_hooks('enotify_store', $datarray);

	if($datarray['abort']) {
		pop_lang();
		return;
	}


	// create notification entry in DB

	$r = q("insert into notify (hash,name,url,photo,date,aid,uid,link,parent,type,verb,otype)
		values('%s','%s','%s','%s','%s',%d,%d,'%s','%s',%d,'%s','%s')",
		dbesc($datarray['hash']),
		dbesc($datarray['name']),
		dbesc($datarray['url']),
		dbesc($datarray['photo']),
		dbesc($datarray['date']),
		intval($datarray['aid']),
		intval($datarray['uid']),
		dbesc($datarray['link']),
		dbesc($datarray['parent']),
		intval($datarray['type']),
		dbesc($datarray['verb']),
		dbesc($datarray['otype'])
	);

	$r = q("select id from notify where hash = '%s' and uid = %d limit 1",
		dbesc($hash),
		intval($recip['channel_id'])
	);
	if($r)
		$notify_id = $r[0]['id'];
	else {
		logger('notification not found.');
		pop_lang();
		return;
	}

	$itemlink = $a->get_baseurl() . '/notify/view/' . $notify_id;
	$msg = str_replace('$itemlink',$itemlink,$epreamble);

	// wretched hack, but we don't want to duplicate all the preamble variations and we also don't want to screw up a translation

	if(($a->language === 'en' || (! $a->language)) && strpos($msg,', '))
		$msg = substr($msg,strpos($msg,', ')+1);	

	$r = q("update notify set msg = '%s' where id = %d and uid = %d limit 1",
		dbesc($msg),
		intval($notify_id),
		intval($datarray['uid'])
	);
		

	// send email notification if notification preferences permit

	require_once('bbcode.php');
	if((intval($recip['channel_notifyflags']) & intval($params['type'])) || $params['type'] == NOTIFY_SYSTEM) {

		logger('notification: sending notification email');


		$textversion = strip_tags(html_entity_decode(bbcode(stripslashes(str_replace(array("\\r", "\\n"), array( "", "\n"), $body))),ENT_QUOTES,'UTF-8'));

		$htmlversion = bbcode(stripslashes(str_replace(array("\\r","\\n"), array("","<br />\n"),$body)));


		// use $_SESSION['zid_override'] to force zid() to use 
		// the recipient address instead of the current observer

		$_SESSION['zid_override'] = $recip['channel_address'] . '@' . get_app()->get_hostname();
		$_SESSION['zrl_override'] = z_root() . '/channel/' . $recip['channel_address'];
		
		$textversion = zidify_links($textversion);
		$htmlversion = zidify_links($htmlversion);

		// unset when done to revert to normal behaviour

		unset($_SESSION['zid_override']);
		unset($_SESSION['zrl_override']);


		$datarray = array();
		$datarray['banner']       = $banner;
		$datarray['product']      = $product;
		$datarray['preamble']     = $preamble;
		$datarray['sitename']     = $sitename;
		$datarray['siteurl']      = $siteurl;
		$datarray['type']         = $params['type'];
		$datarray['parent']       = $params['parent_mid'];
		$datarray['source_name']  = $sender['xchan_name'];
		$datarray['source_link']  = $sender['xchan_url'];
		$datarray['source_photo'] = $sender['xchan_photo_s'];
		$datarray['uid']          = $recip['channel_id'];
		$datarray['username']     = $recip['channel_name'];
		$datarray['hsitelink']    = $hsitelink;
		$datarray['tsitelink']    = $tsitelink;
		$datarray['hitemlink']    = '<a href="' . $itemlink . '">' . $itemlink . '</a>';
		$datarray['titemlink']    = $itemlink;
		$datarray['thanks']       = $thanks;
		$datarray['site_admin']   = $site_admin;
		$datarray['title']        = stripslashes($title);
		$datarray['htmlversion']  = $htmlversion;
		$datarray['textversion']  = $textversion;
		$datarray['subject']      = $subject;
		$datarray['headers']      = $additional_mail_header;
		$datarray['email_secure'] = false;

		call_hooks('enotify_mail', $datarray);

		// Default to private - don't disclose message contents over insecure channels (such as email)
		// Might be interesting to use GPG,PGP,S/MIME encryption instead
		// but we'll save that for a clever plugin developer to implement

		$private_activity = false;

		if(! $datarray['email_secure']) {
			switch($params['type']) {
				case NOTIFY_WALL:
				case NOTIFY_TAGSELF:
				case NOTIFY_POKE:
				case NOTIFY_COMMENT:
					if(! $private)
						break;
					$private_activity = true;
				case NOTIFY_MAIL:
					$datarray['textversion'] = $datarray['htmlversion'] = $datarray['title'] = '';
					$datarray['subject'] = preg_replace('/' . preg_quote(t('[Red:Notify]')) . '/','$0*',$datarray['subject']);
					break;
				default:
					break;
			}
		}

		if($private_activity 
			&& intval(get_pconfig($datarray['uid'],'system','ignore_private_notifications'))) {
			pop_lang();
			return;
		}		

		// load the template for private message notifications
		$tpl = get_markup_template('email_notify_html.tpl');
		$email_html_body = replace_macros($tpl,array(
			'$banner'       => $datarray['banner'],
			'$product'      => $datarray['product'],
			'$preamble'     => $datarray['preamble'],
			'$sitename'     => $datarray['sitename'],
			'$siteurl'      => $datarray['siteurl'],
			'$source_name'  => $datarray['source_name'],
			'$source_link'  => $datarray['source_link'],
			'$source_photo' => $datarray['source_photo'],
			'$username'     => $datarray['to_name'],
			'$hsitelink'    => $datarray['hsitelink'],
			'$hitemlink'    => $datarray['hitemlink'],
			'$thanks'       => $datarray['thanks'],
			'$site_admin'   => $datarray['site_admin'],
			'$title'		=> $datarray['title'],
			'$htmlversion'	=> $datarray['htmlversion'],	
		));
		
		// load the template for private message notifications
		$tpl = get_markup_template('email_notify_text.tpl');
		$email_text_body = replace_macros($tpl,array(
			'$banner'       => $datarray['banner'],
			'$product'      => $datarray['product'],
			'$preamble'     => $datarray['preamble'],
			'$sitename'     => $datarray['sitename'],
			'$siteurl'      => $datarray['siteurl'],
			'$source_name'  => $datarray['source_name'],
			'$source_link'  => $datarray['source_link'],
			'$source_photo' => $datarray['source_photo'],
			'$username'     => $datarray['to_name'],
			'$tsitelink'    => $datarray['tsitelink'],
			'$titemlink'    => $datarray['titemlink'],
			'$thanks'       => $datarray['thanks'],
			'$site_admin'   => $datarray['site_admin'],
			'$title'		=> $datarray['title'],
			'$textversion'	=> $datarray['textversion'],	
		));

//		logger('text: ' . $email_text_body);

		// use the EmailNotification library to send the message

		enotify::send(array(
			'fromName'             => $sender_name,
			'fromEmail'            => $sender_email,
			'replyTo'              => $sender_email,
			'toEmail'              => $recip['account_email'],
			'messageSubject'       => $datarray['subject'],
			'htmlVersion'          => $email_html_body,
			'textVersion'          => $email_text_body,
			'additionalMailHeader' => $datarray['headers'],
		));
	}

	pop_lang();

}


class enotify {
	/**
	 * Send a multipart/alternative message with Text and HTML versions
	 *
	 * @param fromName			name of the sender
	 * @param fromEmail			email fo the sender
	 * @param replyTo			replyTo address to direct responses
	 * @param toEmail			destination email address
	 * @param messageSubject	subject of the message
	 * @param htmlVersion		html version of the message
	 * @param textVersion		text only version of the message
	 * @param additionalMailHeader	additions to the smtp mail header
	 */
	static public function send($params) {

		$fromName = email_header_encode(html_entity_decode($params['fromName'],ENT_QUOTES,'UTF-8'),'UTF-8'); 
		$messageSubject = email_header_encode(html_entity_decode($params['messageSubject'],ENT_QUOTES,'UTF-8'),'UTF-8');
		
		// generate a mime boundary
		$mimeBoundary   =rand(0,9)."-"
				.rand(10000000000,9999999999)."-"
				.rand(10000000000,9999999999)."=:"
				.rand(10000,99999);

		// generate a multipart/alternative message header
		$messageHeader =
			$params['additionalMailHeader'] .
			"From: $fromName <{$params['fromEmail']}>\n" . 
			"Reply-To: $fromName <{$params['replyTo']}>\n" .
			"MIME-Version: 1.0\n" .
			"Content-Type: multipart/alternative; boundary=\"{$mimeBoundary}\"";

		// assemble the final multipart message body with the text and html types included
		$textBody	=	chunk_split(base64_encode($params['textVersion']));
		$htmlBody	=	chunk_split(base64_encode($params['htmlVersion']));
		$multipartMessageBody =
			"--" . $mimeBoundary . "\n" .					// plain text section
			"Content-Type: text/plain; charset=UTF-8\n" .
			"Content-Transfer-Encoding: base64\n\n" .
			$textBody . "\n" .
			"--" . $mimeBoundary . "\n" .					// text/html section
			"Content-Type: text/html; charset=UTF-8\n" .
			"Content-Transfer-Encoding: base64\n\n" .
			$htmlBody . "\n" .
			"--" . $mimeBoundary . "--\n";					// message ending

		// send the message
		$res = mail(
			$params['toEmail'],	 							// send to address
			$messageSubject,								// subject
			$multipartMessageBody,	 						// message body
			$messageHeader									// message headers
		);
		logger("notification: enotify::send returns " . $res, LOGGER_DEBUG);
	}
}

