<?php
use MediaWiki\MediaWikiServices;
/**
 * Base API module for Votes
 *
 * @ingroup API
 * @ingroup Extensions
 */
abstract class ApiVote extends ApiBase {
	protected function dieOnBadUser( User $user ) {
		if ( $user->isAnon() ) {
			$this->dieWithError( 'votes-error-notloggedin', 'notloggedin' );
		} elseif ( $user->pingLimiter( 'votes-notification' ) ) {
			$this->dieWithError( [ 'votes-error-ratelimited', $user->getName() ], 'ratelimited' );
		} elseif ( $user->isBlocked() ) {
			$this->dieBlocked( $user->getBlock() );
		} elseif ( $user->isBlockedGlobally() ) {
			$this->dieBlocked( $user->getGlobalBlock() );
		}
	}

	protected function dieOnBadRecipient( User $user, User $recipient ) {
		global $wgVoteGivenToBots;

		if ( $user->getId() === $recipient->getId() ) {
			$this->dieWithError( 'votes-error-invalidrecipient-self', 'invalidrecipient' );
		} elseif ( !$wgVoteGivenToBots && $recipient->isBot() ) {
			$this->dieWithError( 'votes-error-invalidrecipient-bot', 'invalidrecipient' );
		}
	}

	protected function markResultSuccess( $recipientName ) {
		$this->getResult()->addValue( null, 'result', [
			'success' => 1,
			'recipient' => $recipientName,
		] );
	}

	/**
	 * This checks the log_search data.
	 *
	 * @param User $Voter The user sending the vote.
	 * @param string $uniqueId The identifier for the vote.
	 * @return bool Whether vote has already been given
	 */
	protected function haveAlreadyVoted( User $Voter, $uniqueId ) {
		$dbw = wfGetDB( DB_MASTER );
		$logWhere = ActorMigration::newMigration()->getWhere( $dbw, 'log_user', $Voter );
		return (bool)$dbw->selectRow(
			[ 'log_search', 'logging' ] + $logWhere['tables'],
			[ 'ls_value' ],
			[
				$logWhere['conds'],
				'ls_field' => 'VoteId',
				'ls_value' => $uniqueId,
			],
			__METHOD__,
			[],
			[ 'logging' => [ 'INNER JOIN', 'ls_log_id=log_id' ] ] + $logWhere['joins']
		);
	}

	/**
	 * @param User $user The user giving the vote (and the log entry).
	 * @param User $recipient The target of the vote (and the log entry).
	 * @param string $uniqueId A unique Id to identify the event being voted for, to use
	 *                         when checking for duplicate vote
	 
	 in ManualLogEntry.php-->>
	  public function __construct( $type, $subtype ) {
         $this->type = $type;
         $this->subtype = $subtype;
     }
 
	 
	 
	 */
	protected function logVotes( User $user, User $recipient, $uniqueId, $postId ) {
		global $wgVotesLogging;
		if ( !$wgVotesLogging ) { 
			return;
		}
		//$a=$postid;
		
		
		
		$logEntry = new ManualLogEntry( 'votes', 'vote' );
		$logEntry->setPerformer( $user );
		$logEntry->setRelations( [ 'VoteId' => $uniqueId ] );                                      
		$target = $recipient->getUserPage();
		$logEntry->setTarget( $target );
		
		$logEntry->setComment( $postId );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, 'udp' );
		$dbw = wfGetDB( DB_MASTER );
		$count = 5;
		$s =$dbw->selectRow(
			'v1',
			[ 'COUNT(*) AS NOV' ],
			[ 'UUID'=>$postId ],
			 __METHOD__
		);
	   //$count =$s->NOV; 
		
		
		$dbw->insert(
				'v1',
				[
					'UUID' => $postId,
					'NoV'=>$s->NOV,
					'Xtra'=>$user
				],
				__METHOD__
			);
		
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		// Writes to the Echo database and sometimes log tables.
		return true;
	}
}
