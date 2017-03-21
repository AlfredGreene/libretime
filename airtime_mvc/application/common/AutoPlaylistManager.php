<?php

class AutoPlaylistManager {
    /**
     * @var int how often, in seconds, to check for and ingest new podcast episodes
     */
    private static $_AUTOPLAYLIST_POLL_INTERVAL_SECONDS = 60;  // 10 minutes
    
    /**
     * Check whether $_AUTOPLAYLIST_POLL_INTERVAL_SECONDS have passed since the last call to
     * buildAutoPlaylist
     *
     * @return bool true if $_AUTOPLAYLIST_POLL_INTERVAL_SECONDS has passed since the last check
     */
    public static function hasAutoPlaylistPollIntervalPassed() {
        Logging::info("Checking autoplaylist poll");
        $lastPolled = Application_Model_Preference::getAutoPlaylistPollLock();
        return empty($lastPolled) || (microtime(true) > $lastPolled + self::$_AUTOPLAYLIST_POLL_INTERVAL_SECONDS);
    }

     /**
     * Find all shows with autoplaylists who have yet to have their playlists built and added to the schedule
     *
     */
    public static function buildAutoPlaylist() {
            // Starting a session
            Zend_Session::start();
	        Logging::info("Checking to run Auto Playlist");
            $autoPlaylists = static::_upcomingAutoPlaylistShows();
            foreach ($autoPlaylists as $autoplaylist) {
       	    // creates a ShowInstance object to build the playlist in from the ShowInstancesQuery Object     
	        $si = new Application_Model_ShowInstance($autoplaylist->getDbId());
	        $playlistid = $si->GetAutoPlaylistId();
            Logging::info("Scheduling $playlistid");
            // call the addPlaylist to show function and don't check for user permission to avoid call to non-existant user object
            $si->addPlaylistToShow($playlistid, false);
            $si->setAutoPlaylistBuilt(true);
            }
            Application_Model_Preference::setAutoPlaylistPollLock(microtime(true));
	        Zend_Session::stop();
    }

    /**
     * Find all show instances starting in the next hour with autoplaylists not yet added to the schedule
     *
     * @return PropelObjectCollection collection of ShowInstance objects
     *                                that have unbuilt autoplaylists
     */
    protected static function _upcomingAutoPlaylistShows() {
	    //setting now so that past shows aren't referenced
            $now = new DateTime("now", new DateTimeZone("UTC"));
	    // only build playlists for shows that start up to an hour from now
	    $future = clone $now;
	    $future->add(new DateInterval('PT1H'));
	    
        return CcShowInstancesQuery::create()
            ->filterByDbStarts($now,Criteria::GREATER_THAN) 
          ->filterByDbStarts($future,Criteria::LESS_THAN)
            ->useCcShowQuery('a', 'left join')
                ->filterByDbHasAutoPlaylist(true)
            ->endUse()
            ->filterByDbAutoPlaylistBuilt(false)
            ->find();
    }
}
