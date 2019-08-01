( function ( $, mw, OO ) {
	'use strict';

	var $votedLabel = $( '<span></span>' )
		.addClass( 'mw-thanks-flow-thanked mw-ui-quiet' );

	mw.votes.voted.cookieName = 'flow-voted';  
	mw.votes.voted.attrName = 'data-flow-id';

	function findPostAuthorFromVoteLink( $thankLink ) {
		// We can't use 'closest' directly because .flow-author is a cousin
		// of $thankLink rather than its ancestor
		return $( $thankLink.findWithParent( '< .flow-post .flow-author a.mw-userlink' )[ 0 ] ).text().trim();
	}
 
	function reloadVotedState() {
		$( 'a.mw-votes-flow-vote-link' ).each( function ( idx, el ) {
			var $thankLink = $( el ), 
				author = findPostAuthorFromVoteLink( $thankLink );
			if ( mw.votes.voted.contains( $thankLink.closest( '.flow-post' ) ) ) {
				mw.votes.getUserGender( author )
					.done( function ( recipientGender ) {
						$thankLink.before(
							$votedLabel
								.clone()
								.append(
									mw.msg( 'votes-button-voted', mw.user, recipientGender )
								)
						);
						$thankLink.remove();
					} );
			}
		} ); 
	}
 
	function sendflowvotes( $thankLink ) { 
		( new mw.Api() ).postWithToken( 'csrf', {
			action: 'flowvote',
			postid: $thankLink.closest( '.flow-post' ).attr( mw.votes.voted.attrName )
		} )
			.then( 
				// Success
				function () {
					var author = findPostAuthorFromVoteLink( $thankLink );
					// Get the user who was voted (for gender purposes) 
					return mw.votes.getUserGender( author );
				},
				// Failure 
				function ( errorCode ) {
					switch ( errorCode ) {
						case 'ratelimited':
							OO.ui.alert( mw.msg( 'votes-error-ratelimited', mw.user ) );
							break;
						default:
							OO.ui.alert( mw.msg( 'votes-error-undefined', errorCode ) ); 
					}
				}
			)
			.then( function ( recipientGender ) {
				var $voteUserLabel = $votedLabel.clone();
				$voteUserLabel.append(
					mw.msg( 'votes-button-voted', mw.user, recipientGender )
				);
				mw.votes.voted.push( $thankLink.closest( '.flow-post' ) );
				$thankLink.before( $voteUserLabel ); 
				$thankLink.remove();
			} ); 
	}
 
	if ( $.isReady ) {
		// This condition is required for soft-reloads
		// to also trigger the reloadVotedState
		reloadVotedState(); 
	} else {
		$( reloadVotedState );
	}

	// .on() is needed to make the button work for dynamically loaded posts
	$( '.flow-board' ).on( 'click', 'a.mw-votes-flow-vote-link', function ( e ) {
		var $thankLink = $( this );
		e.preventDefault();
		sendflowvotes( $thankLink );
	} );

}( jQuery, mediaWiki, OO ) );
