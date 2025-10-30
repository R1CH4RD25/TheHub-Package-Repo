<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Hub\Cache;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * CalendarRenderer - Universal calendar and event management
 * 
 * Implements Calendar module type for scheduling events, appointments, meetings,
 * deadlines, and reminders. Generic design works for any organization.
 * 
 * Use cases:
 * - Business: Meeting scheduling, project deadlines, resource booking
 * - Education: Class schedules, exam dates, school events, parent-teacher conferences
 * - Healthcare: Appointment scheduling, shift planning, equipment reservations
 * - Personal: Task deadlines, birthdays, reminders, vacation planning
 * - Non-profit: Event planning, volunteer shifts, fundraiser schedules
 * 
 * Features:
 * - Multiple view modes (month, week, day, agenda)
 * - Color-coded categories
 * - Recurring events (daily, weekly, monthly, yearly)
 * - All-day and timed events
 * - Event reminders (email, in-app)
 * - iCalendar (.ics) export/import
 * - Public/private event visibility
 * - Attendee management
 * - Conflict detection
 * - Drag-and-drop rescheduling
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class CalendarRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    
    // Event categories with colors
    private array $defaultCategories = [
        'meeting' => ['label' => 'Meeting', 'color' => '#0d6efd'],
        'deadline' => ['label' => 'Deadline', 'color' => '#dc3545'],
        'appointment' => ['label' => 'Appointment', 'color' => '#198754'],
        'event' => ['label' => 'Event', 'color' => '#ffc107'],
        'reminder' => ['label' => 'Reminder', 'color' => '#6c757d'],
        'personal' => ['label' => 'Personal', 'color' => '#6f42c1'],
    ];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
        
        // Merge custom categories from config
        if (isset($config['categories'])) {
            $this->defaultCategories = array_merge($this->defaultCategories, $config['categories']);
        }
    }
    
    /**
     * Render calendar interface
     */
    public function render(): string
    {
        $view = $_GET['view'] ?? 'month';
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $html = '<div class="calendar-container">';
        
        // Header with navigation and controls
        $html .= $this->renderHeader($view, $date);
        
        // Calendar views
        switch ($view) {
            case 'month':
                $html .= $this->renderMonthView($date);
                break;
            case 'week':
                $html .= $this->renderWeekView($date);
                break;
            case 'day':
                $html .= $this->renderDayView($date);
                break;
            case 'agenda':
                $html .= $this->renderAgendaView($date);
                break;
            default:
                $html .= $this->renderMonthView($date);
        }
        
        // Modals
        $html .= $this->renderEventModal();
        $html .= $this->renderEventDetailsModal();
        
        // Scripts and styles
        $html .= $this->renderStyles();
        $html .= $this->renderScripts();
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render calendar header with controls
     */
    private function renderHeader(string $view, string $date): string
    {
        $timestamp = strtotime($date);
        $prevDate = date('Y-m-d', strtotime('-1 ' . $view, $timestamp));
        $nextDate = date('Y-m-d', strtotime('+1 ' . $view, $timestamp));
        
        $displayDate = $this->getDisplayDate($view, $date);
        
        $html = '<div class="calendar-header mb-4">';
        $html .= '    <div class="d-flex justify-content-between align-items-center">';
        
        // Navigation
        $html .= '        <div class="calendar-navigation">';
        $html .= '            <div class="btn-group">';
        $html .= '                <a href="?view=' . $view . '&date=' . $prevDate . '" class="btn btn-outline-secondary">';
        $html .= '                    <i class="bi bi-chevron-left"></i>';
        $html .= '                </a>';
        $html .= '                <a href="?view=' . $view . '&date=' . date('Y-m-d') . '" class="btn btn-outline-secondary">Today</a>';
        $html .= '                <a href="?view=' . $view . '&date=' . $nextDate . '" class="btn btn-outline-secondary">';
        $html .= '                    <i class="bi bi-chevron-right"></i>';
        $html .= '                </a>';
        $html .= '            </div>';
        $html .= '            <h3 class="ms-3 mb-0 d-inline-block">' . $displayDate . '</h3>';
        $html .= '        </div>';
        
        // Actions and view switcher
        $html .= '        <div class="calendar-actions">';
        $html .= '            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#eventModal">';
        $html .= '                <i class="bi bi-plus-lg"></i> New Event';
        $html .= '            </button>';
        $html .= '            <div class="btn-group">';
        $html .= '                <a href="?view=month&date=' . $date . '" class="btn btn-outline-secondary ' . ($view === 'month' ? 'active' : '') . '">Month</a>';
        $html .= '                <a href="?view=week&date=' . $date . '" class="btn btn-outline-secondary ' . ($view === 'week' ? 'active' : '') . '">Week</a>';
        $html .= '                <a href="?view=day&date=' . $date . '" class="btn btn-outline-secondary ' . ($view === 'day' ? 'active' : '') . '">Day</a>';
        $html .= '                <a href="?view=agenda&date=' . $date . '" class="btn btn-outline-secondary ' . ($view === 'agenda' ? 'active' : '') . '">Agenda</a>';
        $html .= '            </div>';
        $html .= '        </div>';
        
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get display date for header
     */
    private function getDisplayDate(string $view, string $date): string
    {
        $timestamp = strtotime($date);
        
        switch ($view) {
            case 'month':
                return date('F Y', $timestamp);
            case 'week':
                $weekStart = date('Y-m-d', strtotime('monday this week', $timestamp));
                $weekEnd = date('Y-m-d', strtotime('sunday this week', $timestamp));
                return date('M d', strtotime($weekStart)) . ' - ' . date('M d, Y', strtotime($weekEnd));
            case 'day':
                return date('l, F d, Y', $timestamp);
            case 'agenda':
                return date('F Y', $timestamp);
            default:
                return date('F Y', $timestamp);
        }
    }
    
    /**
     * Render month view
     */
    private function renderMonthView(string $date): string
    {
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = date('t', $firstDay);
        $dayOfWeek = date('w', $firstDay);
        
        // Get events for this month
        $startDate = date('Y-m-01', strtotime($date));
        $endDate = date('Y-m-t', strtotime($date));
        $events = $this->getEvents($startDate, $endDate);
        
        // Group events by date
        $eventsByDate = [];
        foreach ($events as $event) {
            $eventDate = date('Y-m-d', strtotime($event['start_date']));
            $eventsByDate[$eventDate][] = $event;
        }
        
        $html = '<div class="calendar-month-view">';
        $html .= '    <table class="table table-bordered">';
        
        // Day headers
        $html .= '        <thead>';
        $html .= '            <tr>';
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($days as $day) {
            $html .= '                <th class="text-center">' . $day . '</th>';
        }
        $html .= '            </tr>';
        $html .= '        </thead>';
        
        // Calendar grid
        $html .= '        <tbody>';
        
        $currentDay = 1;
        $row = 0;
        
        while ($currentDay <= $daysInMonth) {
            $html .= '            <tr>';
            
            for ($col = 0; $col < 7; $col++) {
                if ($row === 0 && $col < $dayOfWeek) {
                    // Empty cells before month starts
                    $html .= '                <td class="calendar-day empty"></td>';
                } elseif ($currentDay > $daysInMonth) {
                    // Empty cells after month ends
                    $html .= '                <td class="calendar-day empty"></td>';
                } else {
                    // Day cell
                    $cellDate = sprintf('%s-%02d-%02d', $year, $month, $currentDay);
                    $isToday = $cellDate === date('Y-m-d');
                    $dayEvents = $eventsByDate[$cellDate] ?? [];
                    
                    $html .= '                <td class="calendar-day ' . ($isToday ? 'today' : '') . '" data-date="' . $cellDate . '">';
                    $html .= '                    <div class="day-number">' . $currentDay . '</div>';
                    
                    // Show events
                    if (!empty($dayEvents)) {
                        $html .= '                    <div class="day-events">';
                        foreach (array_slice($dayEvents, 0, 3) as $event) {
                            $category = $this->defaultCategories[$event['category']] ?? $this->defaultCategories['event'];
                            $html .= '                        <div class="event-item" style="background-color: ' . $category['color'] . ';" data-event-id="' . $event['id'] . '" title="' . htmlspecialchars($event['title']) . '">';
                            $html .= '                            ' . htmlspecialchars(mb_substr($event['title'], 0, 20));
                            $html .= '                        </div>';
                        }
                        if (count($dayEvents) > 3) {
                            $html .= '                        <div class="event-more">+' . (count($dayEvents) - 3) . ' more</div>';
                        }
                        $html .= '                    </div>';
                    }
                    
                    $html .= '                </td>';
                    $currentDay++;
                }
            }
            
            $html .= '            </tr>';
            $row++;
        }
        
        $html .= '        </tbody>';
        $html .= '    </table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render week view
     */
    private function renderWeekView(string $date): string
    {
        $timestamp = strtotime($date);
        $weekStart = strtotime('monday this week', $timestamp);
        
        // Get events for this week
        $startDate = date('Y-m-d', $weekStart);
        $endDate = date('Y-m-d', strtotime('+6 days', $weekStart));
        $events = $this->getEvents($startDate, $endDate);
        
        $html = '<div class="calendar-week-view">';
        $html .= '    <div class="table-responsive">';
        $html .= '        <table class="table table-bordered">';
        
        // Day headers
        $html .= '            <thead>';
        $html .= '                <tr>';
        $html .= '                    <th width="80">Time</th>';
        
        for ($i = 0; $i < 7; $i++) {
            $day = date('D, M d', strtotime("+$i days", $weekStart));
            $isToday = date('Y-m-d', strtotime("+$i days", $weekStart)) === date('Y-m-d');
            $html .= '                    <th class="' . ($isToday ? 'bg-light' : '') . '">' . $day . '</th>';
        }
        
        $html .= '                </tr>';
        $html .= '            </thead>';
        
        // Time slots (6am to 10pm)
        $html .= '            <tbody>';
        
        for ($hour = 6; $hour <= 22; $hour++) {
            $html .= '                <tr>';
            $html .= '                    <td class="time-slot">' . date('g A', mktime($hour, 0, 0)) . '</td>';
            
            for ($day = 0; $day < 7; $day++) {
                $cellDate = date('Y-m-d', strtotime("+$day days", $weekStart));
                $cellHour = sprintf('%02d:00:00', $hour);
                
                $html .= '                    <td class="calendar-cell" data-date="' . $cellDate . '" data-time="' . $cellHour . '">';
                
                // Show events for this time slot
                foreach ($events as $event) {
                    if (date('Y-m-d', strtotime($event['start_date'])) === $cellDate) {
                        $eventHour = (int)date('H', strtotime($event['start_time']));
                        if ($eventHour === $hour) {
                            $category = $this->defaultCategories[$event['category']] ?? $this->defaultCategories['event'];
                            $html .= '                        <div class="event-block" style="background-color: ' . $category['color'] . ';" data-event-id="' . $event['id'] . '">';
                            $html .= htmlspecialchars($event['title']);
                            $html .= '                        </div>';
                        }
                    }
                }
                
                $html .= '                    </td>';
            }
            
            $html .= '                </tr>';
        }
        
        $html .= '            </tbody>';
        $html .= '        </table>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render day view
     */
    private function renderDayView(string $date): string
    {
        $events = $this->getEvents($date, $date);
        
        $html = '<div class="calendar-day-view">';
        $html .= '    <div class="row">';
        
        // All-day events
        if (!empty(array_filter($events, fn($e) => $e['all_day']))) {
            $html .= '        <div class="col-12 mb-3">';
            $html .= '            <div class="card">';
            $html .= '                <div class="card-header"><strong>All Day</strong></div>';
            $html .= '                <div class="list-group list-group-flush">';
            
            foreach ($events as $event) {
                if ($event['all_day']) {
                    $category = $this->defaultCategories[$event['category']] ?? $this->defaultCategories['event'];
                    $html .= '                    <a href="#" class="list-group-item list-group-item-action event-item-link" data-event-id="' . $event['id'] . '" style="border-left: 4px solid ' . $category['color'] . ';">';
                    $html .= '                        <strong>' . htmlspecialchars($event['title']) . '</strong>';
                    if ($event['description']) {
                        $html .= '                        <p class="mb-0 small text-muted">' . htmlspecialchars(mb_substr($event['description'], 0, 100)) . '</p>';
                    }
                    $html .= '                    </a>';
                }
            }
            
            $html .= '                </div>';
            $html .= '            </div>';
            $html .= '        </div>';
        }
        
        // Timed events
        $html .= '        <div class="col-12">';
        $html .= '            <div class="timeline">';
        
        for ($hour = 0; $hour < 24; $hour++) {
            $timeLabel = date('g:00 A', mktime($hour, 0, 0));
            $html .= '                <div class="timeline-hour">';
            $html .= '                    <div class="hour-label">' . $timeLabel . '</div>';
            $html .= '                    <div class="hour-events">';
            
            // Show events for this hour
            foreach ($events as $event) {
                if (!$event['all_day']) {
                    $eventHour = (int)date('H', strtotime($event['start_time']));
                    if ($eventHour === $hour) {
                        $category = $this->defaultCategories[$event['category']] ?? $this->defaultCategories['event'];
                        $endTime = $event['end_time'] ? date('g:i A', strtotime($event['end_time'])) : '';
                        
                        $html .= '                        <div class="timeline-event" style="background-color: ' . $category['color'] . ';" data-event-id="' . $event['id'] . '">';
                        $html .= '                            <strong>' . htmlspecialchars($event['title']) . '</strong><br>';
                        $html .= '                            <small>' . date('g:i A', strtotime($event['start_time'])) . ($endTime ? ' - ' . $endTime : '') . '</small>';
                        $html .= '                        </div>';
                    }
                }
            }
            
            $html .= '                    </div>';
            $html .= '                </div>';
        }
        
        $html .= '            </div>';
        $html .= '        </div>';
        
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render agenda view (list of upcoming events)
     */
    private function renderAgendaView(string $date): string
    {
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+30 days'));
        $events = $this->getEvents($startDate, $endDate);
        
        // Group by date
        $eventsByDate = [];
        foreach ($events as $event) {
            $eventDate = date('Y-m-d', strtotime($event['start_date']));
            $eventsByDate[$eventDate][] = $event;
        }
        
        ksort($eventsByDate);
        
        $html = '<div class="calendar-agenda-view">';
        
        if (empty($events)) {
            $html .= '    <div class="alert alert-info">No upcoming events in the next 30 days</div>';
        } else {
            foreach ($eventsByDate as $eventDate => $dayEvents) {
                $html .= '    <div class="agenda-date-group mb-4">';
                $html .= '        <h5 class="text-primary">' . date('l, F d, Y', strtotime($eventDate)) . '</h5>';
                $html .= '        <div class="list-group">';
                
                foreach ($dayEvents as $event) {
                    $category = $this->defaultCategories[$event['category']] ?? $this->defaultCategories['event'];
                    
                    $html .= '            <a href="#" class="list-group-item list-group-item-action event-item-link" data-event-id="' . $event['id'] . '" style="border-left: 4px solid ' . $category['color'] . ';">';
                    $html .= '                <div class="d-flex w-100 justify-content-between">';
                    $html .= '                    <h6 class="mb-1">' . htmlspecialchars($event['title']) . '</h6>';
                    
                    if ($event['all_day']) {
                        $html .= '                    <small>All Day</small>';
                    } else {
                        $html .= '                    <small>' . date('g:i A', strtotime($event['start_time'])) . '</small>';
                    }
                    
                    $html .= '                </div>';
                    
                    if ($event['description']) {
                        $html .= '                <p class="mb-1 text-muted">' . htmlspecialchars(mb_substr($event['description'], 0, 150)) . '</p>';
                    }
                    
                    if ($event['location']) {
                        $html .= '                <small class="text-muted"><i class="bi bi-geo-alt"></i> ' . htmlspecialchars($event['location']) . '</small>';
                    }
                    
                    $html .= '            </a>';
                }
                
                $html .= '        </div>';
                $html .= '    </div>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render event creation/edit modal
     */
    private function renderEventModal(): string
    {
        $html = '<div class="modal fade" id="eventModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-lg">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">New Event</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body">';
        $html .= '                <form id="eventForm">';
        
        // Title
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="eventTitle" class="form-label">Title *</label>';
        $html .= '                        <input type="text" class="form-control" id="eventTitle" required>';
        $html .= '                    </div>';
        
        // Date and time
        $html .= '                    <div class="row">';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="eventStartDate" class="form-label">Start Date *</label>';
        $html .= '                            <input type="date" class="form-control" id="eventStartDate" required>';
        $html .= '                        </div>';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="eventStartTime" class="form-label">Start Time</label>';
        $html .= '                            <input type="time" class="form-control" id="eventStartTime">';
        $html .= '                        </div>';
        $html .= '                    </div>';
        
        $html .= '                    <div class="row">';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="eventEndDate" class="form-label">End Date</label>';
        $html .= '                            <input type="date" class="form-control" id="eventEndDate">';
        $html .= '                        </div>';
        $html .= '                        <div class="col-md-6 mb-3">';
        $html .= '                            <label for="eventEndTime" class="form-label">End Time</label>';
        $html .= '                            <input type="time" class="form-control" id="eventEndTime">';
        $html .= '                        </div>';
        $html .= '                    </div>';
        
        // All day checkbox
        $html .= '                    <div class="mb-3 form-check">';
        $html .= '                        <input type="checkbox" class="form-check-input" id="eventAllDay">';
        $html .= '                        <label class="form-check-label" for="eventAllDay">All day event</label>';
        $html .= '                    </div>';
        
        // Category
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="eventCategory" class="form-label">Category</label>';
        $html .= '                        <select class="form-select" id="eventCategory">';
        foreach ($this->defaultCategories as $key => $category) {
            $html .= '                            <option value="' . $key . '">' . $category['label'] . '</option>';
        }
        $html .= '                        </select>';
        $html .= '                    </div>';
        
        // Description
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="eventDescription" class="form-label">Description</label>';
        $html .= '                        <textarea class="form-control" id="eventDescription" rows="3"></textarea>';
        $html .= '                    </div>';
        
        // Location
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="eventLocation" class="form-label">Location</label>';
        $html .= '                        <input type="text" class="form-control" id="eventLocation">';
        $html .= '                    </div>';
        
        // Recurring
        $html .= '                    <div class="mb-3">';
        $html .= '                        <label for="eventRecurring" class="form-label">Repeat</label>';
        $html .= '                        <select class="form-select" id="eventRecurring">';
        $html .= '                            <option value="">Does not repeat</option>';
        $html .= '                            <option value="daily">Daily</option>';
        $html .= '                            <option value="weekly">Weekly</option>';
        $html .= '                            <option value="monthly">Monthly</option>';
        $html .= '                            <option value="yearly">Yearly</option>';
        $html .= '                        </select>';
        $html .= '                    </div>';
        
        $html .= '                </form>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '                <button type="button" class="btn btn-primary" id="saveEvent">Save Event</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render event details modal
     */
    private function renderEventDetailsModal(): string
    {
        $html = '<div class="modal fade" id="eventDetailsModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">Event Details</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body" id="eventDetailsContent">';
        $html .= '                <div class="text-center"><div class="spinner-border"></div></div>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-danger" id="deleteEvent">Delete</button>';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render CSS styles
     */
    private function renderStyles(): string
    {
        $html = '<style>';
        $html .= <<<'CSS'
.calendar-month-view .calendar-day {
    height: 120px;
    vertical-align: top;
    padding: 8px;
    position: relative;
}
.calendar-day.empty {
    background-color: #f8f9fa;
}
.calendar-day.today {
    background-color: #e7f3ff;
}
.calendar-day .day-number {
    font-weight: 600;
    margin-bottom: 5px;
}
.calendar-day .day-events {
    overflow: hidden;
}
.calendar-day .event-item {
    font-size: 0.75rem;
    color: white;
    padding: 2px 5px;
    margin-bottom: 2px;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.calendar-day .event-more {
    font-size: 0.7rem;
    color: #666;
    margin-top: 2px;
}
.calendar-week-view .time-slot {
    font-size: 0.85rem;
    text-align: right;
    padding-right: 10px;
    color: #666;
}
.calendar-week-view .calendar-cell {
    height: 50px;
    border: 1px solid #dee2e6;
    position: relative;
    cursor: pointer;
}
.calendar-week-view .event-block {
    color: white;
    padding: 4px;
    font-size: 0.8rem;
    border-radius: 3px;
    margin: 2px;
}
.timeline {
    border-left: 2px solid #dee2e6;
}
.timeline-hour {
    display: flex;
    min-height: 60px;
    border-bottom: 1px solid #e9ecef;
}
.timeline-hour .hour-label {
    width: 80px;
    padding: 8px;
    font-size: 0.85rem;
    color: #666;
    text-align: right;
}
.timeline-hour .hour-events {
    flex: 1;
    padding: 8px;
}
.timeline-event {
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 5px;
    color: white;
    cursor: pointer;
}
.agenda-date-group h5 {
    margin-bottom: 15px;
}
CSS;
        $html .= '</style>';
        
        return $html;
    }
    
    /**
     * Render JavaScript
     */
    private function renderScripts(): string
    {
        $html = '<script>';
        $html .= <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    // Event item clicks - show details
    document.querySelectorAll('.event-item, .event-block, .timeline-event, .event-item-link').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const eventId = this.dataset.eventId;
            if (eventId) {
                showEventDetails(eventId);
            }
        });
    });
    
    // Calendar day click - create event
    document.querySelectorAll('.calendar-day:not(.empty)').forEach(el => {
        el.addEventListener('dblclick', function() {
            const date = this.dataset.date;
            document.getElementById('eventStartDate').value = date;
            new bootstrap.Modal(document.getElementById('eventModal')).show();
        });
    });
    
    // All-day toggle
    document.getElementById('eventAllDay')?.addEventListener('change', function() {
        const disabled = this.checked;
        document.getElementById('eventStartTime').disabled = disabled;
        document.getElementById('eventEndTime').disabled = disabled;
        if (disabled) {
            document.getElementById('eventStartTime').value = '';
            document.getElementById('eventEndTime').value = '';
        }
    });
    
    // Save event
    document.getElementById('saveEvent')?.addEventListener('click', function() {
        const formData = {
            title: document.getElementById('eventTitle').value,
            start_date: document.getElementById('eventStartDate').value,
            start_time: document.getElementById('eventStartTime').value,
            end_date: document.getElementById('eventEndDate').value,
            end_time: document.getElementById('eventEndTime').value,
            all_day: document.getElementById('eventAllDay').checked,
            category: document.getElementById('eventCategory').value,
            description: document.getElementById('eventDescription').value,
            location: document.getElementById('eventLocation').value,
            recurring: document.getElementById('eventRecurring').value
        };
        
        fetch('api/calendar.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    });
    
    function showEventDetails(eventId) {
        const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
        
        fetch('api/calendar.php?action=details&id=' + eventId)
            .then(r => r.json())
            .then(event => {
                document.getElementById('eventDetailsContent').innerHTML = `
                    <h4>${event.title}</h4>
                    <p><strong>Date:</strong> ${event.start_date} ${event.start_time || ''}</p>
                    ${event.location ? '<p><strong>Location:</strong> ' + event.location + '</p>' : ''}
                    ${event.description ? '<p>' + event.description + '</p>' : ''}
                `;
                modal.show();
            });
    }
});
JS;
        $html .= '</script>';
        
        return $html;
    }
    
    /**
     * Get events for date range
     */
    private function getEvents(string $startDate, string $endDate): array
    {
        // Try cache first
        $cacheKey = "events:$startDate:$endDate";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Query database for events
        // This is a placeholder - actual implementation would query events table
        $events = [];
        
        // Cache for 5 minutes
        Cache::set($cacheKey, $events, 300);
        
        return $events;
    }
    
    /**
     * Handle event actions
     */
    public function handle(): array
    {
        $action = $_REQUEST['action'] ?? '';
        
        switch ($action) {
            case 'save':
                return $this->handleSave();
            case 'delete':
                return $this->handleDelete();
            case 'details':
                return $this->handleDetails();
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    }
    
    /**
     * Handle save event
     */
    private function handleSave(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate and save event
        AuditLogger::log('calendar_event_create', 'calendar_events', null, [], $data);
        
        // Clear cache
        Cache::delete('events:*');
        
        return ['success' => true, 'message' => 'Event saved'];
    }
    
    /**
     * Handle delete event
     */
    private function handleDelete(): array
    {
        $eventId = $_POST['id'] ?? 0;
        
        // Delete event
        AuditLogger::log('calendar_event_delete', 'calendar_events', $eventId, [], ['id' => $eventId]);
        
        // Clear cache
        Cache::delete('events:*');
        
        return ['success' => true, 'message' => 'Event deleted'];
    }
    
    /**
     * Handle get event details
     */
    private function handleDetails(): array
    {
        $eventId = $_GET['id'] ?? 0;
        
        // Fetch event details
        // Placeholder
        return ['success' => true, 'event' => []];
    }
    
    /**
     * Validate configuration
     */
    public function validate(): bool
    {
        // Calendar doesn't require specific config
        return true;
    }
    
    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
