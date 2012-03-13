<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.1                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Admin/Form.php';

/**
 * This class generates form components for Location Type
 * 
 */
class CDM_Form_Discount_Add extends CRM_Admin_Form
{
    /**
     * Function to build the form
     *
     * @return None
     * @access public
     */
    public function buildQuickForm( ) 
    {
        parent::buildQuickForm( );
       
        if ($this->_action & CRM_Core_Action::DELETE ) { 
            return;
        }
        
        $this->applyFilter('__ALL__', 'trim');
        $this->add('text',
                   'code',
                   ts('Code'),
                   CRM_Core_DAO::getAttribute( 'CDM_DAO_Item', 'code' ),
                   true );
        $this->addRule( 'code',
                        ts('Code already exists in Database.'),
                        'objectExists',
                        array( 'CDM_DAO_Item', $this->_id, 'code' ) );
        $this->addRule( 'code',
                        ts( 'Code can only consist of alpha-numeric characters' ),
                        'variable' );
         
        $this->add('text', 'description', ts('Description'), CRM_Core_DAO::getAttribute( 'CDM_DAO_Item', 'description' ) );

        $this->addMoney( 'amount', ts('Discount'), true, CRM_Core_DAO::getAttribute( 'CDM_DAO_Item', 'amount' ), false );

        $this->add( 'select', 'amount_type', ts( 'Amount Type' ),
                    array( 1 => ts( 'Percentage' ),
                           2 => ts( 'Monetary'   ) ),
                    true );

        $this->add('text', 'count_max', ts( 'Usage' ), CRM_Core_DAO::getAttribute( 'CDM_DAO_Item', 'count_max' ), true );
        $this->addRule( 'count_max', ts('Must be an integer') , 'integer' );

        $this->addDate( 'expiration_date', ts( 'Expiration Date' ), false );

        $this->add( 'text', 'organization', ts( 'Organization' ) );
        $this->add( 'hidden', 'organization_id', '', array( 'id' => 'organization_id' ) );

        $organizationURL = CRM_Utils_System::url( 'civicrm/ajax/rest', 'className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1&context=contact&org=1&employee_id='.$this->_contactId, false, null, false );
        $this->assign('organizationURL', $organizationURL );

        // is this discount active ?
        $this->addElement('checkbox', 'is_active', ts('Is this discount active?') );
    }

       
    /**
     * Function to process the form
     *
     * @access public
     * @return None
     */
    public function postProcess() {
        if ( $this->_action & CRM_Core_Action::DELETE ) {
            CDM_BAO_Item::del( $this->_id );
            CRM_Core_Session::setStatus( ts('Selected Discount has been deleted.') );
            return;
        }

        // store the submitted values in an array
        $params = $this->exportValues();
            
        // action is taken depending upon the mode
        $item                  = new CDM_DAO_Item( );
        $item->code            = $params['code'];
        $item->description     = $params['description'];
        $item->amount          = $params['amount'];
        $item->amount_type     = $params['amount_type'];
        $item->count_max       = $params['count_max'];

        require_once 'CRM/Utils/Date.php';
        $item->expiration_date = CRM_Utils_Date::processDate( $params['expiration_date'] );

        $item->organization_id = $params['organization_id'];
        $item->is_active       = $params['is_active'];
            
        if ($this->_action & CRM_Core_Action::UPDATE ) {
            $item->id = $this->_id;
        }
            
        $item->save( );
        
        CRM_Core_Session::setStatus( ts('The discount \'%1\' has been saved.',
                                        array( 1 => $item->description )) );
    } //end of function

}


