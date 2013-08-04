<?php
/**
 * Created by JetBrains PhpStorm.
 * User: y-kim
 * Date: 13/07/30
 * Time: 8:47
 * To change this template use File | Settings | File Templates.
 */
define('HTML_REALDIR', rtrim(realpath(rtrim(realpath((dirname(dirname(dirname(dirname(__FILE__)))))), '/\\') . '/'), '/\\') . '/html/');

if (!defined('ADMIN_FUNCTION') || ADMIN_FUNCTION !== true) {
    define('FRONT_FUNCTION', true);
}
// {{{ requires
require_once HTML_REALDIR . 'define.php';
if (defined('SAFE') && SAFE === true) {
    require_once HTML_REALDIR . HTML2DATA_DIR . 'require_safe.php';
} else {
    require_once HTML_REALDIR . 'handle_error.php';
    require_once HTML_REALDIR . HTML2DATA_DIR . 'require_base.php';
}

class Reports_OrderTotal {

    // 週の基準になる日
    private $weeklyBaseDay = 1;
    // 月締めの基準となる頭
    private $monthlyHeadDay = 5;
    // 毎日メールの送信先
    private $dailyMailTo = 'yangsin_kim@lockon.co.jp';
    // 週間メールの送信先
    private $weeklyMailTo = 'yangsin_kim@lockon.co.jp';
    // 月間メールの送信先
    private $monthlyMailTo = 'yangsin_kim@lockon.co.jp';
    // DateTimeオブジェクト
    private $objDate = '';
    // 曜日の配列
    private $arrWeekDays = array(
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday'
    );
    // 売上とみなさない受注ステータス
    private $order_status = '1,3,7';
    // 未入金の受注ステータス
    private $unPaid_status = '4';


    // }}}
    // {{{ functions
    function init($argv = "")
    {
        /// 初期化処理
        if ( SC_Utils_Ex::isBlank($argv[1])) {
            $this->objDate = new DateTime();
        } else {
           $this->objDate = new DateTime($argv[1]);
        }
    }

    function execute()
    {

        //締め日なのか確認して月間の売上をだすか確認
        if ($this->objDate->format('j') == $this->monthlyHeadDay ) {
            $arrTotal['monthly'] = $this->calMonthlyOrderTotal();
        }
        // 週の基準日か確認して週間の売上をだすか確認
        if ($this->objDate->format('N') == $this->weeklyBaseDay ) {
            $arrTotal['weekly'] = $this->calWeeklyOrderTotal();
        }

        // 前日の売上の明細を取得
        $arrTotal['Daily'] = $this->calDailyOrderTotal();

        // 今週の売上金額集計
        $arrTotal['thisWeekTotal'] = $this->calThisWeekTotal();

        // 今月の売上金額集計
        $arrTotal['thisMonthTotal'] = $this->calThisMonthTotal();

        // 現在の未入金一覧
        $arrTotal['unPaidOrder'] = $this->checkUnpaidOrder();

        // メールの送信
        $this->sendReportsMail($arrTotal);
        var_dump($arrTotal);

    }

    function calMonthlyOrderTotal()
    {
        // 月間の売上を計算
        $endDate = $this->objDate->format('Y-m-d');
        $objStartDate = new DateTime( $endDate . '-1month');
        $startDate = $objStartDate->format('Y-m-d');
        $arrMonthDetail = $this->getOrderTotalDetail($startDate, $endDate);

        return $arrMonthDetail;
    }

    function calWeeklyOrderTotal()
    {
        //週間の売上を計算
        $endDate = $this->objDate->format('Y-m-d');
        $objStartDate = new DateTime( $endDate . '-1week');
        $startDate = $objStartDate->format('Y-m-d');
        $arrWeeklyDetail = $this->getOrderTotalDetail($startDate, $endDate);

        return $arrWeeklyDetail;
    }

    function calDailyOrderTotal()
    {
        $endDate = $this->objDate->format('Y-m-d');
        $objStartDate = new DateTime( $endDate . '-1day');
        $startDate = $objStartDate->format('Y-m-d');
        $arrDailyDetail = $this->getOrderTotalDetail($startDate, $endDate);

        return $arrDailyDetail;
    }


    function calThisWeekTotal()
    {
        $endDate = $this->objDate->format('Y-m-d');
        // 今週の初めを求める
        $lastBaseDayStr = 'last ' . $this->arrWeekDays[$this->weeklyBaseDay];
        $startDay = date('Y-m-d',strtotime($lastBaseDayStr));
        $arrWeekTotal = $this->getOrderTotal($startDay, $endDate);

        return $arrWeekTotal;
    }

