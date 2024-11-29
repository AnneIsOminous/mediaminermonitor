<?php

/** ******************************************************
* MediaMinerMonitor 1.00
* By Anne Ominous (anne@thephoenixsaga.com)
* Last updated: November 29, 2024
* 
* To use this script: Edit the values in the CONFIGURATION SECTION
* below, and then upload it to a web hosting provider of your choice.
* You can then point any RSS reader app at this file's URL and it should work.
* 
* I used Zapier, which allowed me to configure an email trigger in
* about five minutes on a free account.
* 
* Enjoying the script? Wanna say thanks?
* Consider giving my story a read: https://thephoenixsaga.com
* My social media accounts and stuff are there, too, if you're into that sort of thing.
* 
* This code is provided without warranty under the MIT license. Use at your own risk. 
* Not associated with, endorsed by or vetted by MediaMiner.org.
******************************************************* */

/***************************
 * CONFIGURATION SECTION 
 ************************* */
// The *exact* title of your fic as it appears in the Title field of your Story. 
// If you're looking at the main public page of your Story (as a reader), it'll be everything after the >.
$FIC_NAME = "Phoenix: Reignited Edition";

// The Series of your fandom *exactly* as it appears before the first > in your story's title as it appears when you view it as a READER  
$FANDOM = "Ranma 1/2 Fan Fiction";

// The number of pages deep to search. Each page will pull the 30 most recent MediaMiner chapters edited,
// which is often several months' worth. Recommended to keep this at 3.
$MAX_PAGES = 3;

/** ******************************************************
 * YOU SHOULD NOT NEED TO MODIFY ANYTHING BELOW THIS POINT
******************************************************* */

/**
 * Scrape all the occurrences in $haystack between the search strings $start and $end into an array.
 * @param $haystack string the haystack
 * @param $start string the string that signifies the start of a block of interest
 * @param $end string the string that signifies the end of a block of interest
 * @return array[string] All occurrences between $start and $end in $haystack
 */
function scrapeAllOccurrences($haystack, $start, $end) 
{
    $results = [];
    $startOffset = 0;
    while (true) {
        $startPos = strpos($haystack, $start, $startOffset);        
        if ($startPos === false) { 
            return $results;
        }
        $startCount = $startPos + strlen($start);
       
        if ($end === null) {
            $endCount = strlen($haystack);
        }
        else {
            if ($end >= strlen($haystack) || $end < 0) {
                return $results;
            }
            $endCount = strpos($haystack, $end, $startCount);
            $startOffset = $endCount;
        }
        $results[] = substr($haystack, $startCount, $endCount - $startCount);
    }
    return $results;
}

/**
 * Scrape the first occurrence in $haystack between the search strings $start and $end.
 * @param $haystack string the haystack
 * @param $start string the string that signifies the start of a block of interest
 * @param $end string the string that signifies the end of a block of interest
 * @param $startOffset int the starting offset in $haystack (default 0)
 * @return string The string data in $haystack between the first occurrence of $start and the first occurrence of $end after it.
 */
function scrape($haystack, $start, $end, $startOffset = 0) {
    $startPos = strpos($haystack, $start, $startOffset);        
    $startCount = $startPos + strlen($start);
   
    if ($end === null) {
        $endCount = strlen($haystack);
    }
    else {
        //echo($start . " => " . $end. "<br>");
        if ($end >= strlen($haystack) || $end < 0)
            return "";
        $endCount = strpos($haystack, $end, $startCount);
    }
    return substr($haystack, $startCount, $endCount - $startCount);
}

$comments = [];
$most_recent_time = 0;

// Search the most recent few pages of comments.
for ($page_number = 1; $page_number <= $MAX_PAGES; $page_number++) {
	$html = file_get_contents("https://www.mediaminer.org/forum/categories/story-comments/p" . $page_number);
	if ($html === false) { 
		break;
	}
	
	// Get an array of every discussion on this page	
	$ul = scrape($html, '<ul class="DataList Discussions">', '</ul>');
	$forums = scrapeAllOccurrences($ul, '<li id="Discussion_', "</li>");

	// loop through all the discussions...
	foreach ($forums as $forum) {

		// We found your fic! Yay!

		if (strpos($forum, $FIC_NAME) > 0) {

			// Discussion ID
			$idpos = strpos($forum, '"');
			$id = substr($forum, 0, $idpos);

			// Parse out the chapter name
			$chapter = scrape($forum, $FIC_NAME . " - ", ", " . $FANDOM);
			
			// Most recent commenter's username
			$most_recent_by = scrape($forum, '<span class="MItem LastCommentBy">Most recent by', '</span>');
			$most_recent_by = scrape($most_recent_by, '">', '</a>');
			
			// Forum URL for the comment (goes to the forum, not the chapter itself.)
			$link = scrape($forum, '<div class="Title">', '</a>');
			$link = scrape($link, '<a href="', '">');

			// Number of comments total on this chapter
			$num_comments = intval(scrape($forum, "<span>Comments</span>", "</div>"));

			// Time the last comment was created
			$last = scrape($forum, '<span class="MItem LastCommentDate"><time title="', '</span>');
			$last = scrape($last, 'datetime="', '">');

			// Track what comment was newest overall - should always be the first one but let's be safe. 
			$last_time = strtotime($last);
			if ($last_time > $most_recent_time) {
				$most_recent_time = $last_time;
			}
			
			// Put it together and what've you got? 
			$data = [ "id" => $id, "chapter" => $chapter, "link" => $link, "last_comment_at" => $last, "last_comment_by" => $most_recent_by, "num_comments" => $num_comments];
			$comments[] = $data;
		}
	}
}

// Create the RSS feed object
$rss = new SimpleXMLElement( '<rss version="2.0"  xmlns:atom="http://www.w3.org/2005/Atom"></rss>' );

// Add the channel element to the RSS feed
$channel = $rss->addChild( 'channel' );

// Add the channel data to the RSS feed
$script_url = ($_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);

$channel->addChild( 'title', $FIC_NAME . " Comments on MediaMiner");
$channel->addChild( 'description', $FIC_NAME . " Comments on MediaMiner");
$channel->addChild( 'link', $script_url);
$channel->addChild( "pubDate", date(DateTime::RFC822, $most_recent_time)); // Don't show as updated if no new comments have been created
$channel->addChild( "lastBuildDate", date(DateTime::RFC822, time()));

// Add the items to the RSS feed
foreach ($comments as $item) {
	$rss_item = $channel->addChild( 'item' );
	$rss_item->addChild("guid", $item["link"]);
	$rss_item->addChild( 'title', $item["chapter"] );
	$rss_item->addChild( 'description', "Comments: ". $item["num_comments"] . " Last comment by: " . $item["last_comment_by"] );
	$rss_item->addChild( 'link', $item["link"]);
	$rss_item->addChild( 'pubDate', date(DateTime::RFC822, strtotime($item["last_comment_at"])));
}

// Output the RSS feed as XML
header( 'Content-Type: application/rss+xml' );
echo(str_replace("<channel>", '<channel><atom:link href="' . $script_url . '" type="application/rss+xml" rel="self" />', $rss->asXML()));
?>
