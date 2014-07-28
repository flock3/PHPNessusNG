<?php
/**
 * php-nessus-api
 *
 * PHP Version 5
 *
 * @category Library
 * @package  php-nessus-api
 * @author   Leon Jacobs <@leonjza>
 * @license  MIT
 * @link     @leonjza
 */

namespace Nessus;

/**
 * This class will handle all off the Nessus API related functions
 *
 * @category Library
 * @package  php-nessus-api
 * @author   Leon Jacobs <@leonjza>
 * @license  MIT
 * @link     @leonjza
 */
class NessusInterface
{

    public $token = null;
    private $validate_cert = false;

    /**
     * Instantiate the instance
     *
     * @param string $url      The host to which we should connect.
     * @param string $port     The port to which we should connect.
     * @param string $username The username
     * @param string $password The that would be used.
     *
     * @return void
     */
    public function __construct($url, $port, $username, $password)
    {

        // Check that we have a valid URL here.
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid URL for NessusInterface Object', 1);
        }

        // Check that we have a valid port here.
        if (!is_numeric($port) || ( 0 > $port ) || ( $port > 65535 )) {
            throw new \Exception('Invalid port for NessusInterface Object', 1);
        }

        // Prepare the full url
        $this->url = rtrim($url, '/') . ':' . $port;
        $this->username = $username;
        $this->password = $password;

