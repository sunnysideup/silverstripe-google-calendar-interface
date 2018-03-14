<?php



class GoogleCalendarInterface extends Google_Client
{

    /**
     * @var String
     */
    private static $application_name = "";

    /**
     * @var String
     */
    private static $credentials_path = "";


    /**
     * @var String
     */
    private static $client_secret_path = "";


    /**
     * @var String
     */
    private static $client_access_type = "offline";


    /**
     * @var String
     */
    private static $time_zone = "Pacific/Auckland";

    /**
     * @var String
     */
    private static $calendar_id = "";

    private $scopes;

    private $google_service_calendar;

    private $app_message = '';

    /**
     * Constructor for the class. We call the parent constructor, set
     * scopes array and service calendar instance that we will use
     */
    function __construct()
    {
        parent::__construct();
        /**
         * Google calendar service may have the options:
         * Google_Service_Calendar::CALENDAR_READONLY
         * if you want read only access
         * or
         * Google_Service_Calendar::CALENDAR
         * if you want read/write access to the calendar
         */
        $this->scopes = implode(' ', array( \Google_Service_Calendar::CALENDAR));
        $this->google_service_calendar = new \Google_Service_Calendar($this);
    }

    /**
     * Class configurator
     * @param null $verification_code Verification code we will use
     * to create our authentication credentials
     * @return bool
     */
    public function config($verification_code = null)
    {
        $base_folder = Director::baseFolder().'/';
        $this->setApplicationName(
            Config::inst()->get('GoogleCalendarInterface', 'application_name')
        );
        $this->setScopes($this->scopes);
        $this->setAuthConfigFile(
            $base_folder . Config::inst()->get('GoogleCalendarInterface', 'client_secret_path')
        );
        $this->setAccessType(
            Config::inst()->get('GoogleCalendarInterface', 'client_access_type')
        );
        $credential_file = $base_folder . Config::inst()->get('GoogleCalendarInterface', 'credentials_path');
        $accessToken = [];

        if (file_exists($credential_file)) {
            $accessToken = json_decode( file_get_contents($credential_file), 1);
        }

        if (! file_exists($credential_file) || isset($accessToken['error'])) {
            if (empty($verification_code)){
                return false;
            }
            $accessToken = $this->fetchAccessTokenWithAuthCode($verification_code);
            file_put_contents($credential_file, json_encode($accessToken) );
            if(isset($accessToken['error'])) {
                return false;
            }
        }

        $this->setAccessToken($accessToken);
        // Refresh the token if it's expired.
        if ($this->isAccessTokenExpired()) {
            $this->refreshToken($this->getRefreshToken());
            file_put_contents($credential_file, $this->getAccessToken());
        }
        return true;
    }

    /**
     * Get error message string
     * @return html
     */
    public function getAuthLink()
    {
        $authUrl = $this->createAuthUrl();
        return '<a href="' . $authUrl . '" target="_blank">Retrieve Verification Code</a>';
    }

    /**
    * Gets a list of all calenders
    * @return array|bool
    */
    public function getCalendars()
    {
        $results = $this->google_service_calendar->calendarList->listCalendarList();
        if (count($results->getItems()) == 0) {
           return array();
        }

        $calendarsArray = [];
        foreach ($results->getItems() as $calendar) {
            $calendarsArray[$calendar->getID()] = $calendar->getSummary();
        }

        return $calendarsArray;
    }

    /**
     * We add an event to the calendar.
     * The array that we pass to the method should be like this:
     * array(
     *   'summary' => 'Event name',
     *   'location' => 'Event address',
     *   'description' => 'Event description',
     *   'start' => '2015-05-28T09:00:00',
     *   'end' => '2015-05-28T17:00:00-07:00',
     *   'attendees' => array(
     *                           array('email' => 'attendee1@example.com'),
     *                           array('email' => 'attendee2@example.com'),
     *   ),
     *   'reminders' => array(
     *       'useDefault' => FALSE,
     *       'overrides' => array(
     *           array('method' => 'email', 'minutes' => 24 * 60),
     *           array('method' => 'popup', 'minutes' => 10),
     *   ),
     *   ),
     *   )
     * @param array $eventAttributes Event attributes array
     * @param string $calendarID CalendarID - lets you set the calendar if you don't want to use the primary calendar
     * @return Google_Service_Calendar_Event
     */
    public function addCalendarEvent($eventAttributes, $calendarID = 'primary')
    {
        $event = new \Google_Service_Calendar_Event($eventAttributes);
        $event = $this->google_service_calendar->events->insert($calendarID, $event);
        return $event;
    }

    /**
    * Get our selected calendar events as an array or false if it's empty
    * @param string $calendarID CalendarID - lets you set the calendar if you don't want to use the primary calendar
    * @return array|bool
    */
    public function getCalendarEvents($calendarID = 'primary')
    {
        $optParams = array(
           'maxResults' => 10,
           'orderBy' => 'startTime',
           'singleEvents' => TRUE,
           'timeMin' => date('c'),
        );
        $results = $this->google_service_calendar->events->listEvents($calendarID, $optParams);
        if (count($results->getItems()) == 0) {
           return array();
        }
        return $this->createEventsArray($results);
    }

    /**
     * Creates the events array
     * @param array $eventsResult events result array
     * @return array
     */
    private function createEventsArray($eventsResult)
    {
        $events_array = [];
        foreach ($eventsResult->getItems() as $event) {
            $start = $event->start->dateTime;
            $end = $event->end->dateTime;
            if (empty($start)) {
                $start = $event->start->date;
            }
            if (empty($end)) {
                $end = $event->end->date;
            }
            $events_array[] = [ 'start' => $start,
                                'end' => $end,
                                'summary' => $event->getSummary(),
                                'location' => $event->getLocation(),
                                'description' => $event->getDescription(),
                                'attendees' => $event->getAttendees(),
            ];
        }
        return $events_array;
    }

}
