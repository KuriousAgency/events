<?php
namespace verbb\events\variables;

use verbb\events\Events;
use verbb\events\elements\db\EventQuery;
use verbb\events\elements\db\TicketQuery;
use verbb\events\elements\Event;
use verbb\events\elements\Ticket;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;

use DateTime;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;

class EventsVariable
{
    // Public Methods
    // =========================================================================

    public function getPlugin(): Events
    {
        return Events::$plugin;
    }

    public function getPluginName()
    {
        return Events::$plugin->getPluginName();
    }

    public function getEventTypes(): array
    {
        return Events::$plugin->getEventTypes()->getAllEventTypes();
    }

    public function getEditableEventTypes(): array
    {
        return Events::$plugin->getEventTypes()->getEditableEventTypes();
    }

    public function getTicketTypes(): array
    {
        return Events::$plugin->getTicketTypes()->getAllTicketTypes();
    }

    public function getEditableTicketTypes(): array
    {
        return Events::$plugin->getTicketTypes()->getEditableTicketTypes();
    }

    public function events($criteria = null): EventQuery
    {
        $query = Event::find();

        // Default endDate
        $today = (new DateTime)->format(DateTime::W3C);
        $query->endDate[] = '>=' . $today;

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    public function tickets($criteria = null): TicketQuery
    {
        $query = Ticket::find();

        if ($criteria) {
            Craft::configure($query, $criteria);
        }

        return $query;
    }

    public function purchasedTickets(array $criteria = [])
    {
        return Events::$plugin->getPurchasedTickets()->getAllPurchasedTickets($criteria);
    }

    public function purchasedSortedTickets(array $criteria = [])
    {
        $purchasedTickets = Events::$plugin->getPurchasedTickets()->getAllPurchasedTickets($criteria);
        return Events::$plugin->getPurchasedTickets()->sortPurchasedTicketsByCheckInDate($purchasedTickets);
    }

    public function customerPurchasedTickets(int $customerId = null, int $eventId = null)
    {
        return Events::$plugin->getPurchasedTickets()->getCustomerTicketsByEvent($customerId,$eventId);
    }

    public function customerPurchasedEvents(int $customerId = null, int $eventId = null)
    {
        return Events::$plugin->getPurchasedTickets()->getEventsByCustomer($customerId);
    }

    public function availableTickets($eventId)
    {
        // Backwads compatibility
        return Event::find()->eventId($eventId)->one()->availableTickets();
    }

    public function isTicket(LineItem $lineItem)
    {
        if ($lineItem->purchasable) {
            return (bool)(get_class($lineItem->purchasable) === Ticket::class);
        }

        return false;
    }

    public function getPdfUrl(LineItem $lineItem)
    {
        if ($this->isTicket($lineItem)) {
            $order = $lineItem->order;

            return Events::$plugin->getPdf()->getPdfUrl($order, $lineItem);
        }

        return null;
    }

    public function getOrderPdfUrl(Order $order)
    {
        return Events::$plugin->getPdf()->getPdfUrl($order);
    }

    public function getEventUserPdfUrl(Event $event)
    {
        return Events::$plugin->getPdf()->getPdfUrlForEventAndUser($event);
    }
}
