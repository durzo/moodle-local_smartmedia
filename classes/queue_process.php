<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class for AWS SQS processing operations.
 *
 * @package     local_smartmedia
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_smartmedia;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use Aws\Sqs\SqsClient;

/**
 * Class for AWS SQS processing operations.
 *
 * @package     local_smartmedia
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_process {

    /**
     *
     * @var object Plugin confiuration.
     */
    private $config;

    /**
     *
     * @var \Aws\Sqs\SqsClient SQS client.
     */
    private $client;

    /**
     * Max messages to get from AWS SQS queue per run..
     *
     * @var integer
     */
    private const MAX_MESSAGES = 100;



    /**
     * Class constructor
     */
    public function __construct() {
        $this->config = get_config('local_smartmedia');
    }

    /**
     * Create AWS SQS API client.
     *
     * @param \GuzzleHttp\Handler $handler Optional handler.
     * @return \Aws\Sqs\SqsClient
     */
    public function create_client($handler=null) {
        $connectionoptions = array(
            'version' => 'latest',
            'region' => $this->config->api_region,
            'credentials' => [
                'key' => $this->config->api_key,
                'secret' => $this->config->api_secret
            ]);

        // Allow handler overriding for testing.
        if ($handler != null) {
            $connectionoptions['handler'] = $handler;
        }

        // Only create client if it hasn't already been done.
        if ($this->client == null) {
            $this->client = new SqsClient($connectionoptions);
        }

        return $this->client;
    }


    /**
     * Get pending messages from the AWS SQS queue.
     *
     * @return array $messages The messages retreived from the SQS Queue.
     */
    private function get_queue_messages() : array {
        global $CFG;

        // Get current messages from queue.
        $messages = array();
        $messageparams = array(
            'AttributeNames' => array('All'),
            'MaxNumberOfMessages' => 10,  // 10 is AWS maximum per call.
            'MessageAttributeNames' => array('All'),
            'QueueUrl' => $this->config->sqs_queue_url,
            'VisibilityTimeout' => 60,
            'WaitTimeSeconds' => 5, // To quick and we miss messages, to long and it's slow.
        );

        while (count($messages) < self::MAX_MESSAGES) {
            $result = $this->client->receiveMessage($messageparams);
            $newmessages = $result->get('Messages'); // Number of received messages varies unpredictably.

            if ($newmessages == null || count($newmessages) == 0) {
                // No messages received so end early.
                break;
            }

            // Not only do the number of messages received vary,
            // SQS can also deliver the same message multiple times.
            foreach ($newmessages as $newmessage) {
                $messageid = $newmessage['MessageId'];
                $messagesiteid = $newmessage['MessageAttributes']['siteid']['StringValue'];

                // We could be using the same AWS queue for multiple Moodles,
                // so we only store messages for our Moodle.
                if ($messagesiteid == $CFG->siteidentifier) {
                    $messages[$messageid] = $newmessage;
                }
            }
        }

        return $messages;
    }

    /**
     * Store received SQS queue messages in the DB.
     *
     * @param array $messages THe messages to store.
     */
    private function store_messages(array $messages) : void {
        global $DB;
        $messagerecords = array();

        foreach ($messages as $message) {
            $messagebody = json_decode($message['Body']);
            $record = new \stdClass();
            $record->objectkey = $messagebody->objectkey;
            $record->process = $messagebody->process;
            $record->status = $messagebody->status;
            $record->message = json_encode($messagebody->message);
            $record->senttime = $messagebody->timestamp;
            $record->timecreated = time();

            $messagerecords[] = $record;
        }

        $DB->insert_records('local_smartmedia_queue_msgs', $messagerecords);

    }

    /**
     * Deletes messages from AWS SQS queue.
     *
     * @param array $messages Messages to delete.
     * @return array $results Results of message deletions.
     */
    private function delete_queue_messages(array $messages) : array {
        $result = array();

        foreach ($messages as $message) {
            $deleteparams = array(
                'QueueUrl' => $this->config->sqs_queue_url,
                'ReceiptHandle' => $message['ReceiptHandle']
            );

            $result[] = $this->client->deleteMessage($deleteparams)->get('@metadata');

        }

        return $result;
    }

    /**
     * Process outstanding queue messages.
     *
     * @return int Count of messages processed.
     */
    public function process_queue() : int {
        $this->create_client();

        $messages = $this->get_queue_messages(); // Get current messages from queue
        $this->store_messages($messages); // Store messages in database.
        $this->delete_queue_messages($messages); // Remove messages from queue.

        return count($messages);

    }

}