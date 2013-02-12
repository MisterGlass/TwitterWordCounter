<?php

/* Load required lib files. */
session_start();
require_once('twitteroauth/twitteroauth.php'); //OAuth Library
require_once('config.php'); //Environment variables for OAuth
require_once('stopWords.php'); //Common junk words. List from http://norm.al/2009/04/14/list-of-english-stop-words/

/* If redirect is requested, redirect to twitter to start OAuth process */
if(isset($_GET['redirect']) && $_GET['redirect']==1)	{
	$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET); //Build TwitterOAuth object with client credentials.
	$request_token = $connection->getRequestToken(OAUTH_CALLBACK); //Get temporary credentials.
	$_SESSION['oauth_token'] = $token = $request_token['oauth_token']; //Save temporary credentials to session.
	$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
	 
	if ($connection->http_code == 200) { //Build authorize URL and redirect user to Twitter.
		$url = $connection->getAuthorizeURL($token);
		header('Location: ' . $url);
	}
	else	{ //If connection failed don't display authorization link.
		$content = 'Could not connect to Twitter. Refresh the page or try again later.'; //Show notification if something went wrong.
	}
}

/* If access tokens are not available prompt user for login. */
elseif (empty($_SESSION['oauth_token']) ) {
    $content = 'This will list the most common words you use on twitter. To use, you must <a href="?redirect=1"><img src="./images/lighter.png" alt="Sign in with Twitter"/></a> don\'t worry, I\'ll only use it to check your status updates.';
}

/* If tokens in session & request dont match, throw an error. */
elseif (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
	$content = 'There was an issue connecting to twitter. please try again later';
}

/* If we have access tokens, then we can talk to twitter and do our thing */
else	{

	//Connect to twitter with oauth token
	$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
	//Get user access token
	$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
	//Connect to twitter with user tokens
	$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
	//Destroy the session - we have everythign we need, we dont want their access tokens anymore
	session_destroy();
	
	$wordCounts = array();
	$statuses = $connection->get('statuses/user_timeline', array('count' => 200));
	foreach($statuses as $status)	{
		// Handy explode and remove special chars from http://stackoverflow.com/questions/790596/split-a-text-into-single-words
		$words = preg_split('/((^\p{P}+)|(\p{P}*\s+\p{P}*)|(\p{P}+$))/', $status->text, -1, PREG_SPLIT_NO_EMPTY);
		
		foreach($words as $word)	{
			if(!in_array($word, $stopwords))	{
				if(isset($wordCounts[$word])) $wordCounts[$word]++;
				else $wordCounts[$word] = 1;
			}
		}
	}
	
	
	arsort($wordCounts); //Sort words in descending order
	$wordsContent = '';
	$i = 1;
	foreach($wordCounts as $word=>$count)	{
		$wordsContent .= "<div>$i. $word: $count</div>";
		$i++;
	}
	
	$content = 'The most common words you\'ve used on twitter (in your last 200 tweets): '.$wordsContent;
}
?>
<html><head></head><?php echo $content; ?><body></body></html>