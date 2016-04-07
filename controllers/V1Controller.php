<?php namespace OEModule\PASAPI\controllers;

/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2016
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2016, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

use \UserIdentity;

class V1Controller extends \CController
{

    static protected $resources = array('Patient');
    static protected $version = "V1";
    static protected $supported_formats = array('xml');

    /**
     * @var string output format defaults to xml
     */
    protected $output_format = 'xml';

    /**
     * @TODO: map from output_format when we support multiple.
     *
     * @return string
     */
    protected function getContentType()
    {
        return "application/xml";
    }

    /**
     * This overrides the default behaviour for supported resources by pushing the resource
     * into the GET parameter and updating the actionID
     *
     * This is necessary because there's no way of pushing the appropriate pattern to the top of the
     * URLManager config, so this captures calls where the id doesn't contain non-numerics.
     *
     * @param string $actionID
     * @return \CAction|\CInlineAction
     */
    public function createAction($actionID)
    {
        if (in_array($actionID, static::$resources)) {
            $_GET['resource_type'] = $actionID;
            switch (\Yii::app()->getRequest()->getRequestType())
            {
                case 'PUT':
                    return parent::createAction('Update');
                    break;
                default:
                    $this->sendResponse(405);
                    break;
            }
        }

        return parent::createAction($actionID);
    }

    /**
     *
     * @param \CAction $action
     * @return bool
     */
    public function beforeAction($action)
    {
        foreach (\Yii::app()->request->preferredAcceptTypes as $type) {
            if ($type['baseType'] == 'xml' || $type['subType'] == 'xml' || $type['subType'] == '*') {
                $this->output_format = 'xml';
                break;
            }
            else {
                $this->output_format = $type['baseType'];
            }
        }

        if (!in_array($this->output_format, static::$supported_formats)) {
            $this->sendResponse(406, 'PASAPI only supports ' . implode(",",  static::$supported_formats));
        }

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            $this->sendResponse(401);
        }
        $identity = new UserIdentity($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        if (!$identity->authenticate()) {
            $this->sendResponse(401);
        }

        \Yii::app()->user->login($identity);

        if (!\Yii::app()->user->checkAccess('OprnApi')) {
            $this->sendResponse(403);
        }

        return parent::beforeAction($action);
    }

    /**
     * @param $resource_type
     * @param $id
     */
    public function actionUpdate($resource_type, $id)
    {
        if (!in_array($resource_type, static::$resources))
            $this->sendErrorResponse(404, "Unrecognised Resource type {$resource_type}");

        if (!$id)
            $this->sendResponse(404, "External Resource ID required");

        $body = \Yii::app()->request->rawBody;

        $resource_model = "\\OEModule\\PASAPI\\resources\\{$resource_type}";

        try {
            $resource = $resource_model::fromXml(static::$version, $body);

            if ($resource->errors)
                $this->sendErrorResponse(400, $resource->errors);

            $resource->id = $id;

            if (!$internal_id = $resource->save()) {
                if (!$resource->update_only || $resource->errors) {
                    $this->sendErrorResponse(400, $resource->errors);
                }
                else {
                    // assuming that this was an update only resource request
                    $this->sendSuccessResponse(200, array('Message' => $resource_type . ' not created'));
                }
            }

            $response = array(
                'Id' => $internal_id
            );

            if ($resource->isNewResource) {
                $status_code = 201;
                $response['Message'] = $resource_type . " created.";
            }
            else {
                $status_code = 200;
                $response['Message'] = $resource_type . " updated.";
            }

            if ($resource->warnings) {
                $response['Warnings'] = $resource->warnings;
            }

            $this->sendSuccessResponse($status_code, $response);
        }
        catch (\Exception $e)
        {
            $errors = array(YII_DEBUG ? $e->getMessage() : "Could not save resource");
            $this->sendErrorResponse(500, $errors);
        }



    }

    protected function sendErrorResponse($status, $messages = array())
    {
        $body = "<Failure><Errors><Error>"  . implode("</Error><Error>", $messages) . "</Error></Errors></Failure>";

        $this->sendResponse($status, $body);
    }

    protected function sendSuccessResponse($status, $response)
    {
        $body = "<Success>";
        if (isset($response['Id']))
            $body .= "<Id>{$response['Id']}</Id>";

        $body .= "<Message>{$response['Message']}</Message>";

        if (isset($response['Warnings']))
            $body .= "<Warnings><Warning>" . implode('</Warning><Warning>', $response['Warnings']) . "</Warning></Warnings>";

        $body .= "</Success>";

        $this->sendResponse($status, $body);
    }

    protected function sendResponse($status = 200, $body = '')
    {
        header('HTTP/1.1 ' . $status);
        header('Content-type: ' . $this->getContentType());
        if ($status == 401) header('WWW-Authenticate: Basic realm="OpenEyes"');
        // TODO: configure allowed methods per resource
        if ($status == 405) header('Allow: PUT');
        echo $body;
        \Yii::app()->end();
    }

}