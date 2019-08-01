<?php
/**
 * API module to send Flow votes notifications
 *
 * This API does not prevent sending votes using post IDs that refer to topic
 * titles, though Vote buttons are only shown for comments in the UI.
 *
 * @ingroup API
 * @ingroup Extensions
 */

use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Exception\FlowException;
use Flow\Model\PostRevision;
use Flow\Model\UUID;

class ApiFlowVote extends ApiVote {
	public function execute() {
		$user = $this->getUser();
		$this->dieOnBadUser( $user );

		$params = $this->extractRequestParams();

		try {
			$postId = UUID::create( $params['postid'] );
		} catch ( FlowException $e ) {
			$this->dieWithError( 'vote-error-invalidpostid', 'invalidpostid' );
		}

		$data = $this->getFlowData( $postId );

		$recipient = $this->getRecipientFromPost( $data['post'] );
		$this->dieOnBadRecipient( $user, $recipient );

		if ( $this->userAlreadyVotedForId( $user, $postId ) ) {
			$this->markResultSuccess( $recipient->getName() );
			return;
		}

		$rootPost = $data['root'];
		$workflowId = $rootPost->getPostId();
		$rawTopicTitleText = Utils::htmlToPlaintext(
			Container::get( 'templating' )->getContent( $rootPost, 'topic-title-html' )
		);
		// Truncate the title text to prevent issues with database storage.
		$topicTitleText = $this->getLanguage()->truncateForDatabase( $rawTopicTitleText, 200 );
		$pageTitle = $this->getPageTitleFromRootPost( $rootPost );

		/** @var PostRevision $post */
		$post = $data['post'];
		$postText = Utils::htmlToPlaintext( $post->getContent() );
		$postText = $this->getLanguage()->truncateForDatabase( $postText, 200 );

		$topicTitle = $this->getTopicTitleFromRootPost( $rootPost );

		$this->GiveVote(
			$user,
			$recipient,
			$postId,
			$workflowId,
			$topicTitleText,
			$pageTitle,
			$postText,
			$topicTitle
		);
	} 

	private function userAlreadyVotedForId( User $user, UUID $id ) {
		return $user->getRequest()->getSessionData( "flow-voted-{$id->getAlphadecimal()}" );
	}

	/**
	 * @param UUID $postId UUID of the post to vote for
	 * @return array containing 'post' and 'root' as keys
	 */
	private function getFlowData( UUID $postId ) {
		$rootPostLoader = Container::get( 'loader.root_post' );

		try {
			$data = $rootPostLoader->getWithRoot( $postId );
		} catch ( FlowException $e ) {
			$this->dieWithError( 'vote-error-invalidpostid', 'invalidpostid' );
		}

		if ( $data['post'] === null ) {
			$this->dieWithError( 'vote-error-invalidpostid', 'invalidpostid' );
		}
		return $data;
	}

	/**
	 * @param PostRevision $post
	 * @return User
	 */
	private function getRecipientFromPost( PostRevision $post ) {
		$recipient = User::newFromId( $post->getCreatorId() );
		if ( !$recipient->loadFromId() ) {
			$this->dieWithError( 'votes-error-invalidrecipient', 'invalidrecipient' );
		}
		return $recipient;
	}

	/**
	 * @param PostRevision $rootPost
	 * @return Title
	 */
	private function getPageTitleFromRootPost( PostRevision $rootPost ) {
		$workflow = Container::get( 'storage' )->get( 'Workflow', $rootPost->getPostId() );
		return $workflow->getOwnerTitle();
	}

	/**
	 * @param PostRevision $rootPost
	 * @return Title
	 */
	private function getTopicTitleFromRootPost( PostRevision $rootPost ) {
		$workflow = Container::get( 'storage' )->get( 'Workflow', $rootPost->getPostId() );
		return $workflow->getArticleTitle();
	}

	/**
	 * @param User $user
	 * @param User $recipient
	 * @param UUID $postId
	 * @param UUID $workflowId
	 * @param string $topicTitleText
	 * @param Title $pageTitle
	 * @param string $postTextExcerpt
	 * @param Title $topicTitle
	 * @throws FlowException
	 * @throws MWException
	 */
	private function GiveVote(
		User $user,
		User $recipient,
		UUID $postId,
		UUID $workflowId,
		$topicTitleText,
		Title $pageTitle,
		$postTextExcerpt,
		Title $topicTitle
	) { 
		$uniqueId = "flow-{$postId->getAlphadecimal()}";
		// Do one last check to make sure we haven't given vote before
		if ( $this->haveAlreadyVoted( $user, $uniqueId ) ) {
			// Pretend the vote was given
			$this->markResultSuccess( $recipient->getName() );
			return;
		}

		// Create the notification via Echo extension
		EchoEvent::create( [
			'type' => 'flow-vote',
			'title' => $pageTitle,
			'extra' => [
				'post-id' => $postId->getAlphadecimal(),
				'workflow' => $workflowId->getAlphadecimal(),
				'voted-user-id' => $recipient->getId(), 
				'topic-title' => $topicTitleText,
				'excerpt' => $postTextExcerpt,
				'target-page' => $topicTitle->getArticleID(),
			],
			'agent' => $user,
		] );

		// And mark the vote in session for a cheaper check to prevent duplicates (Bug 46690).
		$user->getRequest()->setSessionData( "flow-voted-{$postId->getAlphadecimal()}", true );
		// Set success message.
		$this->markResultSuccess( $recipient->getName() );
		$this->logVotes( $user, $recipient, $uniqueId, $postId);
		
		// tp insert commend UUID in database.
		
		
		
		
		
		
		
		
		
		
	}

	public function getAllowedParams() {
		return [
			'postid' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'token' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=flowvote&postid=xyz789&token=123ABC'
				=> 'apihelp-flowvote-example-1',
		]; 
	}
}
