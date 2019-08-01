# Extension
MediaWiki Extension

If Extension:Thanks is already installed in your wiki along wih StructuredDiscussions, replace the previously installed Extension:Thanks with this one.Save the contents in 'Thanks' directory in 'Extension' folder of the wiki.
Run update.php to create table 'V1'.
Also run composer.


Some changes to be done in your wiki's flow--> 

1. Replace 'flow-thank-link' with 'flow-vote-link'
   and 'flow-thank-link-title' with 'flow-vote-link-title'.(mostly in i18n files)

2.In line 486, 531 of Flow\includes\Formatter\RivisionFormatter.php ->
  $wgThanksGivenToBots --> $wgVotesGivenToBots

3.In line 876, 880 of Flow\includes\UrlGenerator.php, replace 'flow-thank-link' with 'flow-vote-link' 
  and 'flow-thank-link-title' with 'flow-vote-link-title'.

4.In files which are in flow\handlebars\compiled\ folder ,
  replace 'mw-thanks-flow-thank-link' with 'mw-votes-flow-vote-link'.
