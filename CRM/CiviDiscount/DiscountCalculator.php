<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
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
 * @package CiviDiscount
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

class CRM_CiviDiscount_DiscountCalculator {
  protected $entity;
  protected $entity_id;
  protected $discounts = array();
  protected $contact_id;
  protected $code;
  protected $entity_discounts;

  protected $code_discounts;

  /**
   * configured message for when discount does not apply
   * @var string
   */
  protected $discount_unavailable_message = array();

  /**
   * @var bool Are we Just checking whether we should display a field for a discount code
   */
  protected $is_display_field_mode;

  /**
   * @var bool
   */
  protected $auto_discount_applies;

  /**
   * @var array automatic discounts - ie because contact meets a criteria
   */
  protected $autoDiscounts = array();

  /**
   * Constructor
   *
   * @param string $entity
   * @param integer $entity_id
   * @param integer $contact_id
   * @param string $code
   * @param $is_display_field_mode
   */
  function __construct($entity, $entity_id, $contact_id, $code, $is_display_field_mode) {
    if(empty($code) && empty($contact_id) && !$is_display_field_mode) {
      $this->discounts = array();
    }
    else {
      $this->discounts = CRM_CiviDiscount_BAO_Item::getValidDiscounts();
    }
    $this->entity = $entity;
    $this->contact_id = $contact_id;
    $this->entity_id = $entity_id;
    $this->code = trim($code);
    $this->is_display_field_mode = $is_display_field_mode;
  }

  /**
   * Get discounts that apply in this instance
   *
   * @return array
   */
  public function getDiscounts() {
    //@todo now this has been simplified down I'd like to move the calc
    // into a separate function (possibly into construct so that after construct only 'getting' happens
    //just need to check nothing is changed. Would set an
    $this->filterDiscountByEntity();
    if(!empty($this->code)) {
      $this->filterDiscountByCode();
      return $this->discounts;
    }
    $this->filterDiscountsByContact();
    if($this->is_display_field_mode) {
      return $this->discounts;
    }
    else {
      return $this->autoDiscounts;
    }
  }

  /**
   * filter this discounts according to entity
   */
  private function filterDiscountByEntity() {
    $this->setEntityDiscounts();
    $this->discounts = array_intersect_key($this->discounts, $this->entity_discounts);
  }

  public function getDiscountUnavailableMessage() {
    return implode(' ', $this->discount_unavailable_message);
  }

  /**
   * Filter discounts by autodiscount criteria. If any one of the criteria is not met for this contact then the discount
   * does not apply
   *
   * We can assume that the no-contact id situation is dealt with in that
   * our scenarios are
   * - no contact id, no code & 'is_display_field_mode' - ie. anonymous mode so no discounts apply
   * - no contact id, no code & is not is_display_field_mode' - ie we won't have populated discounts in construct
   * (saves a query)
   */
  private function filterDiscountsByContact() {
    if(empty($this->contact_id)) {
      $this->autoDiscounts = array();
      return;
    }
    $this->autoDiscounts = $this->discounts;
    foreach ($this->discounts as $discount_id => $discount) {
      if(empty($discount['autodiscount'])) {
        unset($this->autoDiscounts[$discount_id]);
        continue;
      }
      $this->auto_discount_applies = TRUE;
      $this->autoDiscounts[$discount_id]['is_auto_discount'] = TRUE;
      foreach (array_keys($discount['autodiscount']) as $entity) {
        $additionalParams = array('contact_id' => $this->contact_id);
        $id = ($entity == 'contact') ? $this->contact_id : NULL;

        if(!$this->checkDiscountsByEntity($discount, $entity, $id, 'autodiscount', $additionalParams)) {
          $this->discount_unavailable_message[] = $discount['discount_msg'];
          unset($this->autoDiscounts[$discount_id]);
          continue;
        }
      }
    }
  }

  /**
   * get discounts relative to the entity
   */
  public function getEntityDiscounts() {
    if(is_array($this->entity_discounts)) {
      return $this->entity_discounts;
    }
    $this->setEntityDiscounts();
    return $this->entity_discounts;
  }

  /**
   * get discounts relative to the entity
   */
  function getEntityHasDiscounts() {
    $this->getDiscounts();
    if(!empty($this->entity_discounts)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * should we show a field for a discount code?
   * If there is no code for this entity or the only discount for this entity is already applied
   * then no. If more than one discount could auto-apply going with 'Yes' at this stage in
   * case they have the option to enter a key for the other one
   *
   * @return boolean
   */
  public function isShowDiscountCodeField() {
    if (!$this->getEntityHasDiscounts()) {
      return FALSE;
    }
    if(!empty($this->entity_discounts) && count($this->entity_discounts ==1) && array_keys($this->entity_discounts) != array_keys($this->autoDiscounts)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * getter for autodiscount
   */
  public function isAutoDiscount() {
    return $this->auto_discount_applies;
  }

  /**
   * Filter out discounts that are not applicable based on id or other filters
   * @internal param array $discounts discount array from db
   * @internal param string $entity - this should match the api entity
   * @internal param int $id entity id
   * @internal param string $type 'filters' or autodiscount
   * @internal param array $additionalFilter e.g array('contact_id' => x) when looking at memberships
   */
  private function setEntityDiscounts() {
    $this->entity_discounts = array();
    foreach ($this->discounts as $discount_id => $discount) {
      if($this->checkDiscountsByEntity($discount, $this->entity, $this->entity_id, 'filters')) {
        $this->entity_discounts[$discount_id] = $discount;
      }
    }
  }

  /**
   * Check if discount is applicable - we check the 'filters' to see if
   * 1) there are any filters for this entity type - no filter means NO
   * 2) there is an empty filter for this entity type - means 'any'
   * 3) the only filter is on id (in which case we will do a direct comparison
   * 4) there is an api filter
   *
   * @param $discount
   * @param $entity
   * @param integer $id entity id
   * @param string $type 'filters' or autodiscount
   * @param array $additionalFilter e.g array('contact_id' => x) when looking at memberships
   *
   * @return bool
   * @internal param array $discounts discount array from db
   * @internal param string $field - this should match the api entity
   */
  private function checkDiscountsByEntity($discount, $entity, $id, $type, $additionalFilter = array()) {
    try {
      if(!isset($discount[$type][$entity])) {
        return FALSE;
      }
      if(empty($discount[$type][$entity])) {
        return TRUE;
      }
      if(array_keys($discount[$type][$entity]) == array('id')) {

        if (!empty($discount[$type][$entity]['id']['IN'])) {
          return in_array($id, $discount[$type][$entity]['id']['IN']);
        }
        //@todo - remove this in favour of above? is it always consistent?
        return in_array($id, $discount[$type][$entity]['id']);
      }
      $params = $discount[$type][$entity] +  array_merge(array(
        'options' => array('limit' => 999999999), 'return' => 'id'
      ), $additionalFilter);
      $ids = civicrm_api3($entity, 'get', $params);
      if($id) {
        return in_array($id, array_keys($ids['values']));
      }
      else {
        return !empty($ids['values']);
      }
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

  /**
   * If a code is passed in we are going to unset any filters that don't match the code
   *
   * case sensitive
   *
   * @return array discounts that match the code
   */
  private function filterDiscountByCode() {
    foreach ($this->discounts as $id => $discount) {
      if (strcasecmp($this->code, $discount['code']) != 0) {
        unset($this->discounts[$id]);
      }
    }
    return $this->discounts;
  }
}
