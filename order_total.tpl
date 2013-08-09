売上レポート  集計日： <!--{$countDate}-->

******************************************************************
　Daily Report
******************************************************************
今週の売上： <!--{$arrTotal.thisWeekTotal.total|number_format|default:0}-->円 (締：<!--{$arrWeekDays[$arrTotal.thisWeekTotal.cutDay]}-->)
今月の売上： <!--{$arrTotal.thisMonthTotal.total|number_format|default:0}-->円 (締：<!--{$arrTotal.thisMonthTotal.cutDate}-->)

昨日の明細
<!--{foreach from=$arrTotal.Daily item=order}-->
<!--{$order.payment_date}--> <!--{$order.bumon}--> <!--{$order.order_name01}--><!--{$order.order_name02}--> <!--{$order.product_name}--> ￥<!--{$order.total|number_format|default:0}-->
<!--{foreachelse}-->
昨日の売上はありません
<!--{/foreach}-->

******************************************************************
　未入金一覧
******************************************************************
<!--{foreach from=$arrTotal.unPaidOrder item=order}-->
<!--{$order.sales_date}-->  <!--{$order.bumon}--> <!--{$order.order_name01}--><!--{$order.order_name02}--> <!--{$order.product_name}--> <!--{$order.total}-->
<!--{foreachelse}-->
未入金はありません
<!--{/foreach}-->

================================================================
