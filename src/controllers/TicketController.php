<?php
namespace verbb\events\controllers;

use verbb\events\Events;

use Craft;
use craft\web\Controller;
use craft\web\View;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\commerce\elements\Order;

class TicketController extends Controller
{
    // Properties
    // =========================================================================

    protected $allowAnonymous = true;


    // Public Methods
    // =========================================================================

    public function actionCheckin(array $variables = array())
    {
        $this->requireLogin();

        if (!Craft::$app->user->checkPermission('events-checkInTickets')) {
            return $this->redirect('/account'); 
        }

        $url = '/account/event-management/event/';

        $settings = Events::$plugin->getSettings();

        $sku = Craft::$app->request->getParam('sku');

        if (!$sku) {
            $query = UrlHelper::buildQuery(['result' => 'missing-sku']);
            return $this->redirect( $url.'?'.$query); 
        }

        $purchasedTicket = Events::$plugin->getPurchasedTickets()->getPurchasedTicket([
            'ticketSku' => $sku,
        ]);

        if (!$purchasedTicket->id) {
            $query = UrlHelper::buildQuery(['result' => 'invalid-sku']);
            return $this->redirect($url.'?'.$query); 
        }


        if ($purchasedTicket->checkedIn) {
            $query = UrlHelper::buildQuery(['result' => 'double-checkin']);
            return $this->redirect($url.$purchasedTicket->eventId.'?'.$query); 
        }

        Events::$plugin->getPurchasedTickets()->checkInPurchasedTicket($purchasedTicket);
        $query = UrlHelper::buildQuery(['result' => 'checkin-success']);
        return $this->redirect($url.$purchasedTicket->eventId.'?'.$query); 

    }

    public function actionCheckOut()
    {
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();

        $sku = $request->getBodyParam('ticketSku');
        $purchasedTicket = Events::$plugin->getPurchasedTickets()->getPurchasedTicket([
            'ticketSku' => $sku,
        ]);
        
        $url = '/account/event-management/event/';

        if (Events::$plugin->getPurchasedTickets()->checkOutPurchasedTicket($purchasedTicket)){
            $query = UrlHelper::buildQuery(['result' => 'checkout-success']);
            return $this->redirect($url . $purchasedTicket->eventId . '?' . $query); 
        } else {
            $query = UrlHelper::buildQuery(['result' => 'checkout-fail']);
            return $this->redirect($url . $purchasedTicket->eventId . '?'.$query);
        };
    }

    public function actionSearch()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $eventId = $request->getBodyParam('eventId');
        $searchString = $request->getBodyParam('search');
        $url = '/account/event-management/event/';

        if (!$searchString) {
            return $this->redirect($url . $eventId);
        }
        $purchasedTickets = [];

        // Ticket SKU
        $purchasedTicket = Events::$plugin->getPurchasedTickets()->getPurchasedTicket([
            'ticketSku' => $searchString,
        ]);
        if ($purchasedTicket->id) {
            $purchasedTickets[] = $purchasedTicket->ticketSku;
            $query = UrlHelper::buildQuery(['term'=> $searchString,'search' => $purchasedTickets ]);
            return $this->redirect($url . $eventId . '?'.$query);

        }

        $orderIds = Order::find()
            ->reference($searchString)
            ->isCompleted()
            ->ids();

        $tickets = Events::$plugin->getPurchasedTickets()->getAllPurchasedTickets([
            'orderId' => $orderIds,
        ]);

        if(!$tickets) {
            $orderIds = Order::find()
            ->email($searchString)
            ->isCompleted()
            ->ids();

            $tickets = Events::$plugin->getPurchasedTickets()->getAllPurchasedTickets([
                'orderId' => $orderIds,
            ]);
        }
        $tickets = Events::$plugin->getPurchasedTickets()->sortPurchasedTicketsByCheckInDate($tickets);
        foreach ($tickets as $ticket) {
            $purchasedTickets[] = $ticket->ticketSku;
        }
        $query = UrlHelper::buildQuery(['term'=> $searchString,'search' => $purchasedTickets ]);
        return $this->redirect($url . $eventId . '?'.$query);

    }

    // Private Methods
    // =========================================================================

    private function _handleResponse($variables)
    {
        $settings = Events::$plugin->getSettings();

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson($variables);
        }

        $oldMode = Craft::$app->view->getTemplateMode();
        $templateMode = View::TEMPLATE_MODE_CP;
        $template = 'events/check-in';

        if ($settings->checkinTemplate) {
            $templateMode = View::TEMPLATE_MODE_SITE;
            $template = $settings->checkinTemplate;
        }

        $routeVariables = [
            'section' => 'account',
            'type' => 'event-management',
            'subtype' => 'event',
        ];

        $variables = array_merge($variables,$routeVariables);

        Craft::$app->view->setTemplateMode($templateMode);
        $html = Craft::$app->view->renderTemplate($template, $variables);
        Craft::$app->view->setTemplateMode($oldMode);

        return $html;
    }
}