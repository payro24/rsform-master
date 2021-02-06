<?php
/**
 * payro24 payment plugin
 *
 * @developer JMDMahdi, vispa, mnbp1371
 * @publisher payro24
 * @package VirtueMart
 * @subpackage payment
 * @copyright (C) 2020 payro24
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://payro24.ir
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
class plgSystemRSFPpayro24 extends JPlugin
{
  var $componentId = 3543;

  var $componentValue = 'payro24';

  /**
   * plgSystemRSFPpayro24 constructor.
   * @param $subject
   * @param $config
   * @param Http|null $http
   */
  public function __construct(&$subject, $config, Http $http = null)
  {
    $this->http = $http ?: HttpFactory::getHttp();
    parent::__construct($subject, $config);
    $this->newComponents = array(3543);
  }


  /**
   * @param $api_key
   * @param $sandbox
   * @return array
   */
  public function options($api_key,$sandbox)
  {
    $options = array('Content-Type' => 'application/json',
      'P-TOKEN' => $api_key,
      'P-SANDBOX' => $sandbox,
    );
    return $options;
  }

  /**
   *
   */
  function rsfp_bk_onAfterShowComponents()
  {
    $lang = JFactory::getLanguage();
    $lang->load('plg_system_rsfppayro24');
    $formId = JRequest::getInt('formId');
    $link = "displayTemplate('" . $this->componentId . "')";
    if ($components = RSFormProHelper::componentExists($formId, $this->componentId))
      $link = "displayTemplate('" . $this->componentId . "', '" . $components[0] . "')";
    ?>
      <li class="rsform_navtitle"><?php echo 'درگاه payro24'; ?></li>
      <li><a href="javascript: void(0);" onclick="<?php echo $link; ?>;return false;"
             id="rsfpc<?php echo $this->componentId; ?>"><span
                      id="payro24"><?php echo JText::_('اضافه کردن درگاه payro24'); ?></span></a></li>
    <?php
  }

  /**
   * @param $items
   * @param $formId
   */
  function rsfp_getPayment(&$items, $formId)
  {
    if ($components = RSFormProHelper::componentExists($formId, $this->componentId)) {
      $data = RSFormProHelper::getComponentProperties($components[0]);
      $item = new stdClass();
      $item->value = $this->componentValue;
      $item->text = $data['LABEL'] . '(پرداخت امن با آی‌دی‌پی)';

      //JURI::root(true).'/plugins/system/rsfppayro24/assets/images/logo.png
      $items[] = $item;
    }
  }

  /**
   * @param array $args
   */
  function rsfp_bk_onAfterCreateComponentPreview($args = array())
  {
    if ($args['ComponentTypeName'] == 'payro24') {
      $args['out'] = '<td>&nbsp;payro24</td>';
      $args['out'] .= '<td><img src=' . JURI::root(true) . '/plugins/system/rsfppayro24/assets/images/logo.png />' . $args['data']['LABEL'] . '</td>';
    }
  }

  /**
   * @param $tabs
   */
  function rsfp_bk_onAfterShowConfigurationTabs($tabs)
  {
    $lang = JFactory::getLanguage();
    $lang->load('plg_system_rsfppayro24');
    $tabs->addTitle('تنظیمات درگاه payro24', 'form-TRANGELpayro24');
    $tabs->addContent($this->payro24ConfigurationScreen());
  }

  /**
   * @param $payValue
   * @param $formId
   * @param $SubmissionId
   * @param $price
   * @param $products
   * @param $code
   */
  function rsfp_doPayment($payValue, $formId, $SubmissionId, $price, $products, $code)
  {
    $components = RSFormProHelper::componentExists($formId, $this->componentId);
    $data = RSFormProHelper::getComponentProperties($components[0]);
    $app = JFactory::getApplication();

    if ($data['TOTAL'] == 'YES') {
      $price = (int)$_POST['form']['rsfp_Total'];
    } elseif ($data['TOTAL'] == 'NO') {
      if ($data['FIELDNAME'] == 'Select the desired field') {
        $msg = 'فیلدی برای قیمت انتخاب نشده است.';
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }
      $price = $_POST['form'][$data['FIELDNAME']];
    }

    if (is_array($price))
      $price = (int)array_sum($price);

    if (!$price) {
      $msg = 'مبلغی وارد نشده است';
      $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
      $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
    }

    $currency = RSFormProHelper::getConfig('payro24.currency');
    $price = $this->rsfp_payro24_get_amount($price, $currency);

    // execute only for our plugin
    if ($payValue != $this->componentValue) return;
    $tax = RSFormProHelper::getConfig('payro24.tax.value');
    if ($tax)
      $nPrice = round($tax, 0) + round($price, 0);
    else
      $nPrice = round($price, 0);

    if ($nPrice > 100) {
      $api_key = RSFormProHelper::getConfig('payro24.api');
      $sandbox = RSFormProHelper::getConfig('payro24.sandbox') == 'no' ? 'false' : 'true';
      $amount = $nPrice;
      $desc = 'پرداخت سفارش شماره: ' . $formId;
      $callback = JURI::root() . 'index.php?option=com_rsform&task=plugin&plugin_task=payro24.notify&code=' . $code;
      if (empty($amount)) {
        $msg = 'واحد پول انتخاب شده پشتیبانی نمی شود.';
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

      $data = array('order_id' => $formId, 'amount' => $amount, 'phone' => '', 'mail' => '', 'desc' => $desc, 'callback' => $callback,);
      $url='https://api.payro24.ir/v1.1/payment';
      $options = $this->options($api_key,$sandbox);
      $result = $this->http->post($url, json_encode($data, true), $options);
      $http_status = $result->code;
      $result = json_decode($result->body);

      //save payro24_id in db
      $db = JFactory::getDBO();
      $sql = 'INSERT INTO `#__rsform_submission_values` (FormId, SubmissionId, FieldName,FieldValue) VALUES (' . $formId . ',' . $SubmissionId . ',"payro24_id","' . $result->id . '")';
      $db->setQuery($sql);
      $db->execute();

      if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
        $msg = 'خطا هنگام ایجاد تراکنش. وضعیت خطا:' . $http_status . "<br>" . 'کد خطا: ' . $result->error_code . ' پیغام خطا ' . $result->error_message;
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($result->status));
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

      $app->redirect($result->link);
    } else {
      $msg = 'مبلغ وارد شده کمتر از ۱۰۰۰۰ ریال می باشد';
      $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
      $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
    }
  }

  /**
   * @return |null
   */
  function rsfp_f_onSwitchTasks()
  {
    if (JRequest::getVar('plugin_task') == 'payro24.notify') {
      $app = JFactory::getApplication();
      $jinput = $app->input;

      //get status result of payment api
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $track_id = $_POST['track_id'];
        $formId = $_POST['order_id'];
        $pid = $_POST['id'];
        $pOrderId = $_POST['order_id'];
        $status = $_POST['status'];
      }
      elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $track_id = $_GET['track_id'];
        $formId = $_GET['order_id'];
        $pid = $_GET['id'];
        $pOrderId = $_GET['order_id'];
        $status = $_GET['status'];
      }

      $code = $jinput->get->get('code', '', 'STRING');
      $db = JFactory::getDBO();
      $db->setQuery("SELECT SubmissionId FROM #__rsform_submissions s WHERE s.FormId='" . $formId . "' AND MD5(CONCAT(s.SubmissionId,s.DateSubmitted)) = '" . $db->escape($code) . "'");
      $SubmissionId = $db->loadResult();
      $components = RSFormProHelper::componentExists($formId, $this->componentId);
      $data = RSFormProHelper::getComponentProperties($components[0]);
      $app = JFactory::getApplication();

      if ($data['TOTAL'] == 'YES') {
        $fieldname="rsfp_Total";
      } elseif ($data['TOTAL'] == 'NO') {
        $fieldname=$data['FIELDNAME'];
      }

      $price = round($this::getPayerPrice($formId, $SubmissionId,$fieldname), 0);

      //convert to currency
      $currency = RSFormProHelper::getConfig('payro24.currency');
      $price = $this->rsfp_payro24_get_amount($price, $currency);

      $order_id = $formId;
      if (!empty($pid) && !empty($pOrderId) && $pOrderId == $order_id) {

        //in waiting confirm
        if ($status == 10) {

          $api_key = RSFormProHelper::getConfig('payro24.api');
          $sandbox = RSFormProHelper::getConfig('payro24.sandbox') == 'no' ? 'false' : 'true';
          $data = array('id' => $pid, 'order_id' => $order_id,);
          $url='https://api.payro24.ir/v1.1/payment/verify';
          $options = $this->options($api_key,$sandbox);

          // send request and get result
          $result = $this->http->post($url, json_encode($data, true), $options);
          $http_status = $result->code;
          $result = json_decode($result->body);

          //http error
          if ($http_status != 200) {
            $msg = sprintf('خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message);
            $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($status));
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
          }

          $verify_status = empty($result->status) ? NULL : $result->status;
          $verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
          $verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
          $verify_amount = empty($result->amount) ? NULL : $result->amount;
          $hashed_card_no = empty($result->payment->hashed_card_no) ? NULL : $result->payment->hashed_card_no;
          $card_no = empty($result->payment->hashed_card_no) ? NULL : $result->payment->hashed_card_no;

          //failed verify
          if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $price || $verify_status < 100) {
            $msg = $this->payro24_get_failed_message($verify_track_id, $order_id, $verify_status);
            $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($verify_status));
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');

            //successful verify
          } else {

            //check double spending
            $db = JFactory::getDBO();
            $sql = 'SElECT FieldValue FROM ' . "#__rsform_submission_values" . '  WHERE FieldName="payro24_id" AND FormId=' . $formId . ' AND SubmissionId = ' . $SubmissionId;
            $db->setQuery($sql);
            $db->execute();
            $exist = $db->loadObjectList();
            var_dump($exist);
            $exist = count($exist);

            if ($verify_order_id !== $order_id or !$exist) {
              $msg = $this->payro24_get_failed_message($verify_track_id, $order_id, 0);
              $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages(0));
              $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
              $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
            }

            $mainframe = JFactory::getApplication();
            $mainframe->triggerEvent('rsfp_afterConfirmPayment', array($SubmissionId));
            $msgForSaveDataTDataBase = $this->otherStatusMessages($verify_status) . "کد پیگیری :  $verify_track_id " . "شماره کارت :  $card_no " . "شماره کارت رمزنگاری شده : $hashed_card_no ";
            $this->updateAfterEvent($formId, $SubmissionId, $msgForSaveDataTDataBase);
            $msg = $this->payro24_get_success_message($verify_track_id, $order_id, $verify_status);
            $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;

            $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'success');
          }

        } else {
          //save pay failed pay message(payment api)
          $msg = $this->payro24_get_failed_message($track_id, $order_id, $status);
          $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($status));
          $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
          $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
        }

      } else {

        $msg = $this->payro24_get_failed_message($track_id, $order_id, $status);
        $this->updateAfterEvent($formId, $SubmissionId, $this->otherStatusMessages($status));
        $link = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId;
        $app->redirect($link, '<h2>' . $msg . '</h2>', $msgType = 'Error');
      }

    } else {
      return NULL;
    }
  }

  /**
   * @return false|string
   */
  function payro24ConfigurationScreen()
  {
    ob_start();
    ?>
      <div id="page-payro24" class="com-rsform-css-fix">
          <table class="admintable">
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key"><label
                              for="api"><?php echo 'API KEY'; ?></label></td>
                  <td><input type="text" name="rsformConfig[payro24.api]"
                             value="<?php echo RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.api')); ?>"
                             size="100" maxlength="64"></td>
              </tr>
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key">
                      <label><?php echo 'آزمایشگاه'; ?></label></td>
                  <td>
                      <select name="rsformConfig[payro24.sandbox]">
                          <option value="yes"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.sandbox')) == 'yes' ? 'selected="selected"' : ""); ?>>
                              بله
                          </option>
                          <option value="no"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.sandbox')) == 'no' ? 'selected="selected"' : ""); ?>>
                              خیر
                          </option>
                      </select>
                  </td>

              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key">
                      <label><?php echo 'currency'; ?></label></td>
                  <td>
                    <?php
                    echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.currency')));

                    ?>
                      <select name="rsformConfig[payro24.currency]">
                          <option value="RIAL"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.currency')) == 'RIAL' ? 'selected="selected"' : ""); ?>>
                              ریال
                          </option>
                          <option value="IRT"<?php echo(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.currency')) == 'IRT' ? 'selected="selected"' : ""); ?>>
                              تومان
                          </option>
                      </select>
                  </td>
              </tr>
              </tr>
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key">
                      <label><?php echo 'پیام پرداخت موفق'; ?></label></td>
                  <td><textarea
                              name="rsformConfig[payro24.success_massage]"><?php echo(!empty(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.success_massage'))) ? RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.success_massage')) : "پرداخت شما با موفقیت انجام شد. کد رهگیری: {track_id}"); ?></textarea><br>متن
                      پیامی که می خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت
                      کدهای {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری پیرو استفاده
                      نمایید.
                  </td>
              </tr>
              <tr>
                  <td width="200" style="width: 200px;" align="right" class="key">
                      <label><?php echo 'پیام پرداخت ناموفق'; ?></label></td>
                  <td><textarea
                              name="rsformConfig[payro24.failed_massage]"><?php echo(!empty(RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.failed_massage'))) ? RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('payro24.failed_massage')) : "پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید."); ?></textarea><br>متن
                      پیامی که می خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از
                      شورت کدهای {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری پیرو استفاده
                      نمایید.
                  </td>
              </tr>


          </table>
      </div>

    <?php

    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @return mixed
   */
  function getPayerMobile($formId, $SubmissionId)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    $query->select('FieldValue')
      ->from($db->qn('#__rsform_submission_values'));
    $query->where(
      $db->qn('FormId') . ' = ' . $db->q($formId)
      . ' AND ' .
      $db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
      . ' AND ' .
      $db->qn('FieldName') . ' = ' . $db->q('mobile')
    );
    $db->setQuery((string)$query);
    $result = $db->loadResult();
    return $result;
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @param $fieldName
   * @return int
   */
  function getPayerPrice($formId, $SubmissionId, $fieldName)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    $query->select('FieldValue')
      ->from($db->qn('#__rsform_submission_values'));
    $query->where(
      $db->qn('FormId') . ' = ' . $db->q($formId)
      . ' AND ' .
      $db->qn('SubmissionId') . ' = ' . $db->q($SubmissionId)
      . ' AND ' .
      $db->qn('FieldName') . ' = ' . $db->q($fieldName)
    );
    $db->setQuery((string)$query);
    $result = $db->loadResult();

    return (int)$result;
  }

  /**
   * @param $track_id
   * @param $order_id
   * @param null $msgNumber
   * @return string
   */
  public function payro24_get_failed_message($track_id, $order_id, $msgNumber = null)
  {
    //get defult massege
    $failedMassage = RSFormProHelper::getConfig('payro24.failed_massage');
    $msg = $this->otherStatusMessages($msgNumber);
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $failedMassage) . "<br>" . "$msg";
  }

  /**
   * @param $track_id
   * @param $order_id
   * @return string|string[]
   */
  public function payro24_get_success_message($track_id, $order_id)
  {
    //get defult success massage
    $successMassage = RSFormProHelper::getConfig('payro24.success_massage');
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $successMassage);
  }

  /**
   * @param $msgNumber
   * @get status from $_POST['status]
   * @return string
   */
  public function otherStatusMessages($msgNumber = null)
  {
    switch ($msgNumber) {
      case "1":
        $msg = "پرداخت انجام نشده است";
        break;
      case "2":
        $msg = "پرداخت ناموفق بوده است";
        break;
      case "3":
        $msg = "خطا رخ داده است";
        break;
      case "4":
        $msg = "بلوکه شده";
        break;
      case "5":
        $msg = "برگشت به پرداخت کننده";
        break;
      case "6":
        $msg = "برگشت خورده سیستمی";
        break;
      case "7":
        $msg = "انصراف از پرداخت";
        break;
      case "8":
        $msg = "به درگاه پرداخت منتقل شد";
        break;
      case "10":
        $msg = "در انتظار تایید پرداخت";
        break;
      case "100":
        $msg = "پرداخت تایید شده است";
        break;
      case "101":
        $msg = "پرداخت قبلا تایید شده است";
        break;
      case "200":
        $msg = "به دریافت کننده واریز شد";
        break;
      case "0":
        $msg = "سواستفاده از تراکنش قبلی";
        break;
      case null:
        $msg = "خطا دور از انتظار";
        $msgNumber = '1000';
        break;
    }

    return $msg . ' -وضعیت: ' . "$msgNumber";

  }

  /**
   * @param $amount
   * @param $currency
   * @return float|int
   */
  function rsfp_payro24_get_amount($amount, $currency)
  {
    switch (strtolower($currency)) {
      case strtolower('IRR'):
      case strtolower('RIAL'):
        return $amount;

      case strtolower('تومان ایران'):
      case strtolower('تومان'):
      case strtolower('IRT'):
      case strtolower('Iranian_TOMAN'):
      case strtolower('Iran_TOMAN'):
      case strtolower('Iranian-TOMAN'):
      case strtolower('Iran-TOMAN'):
      case strtolower('TOMAN'):
      case strtolower('Iran TOMAN'):
      case strtolower('Iranian TOMAN'):
        return $amount * 10;

      case strtolower('IRHR'):
        return $amount * 1000;

      case strtolower('IRHT'):
        return $amount * 10000;

      default:
        return 0;
    }
  }

  /**
   * @param $formId
   * @param $SubmissionId
   * @param $msg
   * @return bool
   */
  public function updateAfterEvent($formId, $SubmissionId, $msg)
  {
    if (!$SubmissionId) {
      return false;
    }
    $db = JFactory::getDBO();
    $msg = "payro24: $msg";
    $db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue=1 WHERE sv.FieldName='_STATUS' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
    $db->execute();
    $db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue='" . $msg . "'  WHERE sv.FieldValue='payro24' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
    $db->execute();
  }

}
