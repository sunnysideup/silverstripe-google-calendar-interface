<?php

namespace Sunnysideup\GoogleCalendarInterface;

use Exception;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;

/*
    see:
    https://developers.google.com/calendar/quickstart/php
    https://github.com/bpineda/google-calendar-connect-class/blob/master/src/Google/Calendar/GoogleCalendarClient.php
 */

class GoogleCalendarInterface extends GoogleInterface
{
    /**
     * @var string
     */
    private static $calendar_id = '';

    private $google_service_calendar;

    private $app_message = '';

    /**
     * Constructor for the class. We call the parent constructor, set
     * scopes array and service calendar instance that we will use.
     */
    public function __construct()
    {
        parent::__construct();
        /*
         * Google calendar service may have the options:
         * Google_Service_Calendar::CALENDAR_READONLY
         * if you want read only access
         * or
         * Google_Service_Calendar::CALENDAR
         * if you want read/write access to the calendar
         */
        $this->scopes = implode(' ', [\Google_Service_Calendar::CALENDAR]);
        $this->google_service_calendar = new \Google_Service_Calendar($this);
    }

    /**
     * Gets a list of all calenders.
     *
     * @return array|bool
     */
    public function getCalendars()
    {
        $results = $this->google_service_calendar->calendarList->listCalendarList();
        if (0 === count($results->getItems())) {
            return [];
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
     *   'start' =>  [
     *                 'dateTime' => '2015-05-28T09:00:00',
     *                 'timeZone' => 'Pacific/Auckland'
     *             ],
     *   'end' =>    [
     *                 '2015-05-28T17:00:00-07:00',
     *                 'timeZone' => Pacific/Auckland
     *             ],
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
     *   ).
     *
     * @param array  $eventAttributes Event attributes array
     * @param string $calendarID      CalendarID - lets you set the calendar if you don't want to use the primary calendar
     *
     * @return Google_Service_Calendar_Event
     */
    public function addCalendarEvent($eventAttributes, $calendarID = 'primary')
    {
        $event = new \Google_Service_Calendar_Event($eventAttributes);

        try {
            $event = $this->google_service_calendar->events->insert($calendarID, $event);
        } catch (Exception $exception) {
            return false;
        }

        return $event;
    }

    /*
     * see the above function for list of attributes that can be passed in the $eventAttributes array
     * @param array $eventAttributes Event attributes array
     * @param string $eventID EventID - the id of the event you want to update
     * @param string $calendarID CalendarID - lets you set the calendar if you don't want to use the primary calendar
     * @return Google_Service_Calendar_Event
     */
    public function updateCalendarEvent($eventAttributes, $eventID, $calendarID = 'primary')
    {
        try {
            $event = $this->google_service_calendar->events->get($calendarID, $eventID);
        } catch (Exception $e) {
            //should we provide some actual feedback here or just return false?
            return false;
        }

        if (isset($eventAttributes['summary'])) {
            $event->setSummary($eventAttributes['summary']);
        }

        if (isset($eventAttributes['location'])) {
            $event->setLocation($eventAttributes['location']);
        }

        if (isset($eventAttributes['description'])) {
            $event->setLocation($eventAttributes['description']);
        }

        if (isset($eventAttributes['start'])) {
            $startTime = new Google_Service_Calendar_EventDateTime();
            $startTime->setDateTime($eventAttributes['start']['dateTime']);
            $startTime->setTimeZone($eventAttributes['start']['timeZone']);
            $event->setStart($startTime);
        }

        if (isset($eventAttributes['end'])) {
            $endTime = new Google_Service_Calendar_EventDateTime();
            $endTime->setDateTime($eventAttributes['end']['dateTime']);
            $endTime->setTimeZone($eventAttributes['end']['timeZone']);
            $event->setEnd($endTime);
        }

        return $this->google_service_calendar->events->update($calendarID, $eventID, $event);
    }

    /*
    * @param string $eventID EventID - the id of the event you want to update
    * @param string $calendarID CalendarID - lets you set the calendar if you don't want to use the primary calendar
    * @return Google_Service_Calendar_Event
    */
    public function getCalendarEvent($eventID, $calendarID = 'primary')
    {
        try {
            $event = $this->google_service_calendar->events->get($calendarID, $eventID);
        } catch (Exception $e) {
            //should we provide some actual feedback here or just return false?
            return false;
        }

        if ('cancelled' === $event->getStatus()) {
            return false;
        }

        return $event;
    }

    public function deleteCalendarEvent($eventID, $calendarID = 'primary')
    {
        try {
            $event = $this->google_service_calendar->events->delete($calendarID, $eventID);
        } catch (Exception $e) {
            return false;
        }

        return $event;
    }

    /**
     * Get our selected calendar events as an array or false if it's empty.
     *
     * @param string $calendarID CalendarID - lets you set the calendar if you don't want to use the primary calendar
     *
     * @return array|bool
     */
    public function getCalendarEvents($calendarID = 'primary')
    {
        $optParams = [
            'maxResults' => 10,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => date('c'),
        ];
        $results = $this->google_service_calendar->events->listEvents($calendarID, $optParams);
        if (0 === count($results->getItems())) {
            return [];
        }

        return $this->createEventsArray($results);
    }

    /**
     * Creates the events array.
     *
     * @param array $eventsResult events result array
     *
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
            $events_array[] = ['start' => $start,
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
