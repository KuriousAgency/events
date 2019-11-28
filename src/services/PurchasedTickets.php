<?php
namespace verbb\events\services;

use verbb\events\Events;
use verbb\events\models\PurchasedTicket;
use verbb\events\records\PurchasedTicketRecord;
use verbb\events\elements\Event;

use Craft;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Assets;
use craft\queue\jobs\ResaveElements;
use craft\commerce\Plugin as Commerce;
use craft\mail\Message;

use yii\base\Component;
use yii\base\Exception;

class PurchasedTickets extends Component
{

    // Properties
    // =========================================================================

    private $_fetchedAllPurchasedTickets = false;
    private $_allPurchasedTickets = [];


    // Public Methods
    // =========================================================================

    public function getAllPurchasedTickets($criteria = []): array
    {
        if (!$this->_fetchedAllPurchasedTickets) {
            $rows = $this->_createPurchasedTicketsQuery()->all();

            foreach ($rows as $row) {
                $this->_allPurchasedTickets[$row['id']] = new PurchasedTicket($row);
            }

            $this->_fetchedAllPurchasedTickets = true;
        }

        if ($criteria) {
            $rows = PurchasedTicketRecord::find()->where($criteria)->all();
            $purchasedTickets = [];

            foreach ($rows as $row) {
                $purchasedTickets[] = new PurchasedTicket($row);
            }

            return $purchasedTickets;
        }

        return $this->_allPurchasedTickets;
    }

    public function getPurchasedTicket($criteria = [])
    {
        $result = PurchasedTicketRecord::find()->where($criteria)->one();

        return new PurchasedTicket($result);
    }

    public function getPurchasedTicketById($id)
    {
        if (isset($this->_allPurchasedTickets[$id])) {
            return $this->_allPurchasedTickets[$id];
        }

        if ($this->_fetchedAllPurchasedTickets) {
            return null;
        }

        $result = $this->_createPurchasedTicketsQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_allPurchasedTickets[$id] = new PurchasedTicket($result);
    }

    public function checkInPurchasedTicket(PurchasedTicket $purchasedTicket)
    {
        $purchasedTicket->checkedIn = true;
        $purchasedTicket->checkedInDate = new \DateTime();

        $record = PurchasedTicketRecord::findOne($purchasedTicket->id);
        $record->checkedIn = $purchasedTicket->checkedIn;
        $record->checkedInDate = $purchasedTicket->checkedInDate;

        $record->save(false);
    }

    public function checkOutPurchasedTicket(PurchasedTicket $purchasedTicket)
    {
        $purchasedTicket->checkedIn = false;
        $purchasedTicket->checkedInDate = null;

        $record = PurchasedTicketRecord::findOne($purchasedTicket->id);
        $record->checkedIn = $purchasedTicket->checkedIn;
        $record->checkedInDate = $purchasedTicket->checkedInDate;

        if ($record->save(false)){
            return true;
        };

    }

    public function getEventsByCustomer($customerId = null)
    {

        if (!$customerId) {
            $userId = Craft::$app->getUser()->getIdentity()->id;
            $customer = Commerce::getInstance()->customers->getCustomerByUserId($userId);
        } else {
            $customer = Commerce::getInstance()->customers->getCustomerById($customerId);
        }
        if (empty($customer)) {
            return null;
        }

        $orderIds = Commerce::getInstance()->orders->getOrdersByCustomer($customer);
        $tickets = PurchasedTicketRecord::find()->where(['orderId'=> array_column($orderIds,'id')])->all();
        $events = Event::find()
            ->id(array_unique(array_column($tickets,'eventId')))
            ->orderBy('startDate asc')
            ->all();
        //$eventQuery = Event::find()->ids();
        
        return $events;
    }


