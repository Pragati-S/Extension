<?php
use MediaWiki\MediaWikiServices;
/**
 * Hooks for vote extension
 */
class VotesHooks { 
	/**
	 * Check whether a user is allowed to receive vote or not
	 *
	 * @param User $user Recipient
	 * @return bool true if allowed, false if not
	 */
	protected static function canReceiveVote( User $user ) {
		global $wgVoteGivenToBots;

		if ( $user->isAnon() ) {
			return false;
		}

		if ( !$wgVoteGivenToBots && $user->isBot() ) {
			return false;
		}

		return true;
	}
 
	/**
	 * Creates either a vote link or voted span based on users session
	 * @param int $id Revision or log ID to generate the vote element for.
	 * @param User $recipient User who receives vote notification.
	 * @param string $type Either 'revision' or 'log'. 
	 * @return string
	 */
	protected static function generateVoteElement( $id, $recipient, $type = 'revision' ) {
		global $wgUser;
		// Check if the user has already voted for this revision or log entry.
		
		$sessionKey = ( $type === 'revision' ) ? $id : $type . $id;
		if ( $wgUser->getRequest()->getSessionData( "votes-voted-$sessionKey" ) ) { 
			return Html::element(
				'span',
				[ 'class' => 'mw-votes-voted' ], 
				wfMessage( 'votes-voted', $wgUser, $recipient->getName() )->text()
			);
		}

		$genderCache = MediaWikiServices::getInstance()->getGenderCache();
		// Add 'vote' link
		$tooltip = wfMessage( 'votes-vote-tooltip' )
				->params( $wgUser->getName(), $recipient->getName() )
				->text();

		$subpage = ( $type === 'revision' ) ? '' : 'Log/';
		return Html::element(
			'a',
			[
				'class' => 'mw-votes-vote-link',
				'href' => SpecialPage::getTitleFor( 'Thanks', $subpage . $id )->getFullURL(),
				'title' => $tooltip,
				'data-' . $type . '-id' => $id,
				'data-recipient-gender' => $genderCache->getGenderOf( $recipient->getName(), __METHOD__ ),
			],
			wfMessage( 'votes-vote', $wgUser, $recipient->getName() )->text()
		);
	}

	/**
	 * Add Votes events to Echo
	 *
	 * @param array &$notifications array of Echo notifications 
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['edit-vote'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-edit-vote',
		];

		if ( class_exists( Flow\FlowPresentationModel::class ) ) {
			$notifications['flow-vote'] = [
				'category' => 'edit-vote',
				'group' => 'positive',
				'section' => 'message',
				'presentation-model' => 'EchoFlowVotesPresentationModel',
				'bundle' => [
					'web' => true,
					'expandable' => true,
				],
			]; 
		}

		$icons['votes'] = [
			'path' => [
				'ltr' => 'Thanks/vl.svg',
				'rtl' => 'Thanks/vr.svg'
			]
		];

		return true;
	}

	/**
	 * Add user to be notified on echo event
	 * @param EchoEvent $event The event.
	 * @param User[] &$users The user list to add to.
	 * @return bool
	 */
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			
			case 'flow-vote':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['voted-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['voted-user-id']; 
				$recipient = User::newFromId( $recipientId );
				$users[$recipientId] = $recipient;
				break;
		}
		return true;
	}

	/**
	 * Handler for LocalUserCreated hook
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 * @return bool
	 */
	public static function onAccountCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for Votes.
		if ( !$autocreated ) {
			$user->setOption( 'echo-subscriptions-email-edit-vote', true );
			$user->saveSettings();
		} 
		return true;
	}
    /**
	 * Handler for GetLogTypesOnUser.
	 * So users can just type in a username for target and it'll work.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/GetLogTypesOnUser
	 * @param string[] &$types The list of log types, to add to.
	 * @return bool
	 */
	public static function onGetLogTypesOnUser( array &$types ) {
		$types[] = 'votes'; 
		return true;
	}

	/**
	 * Handler for BeforePageDisplay.  Inserts javascript to enhance vote
	 * links from static urls to in-page dialogs along with reloading
	 * the previously voted state.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out OutputPage object
	 * @param Skin $skin The skin in use.
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		$title = $out->getTitle();
		// Add to Flow boards.
		if ( $title instanceof Title && $title->hasContentModel( 'flow-board' ) ) {
			$out->addModules( 'ext.votes.flowvote' );
		}
		return true;
	}

	/**
	 * Conditionally load API module 'flowvote' depending on whether or not
	 * Flow is installed.
	 *
	 * @param ApiModuleManager $moduleManager Module manager instance
	 * @return bool
	 */ 
	public static function onApiMainModuleManager( ApiModuleManager $moduleManager ) {
		if ( class_exists( 'FlowHooks' ) ) {
			$moduleManager->addModule(
				'flowvote',
				'action',
				'ApiFlowVote'
			);
		}
		return true;
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification.
	 *
	 * @param EchoEvent $event The event being notified.
	 * @param string &$bundleString Determines how the notification should be bundled.
	 * @return bool True for success
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'flow-vote':
				$bundleString = 'flow-vote';
				$postId = $event->getExtraParam( 'post-id' );
				if ( $postId ) {
					$bundleString .= $postId;
				}
				break;
		}
		return true;
	}

	/**
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LogEventsListLineEnding
	 * @param LogEventsList $page The log events list.
	 * @param string &$ret The lineending HTML, to modify.
	 * @param DatabaseLogEntry $entry The log entry.
	 * @param string[] &$classes CSS classes to add to the line.
	 * @param string[] &$attribs HTML attributes to add to the line.
	 * @throws ConfigException
	 */
	public static function onLogEventsListLineEnding(
		LogEventsList $page, &$ret, DatabaseLogEntry $entry, &$classes, &$attribs
	) {
		global $wgUser; 
 
		// Don't vote if anonymous or blocked
		if ( $wgUser->isAnon() || $wgUser->isBlocked() || $wgUser->isBlockedGlobally() ) {
			return;
		}

		// Make sure this log type is whitelisted.
		$logTypeWhitelist = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'VotesLogTypeWhitelist' );
		if ( !in_array( $entry->getType(), $logTypeWhitelist ) ) {
			return;
		} 

		// Don't vote if no recipient,
		// or if recipient is the current user or unable to receive vote.
		// Don't check for deleted revision (this avoids extraneous queries from Special:Log).
		$recipient = $entry->getPerformer();
		if ( !$recipient
			|| $recipient->getId() === $wgUser->getId()
			|| !self::canReceiveVote( $recipient )
		) {
			return;
		}
 
		// Create vote link either for the revision (if there is an associated revision ID)
		// or the log entry.
		$type = $entry->getAssociatedRevId() ? 'revision' : 'log';
		$id = $entry->getAssociatedRevId() ? $entry->getAssociatedRevId() : $entry->getId();
    	$thankLink = self::generateVoteElement( $id, $recipient, $type );

		// Add parentheses to match what's done with Votes in revision lists and diff displays.
		$ret .= ' ' . wfMessage( 'parentheses' )->rawParams( $thankLink )->escaped();
	}
	
	/**
	 * Creates the necessary database table when the user runs
	 * maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function addTable( $updater ) {
		$dbt = $updater->getDB()->getType();
	
		if ( $dbt === 'sqlite' ) {
			$dbt = 'mysql';
		}
		$file = __DIR__ . "/../sql/v.$dbt";
		if ( file_exists( $file ) ) {
			$updater->addExtensionTable( 'V1', $file );
		} else {
			throw new MWException( "Votes does not support $dbt." );
		}
	}
}
