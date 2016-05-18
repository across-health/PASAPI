<?php

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

use \OEModule\PASAPI\resources\PatientAppointment;

class PatientAppointmentTest extends PHPUnit_Framework_TestCase
{

    public function getMockResource($resource, $methods = array())
    {
        return $this->getMockBuilder("\\OEModule\\PASAPI\\resources\\" . $resource)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

    }

    public function test_save_success_update()
    {
        $pa = $this->getMockResource('PatientAppointment',
            array('validate','getInstanceForClass','startTransaction', 'saveModel', 'audit'));



        $papi_ass = $this->getMockBuilder('OEModule\\PASAPI\\models\\PasApiAssignment')
            ->disableOriginalConstructor()
            ->setMethods(array('findByResource', 'getInternal', 'save', 'unlock'))
            ->getMock();

        $pa->expects($this->at(0))
            ->method('validate')
            ->will($this->returnValue(true));

        $pa->expects($this->at(1))
            ->method('startTransaction')
            ->will($this->returnvalue(null));

        $pa->expects($this->once())
            ->method('getInstanceForClass')
            ->with("OEModule\\PASAPI\\models\\PasApiAssignment")
            ->will($this->returnValue($papi_ass));

        $pa->expects($this->once())
            ->method('saveModel')
            ->will($this->returnValue(true));

        $worklist_patient = ComponentStubGenerator::generate('WorklistPatient', array('id' => 5));

        $papi_ass->expects($this->once())
            ->method('findByResource')
            ->will($this->returnValue($papi_ass));

        $papi_ass->expects($this->once())
            ->method('getInternal')
            ->will($this->returnValue($worklist_patient));

        $papi_ass->expects($this->once())
            ->method('save')
            ->will($this->returnValue(true));

        $papi_ass->expects($this->once())
            ->method('unlock')
            ->will($this->returnValue(true));

        $worklist_patient->expects($this->once())
            ->method('save')
            ->will($this->returnValue(true));

        $this->assertEquals(5, $pa->save());
    }
}