    public function getCustomerTicketsByEvent($customerId = null, $eventId = null): array
    {
        if (!$customerId) {
            $userId = Craft::$app->getUser()->getIdentity()->id;
            $customer = Commerce::getInstance()->customers->getCustomerByUserId($userId);
        } else {
            $customer = Commerce::getInstance()->customers->getCustomerById($customerId);
        }
        if (empty($customer)) {
            return null;
        }
        $orderIds = Commerce::getInstance()->orders->getOrdersByCustomer($customer);
        $query = PurchasedTicketRecord::find()
            ->where(['orderId'=> array_column($orderIds,'id')]);
        if ($eventId) {
            $query = $query->andWhere(['eventId' => $eventId]);
        }
        $rows = $query->all();
        $tickets = [];
        foreach ($rows as $row) {
            $tickets[] = new PurchasedTicket($row);
        }

        return $tickets;

    }

    public function sendTicketEmails($tickets, $order)
    {   
        $renderVariables = [
            'tickets' => $tickets,
            'order' => $order,
            'handle' => 'ticket'
        ];
        
        $subjectString = 'Your DSD Ticket';
        if (count($tickets) > 1) {
            $subjectString .= 's';
        }
        
        $templatePath = '_emails/commerce/tickets/index';
        
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

        // try {
            $pdf = Events::$plugin->getPdf()->renderPdf($tickets, $order, $templatePath);
            $tempPath = Assets::tempFilePath('pdf');
            file_put_contents($tempPath, $pdf);

            $filenameFormat = Events::$plugin->getSettings()->ticketPdfFilenameFormat;
            $fileName = $view->renderObjectTemplate($filenameFormat, $order);
            if (!$fileName) {
                $fileName = 'Tickets-' . $order->number;
            }
            $options = ['fileName' => $fileName . '.pdf', 'contentType' => 'application/pdf'];
        // } catch (\Exception $e) {
        //     $error = Craft::t('commerce', 'Email PDF generation error for email “{email}”. Order: “{order}”. PDF Template error: “{message}” {file}:{line}', [
        //         'email' => 'Event Ticket Email',
        //         'order' => $order->getShortNumber(),
        //         'message' => $e->getMessage(),
        //         'file' => $e->getFile(),
        //         'line' => $e->getLine()
        //     ]);
        //     Craft::error($error, __METHOD__);
        //     $view->setTemplateMode($oldTemplateMode);
        //     return false;
        // }

        if ($view->doesTemplateExist($templatePath)) {
            $newEmail = new Message();
            $newEmail->setTo($order->email);
            $newEmail->setFrom(Craft::parseEnv(Craft::$app->systemSettings->getEmailSettings()->fromEmail));
            $newEmail->setSubject($subjectString);
            $newEmail->variables = $renderVariables;
            $body = $view->renderTemplate($templatePath, $renderVariables);
            $newEmail->setHtmlBody($body);
            if ($tempPath) {
                $newEmail->attach($tempPath, $options);
            }
            // Craft::dd($newEmail);
            if (!Craft::$app->getMailer()->send($newEmail)) {
            
                $error = Craft::t('kd', 'Email Error');
    
                Craft::error($error, __METHOD__);
                
                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }

        } else {
            $error = Craft::t('kd', 'Template not found for email with handle “{handle}”.', [
                'handle' => $renderVariables['handle']
            ]);

            Craft::error($error, __METHOD__);
        }
        
        $view->setTemplateMode($oldTemplateMode);
        return true;
    }

    public function sortPurchasedTicketsByCheckInDate(array $tickets)
    {
        // Create sort by checkedInDate if checked in or dateCreated if not
        uasort($tickets, function($a, $b) {
            // both tickets are checked in
                $retval = $b['checkedIn'] <=> $a['checkedIn'];
                if ($retval == 0) {
                    $retval = $b['checkedInDate'] <=> $a['checkedInDate'];
                    if ($retval == 0) {
                        $retval = $a['dateCreated'] <=> $a['dateCreated'];
                        if ($retval == 0) {
                            $retval = $a['ticketSku'] <=> $b['ticketSku'];
                        }   
                   }
                }
                return $retval;
            });
        return $tickets;
    }

    // Private methods
    // =========================================================================

    private function _createPurchasedTicketsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'eventId',
                'ticketId',
                'orderId',
                'lineItemId',
                'ticketSku',
                'checkedIn',
                'checkedInDate',
            ])
            ->from(['{{%events_purchasedtickets}}']);
    }
}