    function calThisMonthTotal()
    {
        $endDate = $this->objDate->format('Y-m-d');
        if ( $this->objDate->format('j') > $this->monthlyHeadDay ) {
            $startDate = $this->objDate->format('Y-m-' . str_pad($this->monthlyHeadDay, 2, '0', STR_PAD_LEFT));
        } else {
            $objStartDate = new DateTime( $endDate . '-1month');
            $startDate = $objStartDate->format('Y-m-' . str_pad($this->monthlyHeadDay, 2, '0', STR_PAD_LEFT));
        }

        $arrMonthTotal= $this->getOrderTotal($startDate, $endDate);

        return $arrMonthTotal;
    }

    function checkUnpaidOrder()
    {
        $col =<<<EOF
            A.order_id, A.order_name01, A.order_name02,
            CASE WHEN C.partner_id <> 303 AND B.product_type_id = 5 THEN '他社有料プラグイン'
                ELSE 'EC-CUBE' END AS bumon,
            CASE WHEN A.payment_id = 3 THEN 'zeus'
        			WHEN A.payment_id = 2 THEN '銀行振込'
		        	WHEN A.payment_id = 5 THEN 'GMO-Credit'
                ELSE '' END AS shiharai,
            A.total, to_char(A.create_date, 'YYYY-MM-DD') as sales_date,
            B.product_name
EOF;
        $table = <<<EOF
            dtb_order AS A  INNER JOIN dtb_order_detail  AS B USING(order_id)
               INNER JOIN dtb_products AS C USING (product_id)
EOF;
        $where = "A.del_flg = 0 AND B.price > 0 AND A.status IN ( ? )";

        $order = "sales_date, C.sub_comment4, B.product_code, A.create_date";

        $arrWhereVal = array($this->unPaid_status);
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $objQuery->setOrder($order);
        $arrRet = $objQuery->select($col, $table, $where, $arrWhereVal);

        // 指定範囲の売上の明細
        return $arrRet;

    }



    function sendReportsMail($arrTotal)
    {
        //メールでレポートを送る
        return 'sendmail';
    }

    function getOrderTotal($startDate, $endDate)
    {
        $col = 'SUM(A.total)';
        $table = <<<EOF
            dtb_order AS A  INNER JOIN dtb_order_detail  AS B USING(order_id)
               INNER JOIN dtb_products AS C USING (product_id)
EOF;
        $where = "A.payment_date between ? AND ? AND A.del_flg = 0 AND B.price > 0 AND A.status NOT IN ( 1, 3, 7 )";

        //  --　決済処理中、無料購入、キャンセルを除く
        $arrWhereVal = array($startDate, $endDate);
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $objQuery->setOrder($order);
        $arrRet = $objQuery->select($col, $table, $where, $arrWhereVal);

        // 指定範囲の売上の明細
        return $arrRet;

    }

    function getOrderTotalDetail($startDate, $endDate)
    {
        $col =<<<EOF
            A.order_id, A.order_name01, A.order_name02,
            CASE WHEN C.partner_id <> 303 AND B.product_type_id = 5 THEN '他社有料プラグイン'
                ELSE 'EC-CUBE' END AS bumon,
            CASE WHEN A.payment_id = 3 THEN 'zeus'
        			WHEN A.payment_id = 2 THEN '銀行振込'
		        	WHEN A.payment_id = 5 THEN 'GMO-Credit'
                ELSE '' END AS shiharai,
            A.total, to_char(A.payment_date, 'YYYY-MM-DD') as payment_date,
            to_char(A.create_date, 'YYYY-MM-DD') as sales_date,
            B.product_name, (SELECT name FROM mtb_order_status WHERE id = A.status)  AS bikou
EOF;
         $table = <<<EOF
            dtb_order AS A  INNER JOIN dtb_order_detail  AS B USING(order_id)
               INNER JOIN dtb_products AS C USING (product_id)
EOF;
         $where = "A.payment_date between ? AND ? AND A.del_flg = 0 AND B.price > 0 AND A.status NOT IN ( 1, 3, 7 )";

         $order = "payment_date,sales_date, C.sub_comment4, B.product_code, A.create_date";

        //  --　決済処理中、無料購入、キャンセルを除く
        $arrWhereVal = array($startDate, $endDate);
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $objQuery->setOrder($order);
        $arrRet = $objQuery->select($col, $table, $where, $arrWhereVal);

        // 指定範囲の売上の明細
        return $arrRet;

    }
}

// }}}
// {{{ generate page

$objBatch = new Reports_OrderTotal();
$objBatch->init($argv);
$objBatch->execute();
?>