<?php

namespace Yahoo\Connectors;

use \Exception;

/**
 * Rally API Connector
 *
 * Simple class for interacting with RallyDev web services
 *
 * @version 2.0
 * @author St. John Johnson <stjohn@yahoo-inc.com>
 * @see http://github.com/yahoo/php-rally-connector
 * @copyright Copyright (c) 2013, Yahoo! Inc.  All rights reserved.
 *
 * Redistribution and use of this software in source and binary forms,
 * with or without modification, are permitted provided that the following
 * conditions are met:
 *
 * - Redistributions of source code must retain the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer.
 *
 * - Redistributions in binary form must reproduce the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer in the documentation and/or other
 *   materials provided with the distribution.
 *
 * - Neither the name of Yahoo! Inc. nor the names of its
 *   contributors may be used to endorse or promote products
 *   derived from this software without specific prior
 *   written permission of Yahoo! Inc.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS
 * IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 * TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class Rally
{
    // Curl Object
    private $curl;
    // Rally's Domain
    private $domain;
    // Just for debugging
    private $debug = false;
    // Some fancy user agent here
    private $agent = 'PHP - Rally Api - 2.0';
    // Current API version
    private $version = 'v2.0';
    // Current Workspace
    private $workspace;
    // Silly object translation
    private $objectTranslation = array(
        'story' => 'hierarchicalrequirement',
        'userstory' => 'hierarchicalrequirement',
        'feature' => 'portfolioitem/feature',
        'initiative' => 'portfolioitem/initiative',
        'theme' => 'portfolioitem/theme',
        'release' => 'release'
    );

    // User object
    protected $user = '';

    // User Security Token
    protected $securityToken;

    /**
     * Project ID in Rally.
     *
     * To get the value go to the Project's Dashboard example URL: https://rally1.rallydev.com/#/xxxxxxxxxxxd/dashboard
     * Copy the x's in the URL above excluding the "d" and that's the Project's ID
     *
     * @var int
     */
    protected $project = null;

    /**
     * Create Rally Api Object
     *
     * @param string $username
     *   The username for Rally
     * @param string $password
     *   The password for Rally (probably hunter2)
     * @param string $domain
     *   Override for Domain to talk to
     */
    public function __construct($username, $password, $domain = 'rally1.rallydev.com')
    {
        $this->domain = $domain;

        $this->curl = curl_init();

        $this->_setopt(CURLOPT_RETURNTRANSFER, true);
        $this->_setopt(
            CURLOPT_HTTPHEADER,
            array(
                'Content-Type: text/javascript'
            )
        );
        $this->_setopt(CURLOPT_VERBOSE, $this->debug);
        $this->_setopt(CURLOPT_USERAGENT, $this->agent);
        $this->_setopt(CURLOPT_HEADER, 0);
        $this->_setopt(CURLOPT_COOKIEFILE, '/tmp/php_rally_cookie_file');
        // Authentication
        $this->_setopt(CURLOPT_USERPWD, "$username:$password");
        $this->_setopt(CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    }

    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * Translate object types
     *
     * This is only really for
     *   story -> hierarchicalrequirement
     *
     * @param string $object
     *   Rally Object Type
     * @return string
     *   Translated Object
     */
    protected function _translate($object)
    {
        $object = strtolower($object);
        if (isset($this->objectTranslation[$object])) {
            return $this->objectTranslation[$object];
        }
        return $object;
    }

    /**
     * Set current workspace
     *
     * @param string $workspace_ref
     *   Workspace URL Reference
     */
    public function setWorkspace($workspace_ref)
    {
        $this->workspace = $workspace_ref;
    }

    /**
     * Generates a reference URL to the Object
     *
     * @param string $object
     *   Rally Object Type
     * @param int $id
     *   Rally Object ID
     * @return string
     *   Proper URL or _ref to use
     */
    public function getRef($object, $id)
    {
        $object = $this->_translate($object);
        $ref = "/{$object}";
        if ($id) {
            $ref .= "/{$id}";
        }
        return $ref;
    }

    /**
     * Finds the objects in Rally allowing a query and search params to be sent into the system search
     *
     * @param $object
     * @param string $query
     * @param string $order
     * @param array $fetch
     * @param int $size
     * @param int $start
     * @return array
     */
    public function find($object, $query = '', $order = '', $fetch = array(), $size = 100, $start = 1)
    {
        $object = $this->_translate($object);
        $params = array(
            'pagesize' => $size,
            'start' => $start
        );

        if (!empty($query)) {
            $params['query'] = $query;
        }

        if (!empty($this->project)) {
            $params['project'] = sprintf("/project/%s", $this->project);
        }

        if (!empty($fetch)) {
            $params['fetch'] = implode(",", $fetch);
        }

        if (!empty($order)) {
            $params['order'] = $order;
        }

        return $this->_get($this->_addWorkspace($object, $params));
    }

    /**
     * Get a Rally object
     *
     * @param string $object
     *   Rally Object Type
     * @param int $id
     *   Rally Object ID
     * @return array
     *   Rally API response
     */
    public function get($object, $id)
    {
        return reset($this->_get($this->_addWorkspace($this->getRef($object, $id))));
    }

    public function getByUrl($url)
    {
        $this->_setopt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->_setopt(CURLOPT_POSTFIELDS, '');
        $this->_setopt(CURLOPT_URL, $url);

        $response = curl_exec($this->curl);

        if (curl_errno($this->curl)) {
            throw new RallyApiException(curl_error($this->curl));
        }

        $info = curl_getinfo($this->curl);

        return $this->_result($response, $info);
    }

    /**
     * Create a Rally object
     *
     * @param string $object
     *   Rally Object Type
     * @param array $params
     *   Fields to create with
     * @return array
     *   Rally API response
     */
    public function create($object, array $params)
    {
        $url = $this->_addWorkspace($this->getRef($object, 'create'));

        $object = $this->_put($url, $params);
        return $object['Object'];
    }

    /**
     * Update a Rally object
     *
     * @param string $object
     *   Rally Object Type
     * @param int $id
     *   Rally Object ID
     * @param array $params
     *   Fields to update
     * @return array
     *   Rally API response
     */
    public function update($object, $id, array $params)
    {
        $url = $this->_addWorkspace($this->getRef($object, $id));

        $object = $this->_post($url, $params);
        return $object['Object'];
    }

    /**
     * Delete a Rally object
     *
     * @param string $object
     *   Rally Object Type
     * @param int $id
     *   Rally Object ID
     * @return bool
     */
    public function delete($object, $id)
    {
        $url = $this->_addWorkspace($this->getRef($object, $id));

        // There are no values that return here
        $this->_delete($this->getRef($url, $id));
        return true;
    }

    /**
     * @param $url
     * @param array $params
     * @return string
     */
    protected function _addWorkspace($url, array $params = array())
    {
        // Add workspace
        if ($this->workspace) {
            $params['workspace'] = $this->workspace;
        }

        // Add params as url
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Perform a HTTP Get
     *
     * @param string $method
     *   Method of the API to execute
     * @return array
     *   API return data
     */
    protected function _get($method)
    {
        $this->_setopt(CURLOPT_CUSTOMREQUEST, 'GET');
        $this->_setopt(CURLOPT_POSTFIELDS, '');

        return $this->_execute($method);
    }

    /**
     * Perform a HTTP Post
     *
     * @param string $method
     *   Method of the API to execute
     * @param array $params
     *   Parameters to pass
     * @return array
     *   API return data
     */
    protected function _post($method, array $params = array())
    {
        $this->_setopt(CURLOPT_CUSTOMREQUEST, 'POST');
        $this->_setopt(CURLOPT_POSTFIELDS, json_encode(array('Content' => $params)));
        $securityToken = $this->getSecurityToken();
        return $this->_execute(sprintf("%s?key=%s", $method, $securityToken));
    }

    /**
     * Perform a HTTP Put
     *
     * @param string $method
     *   Method of the API to execute
     * @param array $params
     *   Parameters to pass
     * @return array
     *   API return data
     */
    protected function _put($method, array $params = array())
    {
        $this->_setopt(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->_setopt(CURLOPT_POSTFIELDS, json_encode(array('Content' => $params)));
        $securityToken = $this->getSecurityToken();
        return $this->_execute(sprintf("%s?key=%s", $method, $securityToken));
    }

    /**
     * Perform a HTTP Delete
     *
     * @param string $method
     *   Method of the API to execute
     * @return array
     *   API return data
     */
    protected function _delete($method)
    {
        $this->_setopt(CURLOPT_CUSTOMREQUEST, 'DELETE');
        $securityToken = $this->getSecurityToken();
        return $this->_execute(sprintf("%s?key=%s", $method, $securityToken));
    }

    /**
     * Execute the Curl object
     *
     * @param string $method
     *   Method of the API to execute
     * @return array
     *   API return data
     * @throws RallyApiException
     *   On Curl errors
     */
    protected function _execute($method)
    {
        $method = ltrim($method, '/');
        $url = sprintf("https://%s/slm/webservice/%s/%s", $this->domain, $this->version, $method);

        $this->_setopt(CURLOPT_URL, $url);

        $response = curl_exec($this->curl);

        if (curl_errno($this->curl)) {
            throw new RallyApiException(curl_error($this->curl));
        }

        $info = curl_getinfo($this->curl);

        return $this->_result($response, $info);
    }

    /**
     * Perform Json Decryption of the output
     *
     * @param string $response
     *   Curl Response
     * @param array $info
     *   Curl Info Array
     * @return array
     *   API return data
     * @throws RallyApiException
     *   On non-2xx responses
     */
    protected function _result($response, array $info)
    {
        // Panic on non-200 responses
        if ($info['http_code'] >= 300 || $info['http_code'] < 200) {
            throw new RallyApiException($response, $info['http_code']);
        }

        $object = json_decode($response, true);

        $wrappers = array('OperationResult', 'CreateResult', 'QueryResult');
        // If we have one of these formats, strip out errors
        if (in_array(key($object), $wrappers)) {
            // Strip key
            $object = reset($object);

            // Look for errors and warnings
            if (!empty($object['Errors'])) {
                throw new RallyApiError(implode(PHP_EOL, $object['Errors']));
            }
            if (!empty($object['Warnings'])) {
                throw new RallyApiWarning(implode(PHP_EOL, $object['Warnings']));
            }
        }

        return $object;
    }

    /**
     * Wrapper for curl_setopt
     *
     * @param string $option
     *   the CURLOPT_XXX option to set
     * @param mixed $value
     *   the value
     */
    protected function _setopt($option, $value)
    {
        curl_setopt($this->curl, $option, $value);
    }

    protected function getSecurityToken()
    {
        if (!$this->securityToken) {
            $authResults = $this->_get('security/authorize');
            $this->securityToken = $authResults['SecurityToken'];
        }

        return $this->securityToken;
    }
}

class RallyApiException extends \Exception
{
}

class RallyApiError extends RallyApiException
{
}

class RallyApiWarning extends RallyApiException
{
}