        // Perform the login and set the token that will be used.
        $this->login();
    }

    /**
     * Class deconstructor used once all references to this Class is cleared. We want to log out cleanly.
     *
     * @return void
     */
    public function __destruct()
    {

        $this->logout();
    }

    /**
     * Check a Requests Response if it was OK
     *
     * @param string $result The result from a cURL request
     *
     * @return void
     */
    private function checkResponse($response)
    {

        // Parse the XML to check the status and read the error if required
        if ($response->status <> 'OK') {

            throw new \Exception('Error Processing Request. Error was: ' . $response->contents, 1);
        }
    }

    /**
     * Generate a random sequence number betwee 1 and 65535. This is used for API call synchronization checks.
     *
     * @return int
     */
    private function setSequence()
    {

        return $this->sequence = rand(1, 65535);
    }

    /**
     * Check that the returned sequence number matched the sequence that was sent.
     *
     * @param string $sequence The received sequence number from the API return
     *
     * @return void
     */
    private function checkSequence($sequence)
    {

        if ($sequence <> $this->sequence) {

            throw new \Exception(
                'Out of sequence request calling ' . $this->call . '. Got #$sequence instead of #' . $this->sequence,
                1
            );
        }
    }

    /**
     * Log API requests to the Applications General Log
     *
     * @return void
     */
    private function logRequest()
    {

        // This can be configured to do anything you like really.
        return;
    }

    /**
     * Check that the returned sequence number matched the sequence that was sent.
     *
     * @param array  $fields   An array with arguements that accompany the endpoint
     * @param string $endpoint The API endpoint that should be called
     *
     * @return XML containing the endpoint response
     */
    private function askNessus($endpoint, $fields = array())
    {

        // Prepare the full API call URL
        $this->call = $this->url . $endpoint;

        // Set a sequence number for later checking as well as the JSON flag,
        // added to the already requested fields
        $fields = array_merge(
            $fields, array(
                'token' => $this->token,
                'seq' => $this->setSequence(),
                'json' => 1
            )
        );

        // Configure the options we want to use for this \Request
        $options = array(
            'verify' => $this->validate_cert
        );

        // Attempt the request. Should a \Requests internal fail, catch and rethrow
        // a modified extemption
        try {

            $response = \Requests::post($this->call, array(), $fields, $options);

        } catch (\Exception $e) {

            throw new \Exception('Error Processing API Request to ' . $this->call . ': ' . $e->getMessage(), 1);
        }

        // Ensure that the request was successfull
        if (!$response->success)
            throw new \Exception('Unsuccessfull Request to ' . $this->call . ': HTTP Code ' . $response->status_code, 1);

        // Prepare the response body for some checks
        $body = json_decode($response->body)->reply;

        // Check the response Sequence Number
        $this->checkSequence($body->seq);

        // Ensure that the response was OK
        $this->checkResponse($body);

        // Return the response contents
        return $body->contents;
    }

    /**
     * Login to the Nessus Server preserving the token in this->token
     *
     * @return void
     */
    private function login()
    {

        //set POST variables
        $fields = array(
            'login'     =>$this->username,
            'password'  =>$this->password
        );

        // ask Nessus
        $response = $this->askNessus('/login', $fields);

        // Set the session token
        $this->token = $response->token;
    }

    /**
     * Log out of the scanner, effectively destroying the token
     *
     * @return void
     */
    private function logout()
    {

        // ask Nessus
        $response = $this->askNessus('/logout');

        // Unset the session token
        $this->token = null;
    }

    /**
     * Retreive a list of all the reports in the scanner.
     *
     * @return An array containing the report list
     */
    public function reportList()
    {

        // ask Nessus
        $response = $this->askNessus('/report/list');

        // Prepare the return array
        $values = array();
        foreach ($response->reports->report as $report) {

            $values['reports'][$report->name]['status'] = $report->status;
            $values['reports'][$report->name]['readableName'] = $report->readableName;
            $values['reports'][$report->name]['timestamp'] = $report->timestamp;
        }

        // Return what we get
        return $values;
    }

    /**
     * Retreive technical details about the scanner such as Server Version etc.
     *
     * @return An array containing the server details
     */
    public function feed()
    {

        // ask Nessus
        $response = $this->askNessus('/feed');

        $values= array (
            'feed' => $response->feed,
            'nessus_type' => $response->nessus_type,
            'server_version' => $response->server_version,
            'web_server_version' => $response->web_server_version,
            'nessus_ui_version' => $response->nessus_ui_version,
            'expiration' => $response->expiration,
            'msp' => $response->msp,
            'loaded_plugin_set' => $response->loaded_plugin_set,
            'expiration_time' => $response->expiration_time,
            'plugin_rules' => $response->plugin_rules,
            'report_email' => $response->report_email,
            'tags' => $response->tags,
            'diff' => $response->diff,
            'multi_scanner' => $response->multi_scanner,
        );

        // Return what we got
        return $values;
    }

    /**
     * Retreive a list of configured policies for the scanner.
     *
     * @return An array containing the policy names and numerica references
     */
    public function policyList()
    {

        // ask Nessus
        $response = $this->askNessus('/policy/list');

        //##ENHANCEMENT: Lots more information available here. Should maybe make a seperate details() call.
        $values = array();
        foreach ($response->policies->policy as $policy)
            $values['policies'][$policy->policyid] = $policy->policyname;

        //Return what we got
        return $values;
    }

    /**
     * Retreive a list of the current running scans
     *
     * @return An array with policy uuid's and their details
     */
    public function scanList()
    {

        // ask Nessus
        $response = $this->askNessus('/scan/list');

        $values = array();
        foreach ($response->scans->scanList->scan as $scan) {

            $values[$scan->uuid]['completion_current']    = $scan->completion_current;
            $values[$scan->uuid]['completion_total']      = $scan->completion_total;
            $values[$scan->uuid]['readablename']          = $scan->readableName;
            $values[$scan->uuid]['status']                = $scan->status;
            $values[$scan->uuid]['start_time']            = $scan->start_time;
        }

        //Return what we get
        return $values;
    }

    /**
     * Retreive a list of all the scan templates
     *
     * @return An array with template names and their details
     */
    public function templateList()
    {

        // ask Nessus
        $response = $this->askNessus('/scan/template/list2')->templates;

        // Prepare a empty response array
        $values = array();

        // Check that there are templates defined
        if (!isset($response->template))
            return $values;

        // Continue to work with the response array
        foreach ($response->template as $template) {

            $values[$template->name]['policy_id']       = $template->policy_id;
            $values[$template->name]['name']            = $template->name;
            $values[$template->name]['rrules']          = $template->rRules;
            $values[$template->name]['readablename']    = $template->readableName;
            $values[$template->name]['starttime']       = $template->startTime;
            $values[$template->name]['target']          = $template->target;
            $values[$template->name]['owner']           = $template->owner;
        }

        // Return what we get
        return $values;
    }

    /**
     * Schedule a new scan to be run
     *
     * @param string $template_name A name for the scheduled scan
     * @param int    $policy_id     The policy id to be used.
     * @param string $target        A newline seperated list of Subnets to scan
     * @param string $starttime     The time the scan should start
     * @param string $freq          Optionally, a frequency of the scan.
     *
     * @return A array confirming the scans schedule request.
     */
    public function newScanTemplate($template_name, $policy_id, $target, $starttime, $freq = 'FREQ=ONETIME')
    {

        // Prepare the arguements
        $fields = array(
            'template_name' => $template_name,
            'rRules'        => $freq,
            'startTime'     => $starttime,
            'policy_id'     => $policy_id,
            'target'        => $target
        );

        // ask Nessus
        $response = $this->askNessus('/scan/template/new', $fields)->template;

        // Nessus returns what it understood, along whith a identier.
        $values['response'] = array(

            'name' => $response->name,
            'policy_id' => $response->policy_id,
            'readableName' => $response->readableName,
            'owner' => $response->owner,
            'target' => $response->target,
            'rRules' => $response->rRules,
            'startTime' => $response->startTime
        );

        //Return what we got
        return $values;
    }

    /**
     * Pause a scan
     *
     * @param string $uuid The scan UUID that will be paused
     *
     * @return A array confirming the scans pause request.
     */
    public function scanPause($uuid)
    {

        // Prepare the arguements
        $fields = array(
            'scan_uuid' => $uuid,
        );

        // ask Nessus
        $response = $this->askNessus('/scan/pause', $fields)->scan;

        // Prepare the response
        $values['response'] = array(

            'readableName' => $response->readableName,
            'start_time' => $response->start_time,
            'status' => $response->status,
            'name' => $response->name,
            'uuid' => $response->uuid,
            'shared' => $response->shared,
            'user_permissions' => $response->user_permissions,
            'default_permisssions' => $response->default_permisssions,
            'owner' => $response->owner,
            'owner_id' => $response->owner_id,
            'last_modification_date' => $response->last_modification_date,
            'creation_date' => $response->creation_date,
            'type' => $response->type,
            'id' => $response->id
        );

        //Return what we got
        return $values;
    }

    /**
     * Resume a scan
     *
     * @param string $uuid The scan UUID that will be resumed
     *
     * @return A array confirming the scans resume request.
     */
    public function scanResume($uuid)
    {

        //set POST variables
        $fields = array(
            'scan_uuid' => $uuid,
        );

        // ask Nessus
        $response = $this->askNessus('/scan/resume', $fields)->scan;

        // Prepare the response
        $values['response'] = array(

            'readableName' => $response->readableName,
            'start_time' => $response->start_time,
            'status' => $response->status,
            'name' => $response->name,
            'uuid' => $response->uuid,
            'shared' => $response->shared,
            'user_permissions' => $response->user_permissions,
            'default_permisssions' => $response->default_permisssions,
            'owner' => $response->owner,
            'owner_id' => $response->owner_id,
            'last_modification_date' => $response->last_modification_date,
            'creation_date' => $response->creation_date,
            'type' => $response->type,
            'id' => $response->id
        );

        //Return what we got
        return $values;
    }

    /**
     * Stop a scan
     *
     * @param string $uuid The scan UUID that will be stopped
     *
     * @return A array confirming the scans stop request.
     */
    public function scanStop($uuid)
    {

        //set POST variables
        $fields = array(
            'scan_uuid' => $uuid,
        );

        // ask Nessus
        $response = $this->askNessus('/scan/stop', $fields)->scan;

        // Prepare the response
        $values['response'] = array(

            'readableName' => $response->readableName,
            'start_time' => $response->start_time,
            'status' => $response->status,
            'name' => $response->name,
            'uuid' => $response->uuid,
            'shared' => $response->shared,
            'user_permissions' => $response->user_permissions,
            'default_permisssions' => $response->default_permisssions,
            'owner' => $response->owner,
            'owner_id' => $response->owner_id,
            'last_modification_date' => $response->last_modification_date,
            'creation_date' => $response->creation_date,
            'type' => $response->type,
            'id' => $response->id
        );

        //Return what we got
        return $values;
    }

    /**
     * Delete a scan template.
     *
     * @param string $template_name The scan UUID that will be deleted
     *
     * @return A array confirming the scans delete request.
     */
    public function templateDelete($template_name)
    {

        //set POST variables
        $fields = array(
            'template'  => $template_name,
        );

        // ask Nessus
        $response = $this->askNessus('/scan/template/delete', $fields)->template;

        $values['response'] = array(

            'name' => $response->name,
            'policy_id' => $response->policy_id,
            'readableName' => $response->readableName,
            'owner' => $response->owner,
            'target' => $response->target,
            'rRules' => $response->rRules,
            'startTime' => $response->startTime
        );

        //Return what we got
        return $values;
    }

    /**
     * Launch a scan template now.
     *
     * @param string $template_name The scan UUID that will be launched
     *
     * @return A array confirming the scans launch request.
     */
    public function templateLaunch($template_name)
    {

        //set POST variables
        $fields = array(
            'template'  => $template_name,
        );

        // ask Nessus
        $response = $this->askNessus('/scan/template/launch', $fields)->scan;

        $values['response'] = array(

            'start_time' => $response->start_time,
            'status' => $response->status,
            'name' => $response->name,
            'uuid' => $response->uuid,
            'owner' => $response->owner,
            'type' => $response->type,
            'id' => $response->id
        );

        //Return what we got
        return $values;
    }

    /**
     * Query the servers load
     *
     * @return A array confirming the scans launch request.
     */
    public function serverLoad()
    {

        // ask Nessus
        $response = $this->askNessus('/server/load');

        // Prepare a response
        $values = array(
            'platform' => $response->platform,
            'num_scans' => $response->load->num_scans,
            'num_sessions' => $response->load->num_scans,
            'num_hosts' => $response->load->num_scans,
            'num_tcp_sessions' => $response->load->num_scans,
            'loadavg' => $response->load->num_scans
        );

        //Return what we got
        return $values;
    }
}


